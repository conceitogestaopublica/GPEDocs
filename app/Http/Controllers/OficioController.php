<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notificacao;
use App\Models\Processo\Oficio;
use App\Models\Processo\OficioAnexo;
use App\Models\Processo\OficioResposta;
use App\Support\Relatorio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use App\Support\EmNomeDe;

class OficioController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Oficio::where(fn ($q) => $q->where('remetente_id', Auth::id())->orWhere('digitador_id', Auth::id()));

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

    public function create(Request $request): Response
    {
        $ugId = (int) ($request->session()->get('ug_id') ?? 0);

        $modelos = \App\Models\Processo\OficioModelo::where('ativo', true)
            ->when($ugId, fn ($q) => $q->where(fn ($w) => $w->where('ug_id', $ugId)->orWhereNull('ug_id')))
            ->orderBy('nome')
            ->get(['id', 'nome', 'categoria', 'descricao', 'conteudo']);

        // Organograma da UG ativa (orgaos -> unidades -> setores)
        $setores = collect();
        if ($ugId) {
            $setores = \DB::table('ug_organograma')
                ->where('ug_id', $ugId)
                ->where('ativo', true)
                ->orderBy('nivel')
                ->orderBy('nome')
                ->get(['id', 'parent_id', 'nivel', 'codigo', 'nome']);
        }

        return Inertia::render('GED/Oficios/Create', [
            'variaveis'    => Oficio::variaveisParaEditor(),
            'rascunho'     => \App\Support\Rascunhos::abrir(request()->integer('rascunho') ?: null, Auth::user(), 'oficio'),
            'autorizacoes' => EmNomeDe::autorizacoes(),
            'modelos' => $modelos,
            'setores' => $setores,
        ]);
    }

    /**
     * Livro de Controle - listagem enxuta para auditoria
     */
    public function controle(Request $request): Response
    {
        $ugId = (int) ($request->session()->get('ug_id') ?? 0);
        $ano   = $request->input('ano');
        $busca = $request->input('busca');

        $query = \App\Models\Processo\Oficio::query()
            ->when($ugId, fn ($q) => $q->where('ug_id', $ugId))
            ->whereNotIn('status', ['rascunho']);

        if ($ano) {
            $query->whereYear('enviado_em', (int) $ano);
        }
        if ($busca) {
            $b = "%{$busca}%";
            $query->where(function ($q) use ($b) {
                $q->where('assunto', 'ilike', $b)
                  ->orWhere('destinatario_nome', 'ilike', $b)
                  ->orWhere('destinatario_orgao', 'ilike', $b)
                  ->orWhere('numero', 'ilike', $b);
            });
        }

        $oficios = $query->select([
                'id', 'numero', 'assunto', 'destinatario_nome', 'destinatario_orgao',
                'status', 'enviado_em', 'lido_em', 'created_at',
            ])
            ->orderByDesc('enviado_em')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $anos = \App\Models\Processo\Oficio::query()
            ->when($ugId, fn ($q) => $q->where('ug_id', $ugId))
            ->whereNotNull('enviado_em')
            ->selectRaw('EXTRACT(YEAR FROM enviado_em)::int AS ano')
            ->distinct()
            ->orderByDesc('ano')
            ->pluck('ano');

        return Inertia::render('GED/Oficios/Controle', [
            'oficios' => $oficios,
            'anos'    => $anos,
            'filtros' => ['ano' => $ano, 'busca' => $busca],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'assunto'             => ['required', 'string', 'max:255'],
            'conteudo'            => ['required', 'string'],
            'destinatario_nome'   => ['required', 'string', 'max:255'],
            'destinatario_email'  => ['nullable', 'email', 'max:255'],
            'destinatario_cargo'  => ['nullable', 'string', 'max:255'],
            'destinatario_orgao'  => ['nullable', 'string', 'max:255'],
            'setor_origem'        => ['nullable', 'string', 'max:150'],
            'modo_envio'          => ['nullable', 'string', 'in:eletronico,fisico'],
            'data_envio'          => ['nullable', 'date'],
            'files'               => ['nullable', 'array'],
            'files.*'             => ['file', 'max:51200'],
        ]);

        // Modo de envio: 'fisico' (registro no livro) ou 'eletronico' (envio por email)
        // Se nao informado: deduz pelo email — se preenchido = eletronico, senao = fisico
        $modoEnvio = $request->input('modo_envio')
            ?? ($request->filled('destinatario_email') ? 'eletronico' : 'fisico');

        if ($modoEnvio === 'eletronico' && ! $request->filled('destinatario_email')) {
            return redirect()->back()->withErrors(['destinatario_email' => 'Informe o e-mail do destinatário para o envio eletrônico.'])->withInput();
        }

        // Em nome de outro servidor, por contrassenha (TR 4.18)
        $contrassenha = \App\Support\EmNomeDe::conferir($request);

        try {
            DB::beginTransaction();
            $origem = \App\Support\EmNomeDe::consumir($contrassenha);

            $year = date('Y');

            $lastNum = Oficio::whereYear('created_at', $year)
                ->max(DB::raw("CAST(SPLIT_PART(numero, '/', 2) AS INTEGER)"));

            $nextNum = ($lastNum ?? 0) + 1;
            $numero  = 'OF-' . $year . '/' . str_pad((string) $nextNum, 6, '0', STR_PAD_LEFT);

            $dataEnvio = $request->filled('data_envio')
                ? \Carbon\Carbon::parse($request->input('data_envio'))
                : now();

            $oficio = Oficio::create([
                'numero'             => $numero,
                'assunto'            => $request->input('assunto'),
                'conteudo'           => $request->input('conteudo'),
                'remetente_id'       => $origem['remetente_id'],
                'digitador_id'       => $origem['digitador_id'],
                'contrassenha_id'    => $origem['contrassenha_id'],
                'setor_origem'       => $request->input('setor_origem'),
                'destinatario_nome'  => $request->input('destinatario_nome'),
                'destinatario_email' => $request->input('destinatario_email') ?? '',
                'destinatario_cargo' => $request->input('destinatario_cargo'),
                'destinatario_orgao' => $request->input('destinatario_orgao'),
                'status'             => 'enviado',
                'enviado_em'         => $dataEnvio,
                'modo_envio'         => $modoEnvio,
            ]);

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store(\App\Tenant\TenantStorage::pasta('oficios'), 'documentos');

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

            // Anexos guardados no rascunho automático (TR 4.16.8)
            foreach (\App\Support\Rascunhos::entregarAnexos($request->integer('rascunho_id') ?: null, 'oficios', Auth::user()) as $anexo) {
                OficioAnexo::create(['oficio_id' => $oficio->id, 'solicitar_assinatura' => false,] + $anexo);
            }

            Notificacao::create([
                'usuario_id'      => Auth::id(),
                'tipo'            => 'oficio_enviado',
                'titulo'          => 'Oficio enviado',
                'mensagem'        => "Ofício {$numero} - {$oficio->assunto} enviado para {$oficio->destinatario_nome}.",
                'referencia_tipo' => 'oficio',
                'referencia_id'   => $oficio->id,
                'lida'            => false,
            ]);
            \App\Support\EmNomeDe::avisarAutorizador($origem, 'oficio', $oficio->id, "Ofício {$numero} — {$oficio->assunto}");

            DB::commit();
            \App\Support\Rascunhos::concluir($request->integer('rascunho_id') ?: null, Auth::user());
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao enviar ofício: ' . $e->getMessage());
        }

        if ($modoEnvio === 'fisico') {
            return redirect("/oficios/{$oficio->id}")->with('success', "Ofício {$numero} registrado no livro de controle.");
        }

        // Depois do commit: falha no e-mail não desfaz o ofício; fica registrada e pode ser reenviada.
        $servico = app(\App\Services\OficioEmailService::class);
        if ($servico->enviar($oficio)) {
            return redirect("/oficios/{$oficio->id}")->with('success', "Ofício {$numero} enviado por e-mail para {$oficio->destinatario_email}.");
        }

        return redirect("/oficios/{$oficio->id}")->with('error', "Ofício {$numero} registrado, mas o e-mail não foi enviado: {$oficio->email_falha_motivo}");
    }

    /** Reenvia o e-mail do ofício eletrônico (após falha ou a pedido do remetente). */
    public function reenviarEmail(Request $request, $id)
    {
        $oficio = Oficio::findOrFail($id);
        if (! $oficio->eDoUsuario(Auth::id())) {
            abort(403, 'Você não tem permissão para reenviar este ofício.');
        }

        $data = $request->validate(['destinatario_email' => ['nullable', 'email', 'max:255']]);
        if (! empty($data['destinatario_email']) && $data['destinatario_email'] !== $oficio->destinatario_email) {
            $oficio->forceFill(['destinatario_email' => $data['destinatario_email']])->save();
        }
        if ($oficio->modo_envio === 'fisico' || ! $oficio->modo_envio) {
            $oficio->forceFill(['modo_envio' => 'eletronico'])->save();
        }

        return app(\App\Services\OficioEmailService::class)->enviar($oficio)
            ? redirect()->back()->with('success', "E-mail do ofício {$oficio->numero} enviado para {$oficio->destinatario_email}.")
            : redirect()->back()->with('error', "O e-mail não foi enviado: {$oficio->email_falha_motivo}");
    }

    /** Download de anexo (a rota que a tela usava não existia). Mesma regra de ver o ofício. */
    public function downloadAnexo($id, $anexoId)
    {
        $oficio = Oficio::findOrFail($id);
        if (! $oficio->eDoUsuario(Auth::id())) {
            abort(403);
        }
        $anexo = \App\Models\Processo\OficioAnexo::where('oficio_id', $oficio->id)->findOrFail($anexoId);

        return \App\Support\Anexos::baixar($anexo->arquivo_path, $anexo->nome);
    }

    public function show($id): Response
    {
        $oficio = Oficio::with([
            'remetente', 'digitador:id,name',
            'anexos.enviadoPor',
            'respostas.usuario',
        ])->findOrFail($id);

        if (! $oficio->eDoUsuario(Auth::id())) {
            abort(403, 'Você não tem permissão para visualizar este ofício.');
        }

        \App\Support\Acessos::registrar('oficio', $oficio->id, Auth::user());

        return Inertia::render('GED/Oficios/Show', [
            'classificacao' => \App\Support\Classificacao::doItem('oficio', $oficio->id),
            'oficio'        => $oficio,
            'conteudo_html' => $oficio->conteudoFinal(),
            'acessos'       => \App\Support\Acessos::de('oficio', $oficio->id),
        ]);
    }

    public function responder(Request $request, $id)
    {
        $request->validate([
            'conteudo' => ['required', 'string'],
        ]);

        $oficio = Oficio::findOrFail($id);

        if (! $oficio->eDoUsuario(Auth::id())) {
            abort(403, 'Você não tem permissão para responder a este ofício.');
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

        if (! $oficio->eDoUsuario(Auth::id())) {
            abort(403, 'Você não tem permissão para arquivar este ofício.');
        }

        $oficio->update([
            'status'       => 'arquivado',
            'arquivado_em' => now(),
        ]);

        return redirect()->back()->with('success', 'Ofício arquivado com sucesso.');
    }

    public function arquivarNoGed(Request $request, $id)
    {
        $request->validate(['pasta_id' => ['required', 'integer', 'exists:ged_pastas,id']]);

        $oficio = Oficio::with(['remetente'])->findOrFail($id);

        $pasta = DB::table('ged_pastas')->where('id', $request->input('pasta_id'))->first();
        if (! $pasta || $pasta->ug_id !== $oficio->ug_id) {
            return redirect()->back()->with('error', 'A pasta selecionada não pertence a UG deste ofício.');
        }

        try {
            DB::beginTransaction();

            if ($oficio->documento_id) {
                $documento = \App\Models\Documento::find($oficio->documento_id);
                if ($documento) {
                    $documento->update(['pasta_id' => (int) $request->input('pasta_id'), 'status' => 'arquivado']);
                    DB::commit();
                    return redirect()->back()->with('success', "Ofício arquivado na pasta \"{$pasta->nome}\".");
                }
            }

            $pdf = Relatorio::pdf('pdf.oficio', [
                'oficio'    => $oficio,
                'qrCodeUrl' => url("/oficios/verificar/{$oficio->qr_code_token}"),
                'ug'        => \App\Models\Ug::find($oficio->ug_id),
            ]);
            $pdfBytes = $pdf->output();

            $filename = 'oficio-' . str_replace(['/', '\\'], '-', $oficio->numero) . '.pdf';
            $path = \App\Tenant\TenantStorage::pasta('documentos') . '/' . date('Y/m') . '/' . uniqid() . '-' . $filename;
            \Illuminate\Support\Facades\Storage::disk('documentos')->put($path, $pdfBytes);

            $textoPesquisavel = collect([
                'Oficio', $oficio->numero, $oficio->assunto, html_entity_decode(strip_tags($oficio->conteudoFinal())),
                $oficio->setor_origem, $oficio->remetente?->name,
                $oficio->destinatario_nome, $oficio->destinatario_orgao, $oficio->destinatario_email,
            ])->filter()->implode(' | ');

            $documento = \App\Models\Documento::create([
                'nome'              => 'Oficio ' . $oficio->numero,
                'descricao'         => $oficio->assunto,
                'tipo_documental_id'=> \App\Support\TiposDocumentais::id('Oficio'),
                'pasta_id'          => (int) $request->input('pasta_id'),
                'versao_atual'      => 1,
                'tamanho'           => strlen($pdfBytes),
                'mime_type'         => 'application/pdf',
                'autor_id'          => Auth::id(),
                'status'            => 'arquivado',
                'ocr_texto'         => $textoPesquisavel,
            ]);

            \App\Models\Versao::create([
                'documento_id' => $documento->id,
                'versao'       => 1,
                'arquivo_path' => $path,
                'tamanho'      => strlen($pdfBytes),
                'hash_sha256'  => hash('sha256', $pdfBytes),
                'autor_id'     => Auth::id(),
                'comentario'   => 'Arquivado automaticamente do GPE Fluxos e Processos',
            ]);

            $oficio->update(['documento_id' => $documento->id]);

            DB::commit();
            return redirect()->back()->with('success', "Ofício arquivado na pasta \"{$pasta->nome}\".");
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

        if (! $oficio->eDoUsuario(Auth::id())) {
            abort(403, 'Você não tem permissão para baixar este ofício.');
        }

        $qrCodeUrl = url("/oficios/verificar/{$oficio->qr_code_token}");

        $data = [
            'oficio'    => $oficio,
            'qrCodeUrl' => $qrCodeUrl,
            'ug'        => \App\Models\Ug::find($oficio->ug_id),
        ];

        $pdf = Relatorio::pdf('pdf.oficio', $data);

        $filename = "oficio-{$oficio->numero}.pdf";
        $filename = str_replace('/', '-', $filename);

        return $pdf->download($filename);
    }

    /**
     * Ofício pelo token de rastreio (páginas públicas). Quem abre o link pode estar logado no GED em
     * outra UG, ou em nenhuma: a autorização é o próprio token, não o escopo da sessão.
     */
    private function porToken(string $token): ?Oficio
    {
        if (strlen($token) < 32) {
            return null;
        }

        return Oficio::withoutGlobalScopes()->where('rastreio_token', $token)->first();
    }

    /** Página do destinatário: registra a leitura e mostra o ofício, com o PDF para baixar. */
    public function rastrear($token)
    {
        $oficio = $this->porToken($token);

        if (! $oficio) {
            return Inertia::render('GED/Oficios/Rastreio', [
                'valido' => false,
                'oficio' => null,
            ]);
        }

        app(\App\Services\OficioEmailService::class)->registrarLeitura($oficio);
        // Acesso do externo pelo link (TR 4.14.3)
        \App\Support\Acessos::registrar('oficio', $oficio->id, null, ($oficio->destinatario_nome ?: 'Destinatário') . ' (link do ofício)');
        $ug =\App\Models\Ug::withoutGlobalScopes()->find($oficio->ug_id);

        return Inertia::render('GED/Oficios/Rastreio', [
            'valido' => true,
            'oficio' => [
                'numero'       => $oficio->numero,
                'assunto'      => $oficio->assunto,
                'orgao'        => $ug?->nome,
                'destinatario' => $oficio->destinatario_nome,
                'enviado_em'   => $oficio->enviado_em?->format('d/m/Y'),
                'lido_em'      => $oficio->lido_em?->format('d/m/Y H:i:s'),
                'conteudo'     => $oficio->conteudoFinal(),
                'pdf_url'      => url("/oficios/rastrear/{$token}/pdf"),
            ],
        ]);
    }

    /** PDF do ofício para o destinatário (link da página de rastreio). */
    public function pdfPublico($token)
    {
        $oficio = $this->porToken($token);
        abort_unless($oficio, 404);

        $arquivo = 'oficio-' . str_replace(['/', '\\'], '-', $oficio->numero) . '.pdf';

        return response(app(\App\Services\OficioEmailService::class)->pdf($oficio), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$arquivo}\"",
        ]);
    }

    /**
     * Imagem de 1x1 do e-mail: carregada quando o destinatário abre a mensagem. Leitores que bloqueiam
     * imagens não a carregam (aí a abertura só aparece no acesso ao link); responde a imagem mesmo com
     * token inválido, para não revelar quais tokens existem.
     */
    public function pixelAbertura($token)
    {
        if ($oficio = $this->porToken($token)) {
            app(\App\Services\OficioEmailService::class)->registrarAbertura($oficio);
        }

        return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 200, [
            'Content-Type'  => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }
}
