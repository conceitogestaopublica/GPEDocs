<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notificacao;
use App\Models\Processo\Circular;
use App\Models\Processo\CircularAnexo;
use App\Models\Processo\CircularDestinatario;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CircularController extends Controller
{
    public function index(Request $request): Response
    {
        $tipo = $request->input('tipo', 'recebidas');

        if ($tipo === 'enviadas') {
            $query = Circular::where('remetente_id', Auth::id());
        } else {
            $query = Circular::whereHas('destinatarios', function ($q) {
                $q->where('usuario_id', Auth::id());
            });
        }

        $circulares = $query->with(['remetente', 'destinatarios.usuario'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        // Append lido flag for recebidas
        if ($tipo !== 'enviadas') {
            $circulares->getCollection()->transform(function ($circular) {
                $dest = $circular->destinatarios->firstWhere('usuario_id', Auth::id());
                $circular->lido = $dest?->lido ?? false;
                return $circular;
            });
        }

        return Inertia::render('GED/Circulares/Index', [
            'circulares' => $circulares,
            'filters'    => ['tipo' => $tipo],
        ]);
    }

    public function create(): Response
    {
        $usuarios = User::where('id', '!=', Auth::id())
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('GED/Circulares/Create', [
            'usuarios' => $usuarios,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'assunto'               => ['required', 'string', 'max:255'],
            'conteudo'              => ['required', 'string'],
            'destino_tipo'          => ['required', 'in:todos,setores,usuarios'],
            'destinatarios'         => ['required_if:destino_tipo,usuarios', 'nullable', 'array', 'min:1'],
            'destinatarios.*'       => ['integer', 'exists:users,id'],
            'destino_setores'       => ['required_if:destino_tipo,setores', 'nullable', 'array', 'min:1'],
            'destino_setores.*'     => ['string', 'max:150'],
            'setor_origem'          => ['nullable', 'string', 'max:150'],
            'data_arquivamento_auto'=> ['nullable', 'date'],
            'files'                 => ['nullable', 'array'],
            'files.*'               => ['file', 'max:51200'],
        ]);

        try {
            DB::beginTransaction();

            $year = date('Y');

            $lastNum = Circular::whereYear('created_at', $year)
                ->max(DB::raw("CAST(SPLIT_PART(numero, '/', 2) AS INTEGER)"));

            $nextNum = ($lastNum ?? 0) + 1;
            $numero  = 'CIR-' . $year . '/' . str_pad((string) $nextNum, 6, '0', STR_PAD_LEFT);

            $circular = Circular::create([
                'numero'                => $numero,
                'assunto'               => $request->input('assunto'),
                'conteudo'              => $request->input('conteudo'),
                'remetente_id'          => Auth::id(),
                'setor_origem'          => $request->input('setor_origem'),
                'destino_tipo'          => $request->input('destino_tipo'),
                'destino_setores'       => $request->input('destino_setores'),
                'status'                => 'enviado',
                'enviado_em'            => now(),
                'data_arquivamento_auto'=> $request->input('data_arquivamento_auto'),
            ]);

            // Determine recipients based on destino_tipo
            $destinoTipo = $request->input('destino_tipo');

            if ($destinoTipo === 'todos') {
                $userIds = User::where('id', '!=', Auth::id())->pluck('id');
            } elseif ($destinoTipo === 'usuarios') {
                $userIds = collect($request->input('destinatarios', []));
            } else {
                // setores: all users (except sender)
                $userIds = User::where('id', '!=', Auth::id())->pluck('id');
            }

            foreach ($userIds as $usuarioId) {
                CircularDestinatario::create([
                    'circular_id' => $circular->id,
                    'usuario_id'  => (int) $usuarioId,
                    'lido'        => false,
                ]);
            }

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('circulares', 'documentos');

                    CircularAnexo::create([
                        'circular_id'  => $circular->id,
                        'nome'         => $file->getClientOriginalName(),
                        'arquivo_path' => $path,
                        'tamanho'      => $file->getSize(),
                        'mime_type'    => $file->getMimeType(),
                        'enviado_por'  => Auth::id(),
                    ]);
                }
            }

            // Notifications
            foreach ($userIds as $usuarioId) {
                Notificacao::create([
                    'usuario_id'      => (int) $usuarioId,
                    'tipo'            => 'circular_recebida',
                    'titulo'          => 'Nova circular recebida',
                    'mensagem'        => "Circular {$numero} - {$circular->assunto} recebida.",
                    'referencia_tipo' => 'circular',
                    'referencia_id'   => $circular->id,
                    'lida'            => false,
                ]);
            }

            DB::commit();

            return redirect("/circulares/{$circular->id}")->with('success', 'Circular enviada com sucesso. Numero: ' . $numero);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao enviar circular: ' . $e->getMessage());
        }
    }

    public function show($id): Response
    {
        $circular = Circular::with([
            'remetente',
            'destinatarios.usuario',
            'anexos.enviadoPor',
        ])->findOrFail($id);

        $userId = Auth::id();
        $isRemetente    = $circular->remetente_id === $userId;
        $isDestinatario = $circular->destinatarios->contains('usuario_id', $userId);

        if (! $isRemetente && ! $isDestinatario) {
            abort(403, 'Voce nao tem permissao para visualizar esta circular.');
        }

        if ($isDestinatario) {
            $circular->destinatarios()
                ->where('usuario_id', $userId)
                ->where('lido', false)
                ->update([
                    'lido'    => true,
                    'lido_em' => now(),
                ]);
        }

        return Inertia::render('GED/Circulares/Show', [
            'circular' => $circular,
        ]);
    }

    public function arquivar($id)
    {
        $circular = Circular::findOrFail($id);

        $circular->update([
            'status'       => 'arquivado',
            'arquivado_em' => now(),
        ]);

        return redirect()->back()->with('success', 'Circular arquivada com sucesso.');
    }

    public function arquivarNoGed(Request $request, $id)
    {
        $request->validate(['pasta_id' => ['required', 'integer', 'exists:ged_pastas,id']]);

        $circular = Circular::with(['remetente'])->findOrFail($id);

        $pasta = DB::table('ged_pastas')->where('id', $request->input('pasta_id'))->first();
        if (! $pasta || $pasta->ug_id !== $circular->ug_id) {
            return redirect()->back()->with('error', 'A pasta selecionada nao pertence a UG desta circular.');
        }

        try {
            DB::beginTransaction();

            if ($circular->documento_id) {
                $documento = \App\Models\Documento::find($circular->documento_id);
                if ($documento) {
                    $documento->update(['pasta_id' => (int) $request->input('pasta_id'), 'status' => 'arquivado']);
                    DB::commit();
                    return redirect()->back()->with('success', "Circular arquivada na pasta \"{$pasta->nome}\".");
                }
            }

            $pdf = Pdf::loadView('pdf.circular', [
                'circular'  => $circular,
                'qrCodeUrl' => url("/circulares/verificar/{$circular->qr_code_token}"),
                'ug'        => \App\Models\Ug::find($circular->ug_id),
            ]);
            $pdf->setPaper('A4', 'portrait');
            $pdfBytes = $pdf->output();

            $filename = 'circular-' . str_replace(['/', '\\'], '-', $circular->numero) . '.pdf';
            $path = 'documentos/' . date('Y/m') . '/' . uniqid() . '-' . $filename;
            \Illuminate\Support\Facades\Storage::disk('documentos')->put($path, $pdfBytes);

            $documento = \App\Models\Documento::create([
                'nome'              => 'Circular ' . $circular->numero,
                'descricao'         => $circular->assunto,
                'tipo_documental_id'=> 2, // Memorando/Comunicado
                'pasta_id'          => (int) $request->input('pasta_id'),
                'versao_atual'      => 1,
                'tamanho'           => strlen($pdfBytes),
                'mime_type'         => 'application/pdf',
                'autor_id'          => Auth::id(),
                'status'            => 'arquivado',
            ]);

            \App\Models\Versao::create([
                'documento_id' => $documento->id,
                'versao'       => 1,
                'arquivo_path' => $path,
                'tamanho'      => strlen($pdfBytes),
                'hash_sha256'  => hash('sha256', $pdfBytes),
                'autor_id'     => Auth::id(),
                'comentario'   => 'Arquivado automaticamente do GPE Flow',
            ]);

            $circular->update(['documento_id' => $documento->id]);

            DB::commit();
            return redirect()->back()->with('success', "Circular arquivada na pasta \"{$pasta->nome}\".");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Erro ao arquivar: ' . $e->getMessage());
        }
    }

    public function downloadPdf($id)
    {
        $circular = Circular::with([
            'remetente',
            'destinatarios.usuario',
        ])->findOrFail($id);

        $qrCodeUrl = url("/circulares/verificar/{$circular->qr_code_token}");

        $data = [
            'circular'  => $circular,
            'qrCodeUrl' => $qrCodeUrl,
            'ug'        => \App\Models\Ug::find($circular->ug_id),
        ];

        $pdf = Pdf::loadView('pdf.circular', $data);
        $pdf->setPaper('A4', 'portrait');

        $filename = "circular-{$circular->numero}.pdf";
        $filename = str_replace('/', '-', $filename);

        return $pdf->download($filename);
    }
}
