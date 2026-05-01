<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notificacao;
use App\Models\Processo\Processo;
use App\Models\Processo\ProcessoAnexo;
use App\Models\Processo\ProcessoComentario;
use App\Models\Processo\ProcessoHistorico;
use App\Models\Processo\Tramitacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TramitacaoController extends Controller
{
    public function inbox(): Response
    {
        $tramitacoes = Tramitacao::where('destinatario_id', Auth::id())
            ->whereIn('status', ['pendente', 'recebido', 'em_analise'])
            ->with(['processo.tipoProcesso', 'remetente'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('GED/Processos/Inbox', [
            'tramitacoes' => $tramitacoes,
        ]);
    }

    public function receber(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $tramitacao = Tramitacao::findOrFail($id);
            $tramitacao->update([
                'status'      => 'recebido',
                'recebido_por'=> Auth::id(),
                'recebido_em' => now(),
            ]);

            $tramitacao->processo->update([
                'status' => 'em_tramitacao',
            ]);

            ProcessoHistorico::create([
                'processo_id' => $tramitacao->processo_id,
                'usuario_id'  => Auth::id(),
                'acao'        => 'recebimento',
                'detalhes'    => [
                    'tramitacao_id' => $tramitacao->id,
                    'etapa'         => $tramitacao->tipoEtapa?->nome,
                ],
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Processo recebido com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao receber processo: ' . $e->getMessage());
        }
    }

    public function despachar(Request $request, $id)
    {
        $request->validate([
            'destinatario_id' => ['nullable', 'integer', 'exists:users,id'],
            'despacho'        => ['required', 'string'],
            'setor_destino'   => ['required', 'integer', 'exists:ug_organograma,id'],
            'files'           => ['nullable', 'array'],
            'files.*'         => ['file', 'max:51200'],
        ]);

        // Resolve nome do setor a partir do id
        $unidadeDestino = \App\Models\UgOrganograma::find($request->input('setor_destino'));
        $setorDestinoNome = $unidadeDestino?->nome;
        $setorDestinoId   = (int) $request->input('setor_destino');

        try {
            DB::beginTransaction();

            $tramitacaoAtual = Tramitacao::with('processo.tipoProcesso.etapas')->findOrFail($id);
            $processo = $tramitacaoAtual->processo;

            // Finalizar tramitacao atual
            $tramitacaoAtual->update([
                'status'        => 'despachado',
                'despachado_em' => now(),
                'despacho'      => $request->input('despacho'),
            ]);

            // Determinar proxima etapa
            $proximaEtapa = null;
            if ($tramitacaoAtual->tipo_etapa_id) {
                $etapaAtual = $processo->tipoProcesso->etapas
                    ->where('id', $tramitacaoAtual->tipo_etapa_id)
                    ->first();

                if ($etapaAtual) {
                    $proximaEtapa = $processo->tipoProcesso->etapas
                        ->where('ordem', '>', $etapaAtual->ordem)
                        ->sortBy('ordem')
                        ->first();
                }
            }

            // Criar nova tramitacao
            $novaTramitacao = Tramitacao::create([
                'processo_id'        => $processo->id,
                'tipo_etapa_id'      => $proximaEtapa?->id,
                'ordem'              => $tramitacaoAtual->ordem + 1,
                'setor_origem'       => $tramitacaoAtual->setor_destino,
                'setor_destino'      => $setorDestinoNome,
                'destino_unidade_id' => $setorDestinoId,
                'remetente_id'       => Auth::id(),
                'destinatario_id'    => $request->input('destinatario_id'),
                'status'             => 'pendente',
                'sla_horas'          => $proximaEtapa?->sla_horas ?? $processo->tipoProcesso->sla_padrao_horas,
                'prazo'              => now()->addHours($proximaEtapa?->sla_horas ?? $processo->tipoProcesso->sla_padrao_horas),
            ]);

            $processo->update(['etapa_atual_id' => $novaTramitacao->id]);

            // Armazenar anexos
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('processos', 'documentos');

                    ProcessoAnexo::create([
                        'processo_id'   => $processo->id,
                        'tramitacao_id' => $novaTramitacao->id,
                        'nome'          => $file->getClientOriginalName(),
                        'arquivo_path'  => $path,
                        'tamanho'       => $file->getSize(),
                        'mime_type'     => $file->getMimeType(),
                        'hash_sha256'   => hash_file('sha256', $file->getRealPath()),
                        'enviado_por'   => Auth::id(),
                    ]);
                }
            }

            ProcessoHistorico::create([
                'processo_id' => $processo->id,
                'usuario_id'  => Auth::id(),
                'acao'        => 'despacho',
                'detalhes'    => [
                    'de_tramitacao'   => $tramitacaoAtual->id,
                    'para_tramitacao' => $novaTramitacao->id,
                    'destinatario_id' => $request->input('destinatario_id'),
                    'setor_destino'   => $setorDestinoNome,
                    'destino_unidade_id' => $setorDestinoId,
                    'despacho'        => $request->input('despacho'),
                ],
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Notifica destinatario especifico OU todos os usuarios do setor de destino
            $usuariosNotificar = [];
            if ($request->filled('destinatario_id')) {
                $usuariosNotificar[] = (int) $request->input('destinatario_id');
            } elseif ($setorDestinoId) {
                $usuariosNotificar = \App\Models\User::where('unidade_id', $setorDestinoId)->pluck('id')->all();
            }
            foreach (array_unique($usuariosNotificar) as $uid) {
                Notificacao::create([
                    'usuario_id'     => (int) $uid,
                    'tipo'           => 'processo',
                    'titulo'         => 'Processo despachado para voce',
                    'mensagem'       => "Processo {$processo->numero_protocolo} - {$processo->assunto} foi despachado para voce.",
                    'referencia_tipo'=> 'processo',
                    'referencia_id'  => $processo->id,
                    'lida'           => false,
                ]);
            }

            DB::commit();

            return redirect()->back()->with('success', 'Processo despachado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao despachar processo: ' . $e->getMessage());
        }
    }

    public function devolver(Request $request, $id)
    {
        $request->validate([
            'despacho' => ['nullable', 'string'],
        ]);

        try {
            DB::beginTransaction();

            $tramitacaoAtual = Tramitacao::findOrFail($id);
            $processo = $tramitacaoAtual->processo;

            // Finalizar tramitacao atual
            $tramitacaoAtual->update([
                'status'        => 'devolvido',
                'despachado_em' => now(),
                'despacho'      => $request->input('despacho'),
            ]);

            // Criar nova tramitacao devolvendo ao remetente anterior
            $novaTramitacao = Tramitacao::create([
                'processo_id'     => $processo->id,
                'tipo_etapa_id'   => $tramitacaoAtual->tipo_etapa_id,
                'ordem'           => $tramitacaoAtual->ordem + 1,
                'setor_origem'    => $tramitacaoAtual->setor_destino,
                'setor_destino'   => $tramitacaoAtual->setor_origem,
                'remetente_id'    => Auth::id(),
                'destinatario_id' => $tramitacaoAtual->remetente_id,
                'status'          => 'pendente',
                'sla_horas'       => $tramitacaoAtual->sla_horas,
                'prazo'           => now()->addHours($tramitacaoAtual->sla_horas ?? 48),
            ]);

            $processo->update(['etapa_atual_id' => $novaTramitacao->id]);

            ProcessoHistorico::create([
                'processo_id' => $processo->id,
                'usuario_id'  => Auth::id(),
                'acao'        => 'devolucao',
                'detalhes'    => [
                    'de_tramitacao'   => $tramitacaoAtual->id,
                    'para_tramitacao' => $novaTramitacao->id,
                    'destinatario_id' => $tramitacaoAtual->remetente_id,
                    'despacho'        => $request->input('despacho'),
                ],
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            Notificacao::create([
                'usuario_id'     => $tramitacaoAtual->remetente_id,
                'tipo'           => 'processo',
                'titulo'         => 'Processo devolvido',
                'mensagem'       => "Processo {$processo->numero_protocolo} - {$processo->assunto} foi devolvido para voce.",
                'referencia_tipo'=> 'processo',
                'referencia_id'  => $processo->id,
                'lida'           => false,
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Processo devolvido com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao devolver processo: ' . $e->getMessage());
        }
    }

    public function comentar(Request $request, $id)
    {
        $request->validate([
            'texto'   => ['required', 'string'],
            'interno' => ['nullable', 'boolean'],
        ]);

        try {
            DB::beginTransaction();

            $tramitacao = Tramitacao::findOrFail($id);

            ProcessoComentario::create([
                'processo_id'   => $tramitacao->processo_id,
                'tramitacao_id' => $tramitacao->id,
                'usuario_id'    => Auth::id(),
                'texto'         => $request->input('texto'),
                'interno'       => $request->boolean('interno', false),
            ]);

            ProcessoHistorico::create([
                'processo_id' => $tramitacao->processo_id,
                'usuario_id'  => Auth::id(),
                'acao'        => 'comentario',
                'detalhes'    => [
                    'tramitacao_id' => $tramitacao->id,
                    'interno'       => $request->boolean('interno', false),
                ],
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Comentario adicionado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao adicionar comentario: ' . $e->getMessage());
        }
    }

    public function anexar(Request $request, $id)
    {
        $request->validate([
            'files'   => ['required', 'array'],
            'files.*' => ['file', 'max:51200'],
        ]);

        try {
            DB::beginTransaction();

            $tramitacao = Tramitacao::findOrFail($id);
            $anexosNomes = [];

            foreach ($request->file('files') as $file) {
                $path = $file->store('processos', 'documentos');

                ProcessoAnexo::create([
                    'processo_id'   => $tramitacao->processo_id,
                    'tramitacao_id' => $tramitacao->id,
                    'nome'          => $file->getClientOriginalName(),
                    'arquivo_path'  => $path,
                    'tamanho'       => $file->getSize(),
                    'mime_type'     => $file->getMimeType(),
                    'hash_sha256'   => hash_file('sha256', $file->getRealPath()),
                    'enviado_por'   => Auth::id(),
                ]);

                $anexosNomes[] = $file->getClientOriginalName();
            }

            ProcessoHistorico::create([
                'processo_id' => $tramitacao->processo_id,
                'usuario_id'  => Auth::id(),
                'acao'        => 'anexo',
                'detalhes'    => [
                    'tramitacao_id' => $tramitacao->id,
                    'arquivos'      => $anexosNomes,
                ],
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Anexo(s) adicionado(s) com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao anexar arquivo(s): ' . $e->getMessage());
        }
    }
}
