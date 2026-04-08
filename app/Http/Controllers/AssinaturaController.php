<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Assinatura;
use App\Models\AuditLog;
use App\Models\Documento;
use App\Models\Notificacao;
use App\Models\SolicitacaoAssinatura;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AssinaturaController extends Controller
{
    public function index(): Response
    {
        $pendentes = Assinatura::with(['documento', 'solicitacao.solicitante'])
            ->where('signatario_id', Auth::id())
            ->where('status', 'pendente')
            ->orderByDesc('created_at')
            ->get();

        $assinadas = Assinatura::with(['documento', 'solicitacao.solicitante'])
            ->where('signatario_id', Auth::id())
            ->where('status', 'assinado')
            ->orderByDesc('assinado_em')
            ->limit(20)
            ->get();

        return Inertia::render('GED/Assinaturas/Index', [
            'pendentes' => $pendentes,
            'assinadas' => $assinadas,
        ]);
    }

    public function solicitar(Request $request, $documentoId)
    {
        $request->validate([
            'signatarios'   => ['required', 'array', 'min:1'],
            'signatarios.*' => ['required', 'integer', 'exists:users,id'],
            'mensagem'      => ['nullable', 'string'],
            'prazo'         => ['nullable', 'date'],
        ]);

        $documento = Documento::with('versaoAtual')->findOrFail($documentoId);

        $solicitacao = SolicitacaoAssinatura::create([
            'documento_id'  => $documento->id,
            'solicitante_id'=> Auth::id(),
            'status'        => 'pendente',
            'mensagem'      => $request->input('mensagem'),
            'prazo'         => $request->input('prazo'),
        ]);

        foreach ($request->input('signatarios') as $idx => $userId) {
            $user = User::findOrFail($userId);

            Assinatura::create([
                'solicitacao_id'  => $solicitacao->id,
                'documento_id'    => $documento->id,
                'signatario_id'   => $userId,
                'ordem'           => $idx + 1,
                'status'          => 'pendente',
                'email_signatario'=> $user->email,
            ]);

            Notificacao::create([
                'usuario_id'      => $userId,
                'tipo'            => 'assinatura_pendente',
                'titulo'          => 'Assinatura solicitada',
                'mensagem'        => "Voce tem uma solicitacao de assinatura para o documento \"{$documento->nome}\".",
                'referencia_tipo' => 'documento',
                'referencia_id'   => $documento->id,
            ]);
        }

        AuditLog::create([
            'documento_id' => $documento->id,
            'usuario_id'   => Auth::id(),
            'acao'         => 'solicitacao_assinatura',
            'detalhes'     => ['signatarios' => $request->input('signatarios')],
            'ip'           => $request->ip(),
            'user_agent'   => $request->userAgent(),
        ]);

        return redirect()->back()->with('success', 'Solicitacao de assinatura enviada com sucesso.');
    }

    public function solicitarLote(Request $request)
    {
        $request->validate([
            'documento_ids'  => ['required', 'array', 'min:1'],
            'documento_ids.*'=> ['required', 'integer', 'exists:ged_documentos,id'],
            'signatarios'    => ['required', 'array', 'min:1'],
            'signatarios.*'  => ['required', 'integer', 'exists:users,id'],
            'mensagem'       => ['nullable', 'string'],
            'prazo'          => ['nullable', 'date'],
        ]);

        $count = 0;

        foreach ($request->input('documento_ids') as $documentoId) {
            $documento = Documento::with('versaoAtual')->findOrFail($documentoId);

            $solicitacao = SolicitacaoAssinatura::create([
                'documento_id'   => $documento->id,
                'solicitante_id' => Auth::id(),
                'status'         => 'pendente',
                'mensagem'       => $request->input('mensagem'),
                'prazo'          => $request->input('prazo'),
            ]);

            foreach ($request->input('signatarios') as $idx => $userId) {
                $user = User::findOrFail($userId);

                Assinatura::create([
                    'solicitacao_id'   => $solicitacao->id,
                    'documento_id'     => $documento->id,
                    'signatario_id'    => $userId,
                    'ordem'            => $idx + 1,
                    'status'           => 'pendente',
                    'email_signatario' => $user->email,
                ]);

                Notificacao::create([
                    'usuario_id'      => $userId,
                    'tipo'            => 'assinatura_pendente',
                    'titulo'          => 'Assinatura solicitada',
                    'mensagem'        => "Voce tem uma solicitacao de assinatura para o documento \"{$documento->nome}\".",
                    'referencia_tipo' => 'documento',
                    'referencia_id'   => $documento->id,
                ]);
            }

            AuditLog::create([
                'documento_id' => $documento->id,
                'usuario_id'   => Auth::id(),
                'acao'         => 'solicitacao_assinatura',
                'detalhes'     => ['signatarios' => $request->input('signatarios'), 'lote' => true],
                'ip'           => $request->ip(),
                'user_agent'   => $request->userAgent(),
            ]);

            $count++;
        }

        return redirect()->back()->with('success', "Assinatura solicitada para {$count} documento(s).");
    }

    public function assinar(Request $request, $id)
    {
        $request->validate([
            'cpf'           => ['required', 'string', 'min:11', 'max:14'],
            'geolocalizacao'=> ['nullable', 'string'],
        ]);

        $assinatura = Assinatura::with(['documento.versaoAtual', 'solicitacao'])->findOrFail($id);

        if ($assinatura->signatario_id !== Auth::id()) {
            return redirect()->back()->with('error', 'Voce nao tem permissao para assinar este documento.');
        }

        if ($assinatura->status !== 'pendente') {
            return redirect()->back()->with('error', 'Esta assinatura ja foi processada.');
        }

        $versaoAtual = $assinatura->documento->versaoAtual;

        $assinatura->update([
            'status'          => 'assinado',
            'cpf_signatario'  => $request->input('cpf'),
            'ip'              => $request->ip(),
            'geolocalizacao'  => $request->input('geolocalizacao'),
            'user_agent'      => $request->userAgent(),
            'hash_documento'  => $versaoAtual?->hash_sha256,
            'versao_id'       => $versaoAtual?->id,
            'assinado_em'     => now(),
        ]);

        // Verificar se todas as assinaturas da solicitacao foram concluidas
        $solicitacao = $assinatura->solicitacao;
        $todasAssinadas = $solicitacao->assinaturas()->where('status', 'pendente')->doesntExist();

        if ($todasAssinadas) {
            $solicitacao->update(['status' => 'concluida']);
        } else {
            $solicitacao->update(['status' => 'em_andamento']);
        }

        // Notificar solicitante
        Notificacao::create([
            'usuario_id'      => $solicitacao->solicitante_id,
            'tipo'            => 'assinatura_realizada',
            'titulo'          => 'Documento assinado',
            'mensagem'        => Auth::user()->name . " assinou o documento \"{$assinatura->documento->nome}\".",
            'referencia_tipo' => 'documento',
            'referencia_id'   => $assinatura->documento_id,
        ]);

        AuditLog::create([
            'documento_id' => $assinatura->documento_id,
            'usuario_id'   => Auth::id(),
            'acao'         => 'assinatura',
            'detalhes'     => ['hash' => $versaoAtual?->hash_sha256, 'ip' => $request->ip()],
            'ip'           => $request->ip(),
            'user_agent'   => $request->userAgent(),
        ]);

        return redirect()->back()->with('success', 'Documento assinado com sucesso.');
    }

    public function recusar(Request $request, $id)
    {
        $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        $assinatura = Assinatura::with(['documento', 'solicitacao'])->findOrFail($id);

        if ($assinatura->signatario_id !== Auth::id()) {
            return redirect()->back()->with('error', 'Voce nao tem permissao.');
        }

        $assinatura->update([
            'status'        => 'recusado',
            'motivo_recusa' => $request->input('motivo'),
            'ip'            => $request->ip(),
            'assinado_em'   => now(),
        ]);

        Notificacao::create([
            'usuario_id'      => $assinatura->solicitacao->solicitante_id,
            'tipo'            => 'assinatura_recusada',
            'titulo'          => 'Assinatura recusada',
            'mensagem'        => Auth::user()->name . " recusou assinar o documento \"{$assinatura->documento->nome}\".",
            'referencia_tipo' => 'documento',
            'referencia_id'   => $assinatura->documento_id,
        ]);

        return redirect()->back()->with('success', 'Assinatura recusada.');
    }

    public function manifesto($solicitacaoId)
    {
        $solicitacao = SolicitacaoAssinatura::with([
            'documento.tipoDocumental',
            'documento.autor',
            'solicitante',
            'assinaturas.signatario',
        ])->findOrFail($solicitacaoId);

        $documento = $solicitacao->documento;

        $pdf = Pdf::loadView('assinaturas.manifesto', [
            'solicitacao' => $solicitacao,
            'documento'   => $documento,
        ]);

        $filename = "manifesto-assinatura-{$documento->nome}-{$solicitacao->id}.pdf";

        return $pdf->download($filename);
    }
}
