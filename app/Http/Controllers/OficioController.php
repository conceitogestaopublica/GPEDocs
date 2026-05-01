<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notificacao;
use App\Models\Processo\Oficio;
use App\Models\Processo\OficioAnexo;
use App\Models\Processo\OficioResposta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OficioController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Oficio::where('remetente_id', Auth::id());

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'ilike', "%{$search}%")
                  ->orWhere('assunto', 'ilike', "%{$search}%")
                  ->orWhere('destinatario_nome', 'ilike', "%{$search}%")
                  ->orWhere('destinatario_orgao', 'ilike', "%{$search}%");
            });
        }

        $oficios = $query->with(['remetente'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('GED/Oficios/Index', [
            'oficios' => $oficios,
            'filters' => [
                'status' => $request->input('status', ''),
                'search' => $request->input('search', ''),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('GED/Oficios/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'assunto'             => ['required', 'string', 'max:255'],
            'conteudo'            => ['required', 'string'],
            'destinatario_nome'   => ['required', 'string', 'max:255'],
            'destinatario_email'  => ['required', 'email', 'max:255'],
            'destinatario_cargo'  => ['nullable', 'string', 'max:255'],
            'destinatario_orgao'  => ['nullable', 'string', 'max:255'],
            'setor_origem'        => ['nullable', 'string', 'max:150'],
            'files'               => ['nullable', 'array'],
            'files.*'             => ['file', 'max:51200'],
        ]);

        try {
            DB::beginTransaction();

            $year = date('Y');

            $lastNum = Oficio::whereYear('created_at', $year)
                ->max(DB::raw("CAST(SPLIT_PART(numero, '/', 2) AS INTEGER)"));

            $nextNum = ($lastNum ?? 0) + 1;
            $numero  = 'OF-' . $year . '/' . str_pad((string) $nextNum, 6, '0', STR_PAD_LEFT);

            $oficio = Oficio::create([
                'numero'             => $numero,
                'assunto'            => $request->input('assunto'),
                'conteudo'           => $request->input('conteudo'),
                'remetente_id'       => Auth::id(),
                'setor_origem'       => $request->input('setor_origem'),
                'destinatario_nome'  => $request->input('destinatario_nome'),
                'destinatario_email' => $request->input('destinatario_email'),
                'destinatario_cargo' => $request->input('destinatario_cargo'),
                'destinatario_orgao' => $request->input('destinatario_orgao'),
                'status'             => 'enviado',
                'enviado_em'         => now(),
            ]);

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('oficios', 'documentos');

                    OficioAnexo::create([
                        'oficio_id'           => $oficio->id,
                        'nome'                => $file->getClientOriginalName(),
                        'arquivo_path'        => $path,
                        'tamanho'             => $file->getSize(),
                        'mime_type'           => $file->getMimeType(),
                        'solicitar_assinatura'=> false,
                        'enviado_por'         => Auth::id(),
                    ]);
                }
            }

            Notificacao::create([
                'usuario_id'      => Auth::id(),
                'tipo'            => 'oficio_enviado',
                'titulo'          => 'Oficio enviado',
                'mensagem'        => "Oficio {$numero} - {$oficio->assunto} enviado para {$oficio->destinatario_nome}.",
                'referencia_tipo' => 'oficio',
                'referencia_id'   => $oficio->id,
                'lida'            => false,
            ]);

            DB::commit();

            return redirect("/oficios/{$oficio->id}")->with('success', 'Oficio enviado com sucesso. Numero: ' . $numero);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao enviar oficio: ' . $e->getMessage());
        }
    }

    public function show($id): Response
    {
        $oficio = Oficio::with([
            'remetente',
            'anexos.enviadoPor',
            'respostas.usuario',
        ])->findOrFail($id);

        if ($oficio->remetente_id !== Auth::id()) {
            abort(403, 'Voce nao tem permissao para visualizar este oficio.');
        }

        return Inertia::render('GED/Oficios/Show', [
            'oficio' => $oficio,
        ]);
    }

    public function responder(Request $request, $id)
    {
        $request->validate([
            'conteudo' => ['required', 'string'],
        ]);

        $oficio = Oficio::findOrFail($id);

        if ($oficio->remetente_id !== Auth::id()) {
            abort(403, 'Voce nao tem permissao para responder a este oficio.');
        }

        OficioResposta::create([
            'oficio_id'        => $oficio->id,
            'respondente_nome' => Auth::user()->name,
            'respondente_email'=> Auth::user()->email,
            'conteudo'         => $request->input('conteudo'),
            'externo'          => false,
            'usuario_id'       => Auth::id(),
        ]);

        if ($oficio->status === 'enviado' || $oficio->status === 'lido') {
            $oficio->update(['status' => 'respondido']);
        }

        return redirect()->back()->with('success', 'Resposta enviada com sucesso.');
    }

    public function arquivar($id)
    {
        $oficio = Oficio::findOrFail($id);

        if ($oficio->remetente_id !== Auth::id()) {
            abort(403, 'Voce nao tem permissao para arquivar este oficio.');
        }

        $oficio->update([
            'status'       => 'arquivado',
            'arquivado_em' => now(),
        ]);

        return redirect()->back()->with('success', 'Oficio arquivado com sucesso.');
    }

    public function arquivarNoGed(Request $request, $id)
    {
        $request->validate(['pasta_id' => ['required', 'integer', 'exists:ged_pastas,id']]);

        $oficio = Oficio::with(['remetente'])->findOrFail($id);

        $pasta = DB::table('ged_pastas')->where('id', $request->input('pasta_id'))->first();
        if (! $pasta || $pasta->ug_id !== $oficio->ug_id) {
            return redirect()->back()->with('error', 'A pasta selecionada nao pertence a UG deste oficio.');
        }

        try {
            DB::beginTransaction();

            if ($oficio->documento_id) {
                $documento = \App\Models\Documento::find($oficio->documento_id);
                if ($documento) {
                    $documento->update(['pasta_id' => (int) $request->input('pasta_id'), 'status' => 'arquivado']);
                    DB::commit();
                    return redirect()->back()->with('success', "Oficio arquivado na pasta \"{$pasta->nome}\".");
                }
            }

            $pdf = Pdf::loadView('pdf.oficio', [
                'oficio'    => $oficio,
                'qrCodeUrl' => url("/oficios/verificar/{$oficio->qr_code_token}"),
                'ug'        => \App\Models\Ug::find($oficio->ug_id),
            ]);
            $pdf->setPaper('A4', 'portrait');
            $pdfBytes = $pdf->output();

            $filename = 'oficio-' . str_replace(['/', '\\'], '-', $oficio->numero) . '.pdf';
            $path = 'documentos/' . date('Y/m') . '/' . uniqid() . '-' . $filename;
            \Illuminate\Support\Facades\Storage::disk('documentos')->put($path, $pdfBytes);

            $documento = \App\Models\Documento::create([
                'nome'              => 'Oficio ' . $oficio->numero,
                'descricao'         => $oficio->assunto,
                'tipo_documental_id'=> 1, // Oficio
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

            $oficio->update(['documento_id' => $documento->id]);

            DB::commit();
            return redirect()->back()->with('success', "Oficio arquivado na pasta \"{$pasta->nome}\".");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Erro ao arquivar: ' . $e->getMessage());
        }
    }

    public function downloadPdf($id)
    {
        $oficio = Oficio::with([
            'remetente',
            'respostas.usuario',
        ])->findOrFail($id);

        if ($oficio->remetente_id !== Auth::id()) {
            abort(403, 'Voce nao tem permissao para baixar este oficio.');
        }

        $qrCodeUrl = url("/oficios/verificar/{$oficio->qr_code_token}");

        $data = [
            'oficio'    => $oficio,
            'qrCodeUrl' => $qrCodeUrl,
            'ug'        => \App\Models\Ug::find($oficio->ug_id),
        ];

        $pdf = Pdf::loadView('pdf.oficio', $data);
        $pdf->setPaper('A4', 'portrait');

        $filename = "oficio-{$oficio->numero}.pdf";
        $filename = str_replace('/', '-', $filename);

        return $pdf->download($filename);
    }

    public function rastrear($token)
    {
        $oficio = Oficio::where('rastreio_token', $token)->first();

        if (! $oficio) {
            return Inertia::render('GED/Oficios/Rastreio', [
                'valido' => false,
                'oficio' => null,
            ]);
        }

        if (is_null($oficio->lido_em)) {
            $oficio->update([
                'lido_em' => now(),
                'status'  => 'lido',
            ]);
        }

        if (is_null($oficio->entregue_em)) {
            $oficio->update([
                'entregue_em' => now(),
            ]);
        }

        return Inertia::render('GED/Oficios/Rastreio', [
            'valido' => true,
            'oficio' => [
                'numero'  => $oficio->numero,
                'assunto' => $oficio->assunto,
                'lido_em' => $oficio->lido_em?->format('d/m/Y H:i:s'),
            ],
        ]);
    }
}
