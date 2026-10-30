<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notificacao;
use App\Models\Processo\Circular;
use App\Models\Processo\CircularAnexo;
use App\Models\Processo\CircularDestinatario;
use App\Models\User;
use App\Support\Relatorio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use App\Support\EmNomeDe;

class CircularController extends Controller
{
    public function index(Request $request): Response
    {
        $tipo = $request->input('tipo', 'recebidas');

        if ($tipo === 'enviadas') {
            $query = Circular::where(fn ($q) => $q->where('remetente_id', Auth::id())->orWhere('digitador_id', Auth::id()));
        } else {
            // Recebidas; as que arquivei para mim só com ?arquivadas=1 (TR 4.46.9)
            $query = Circular::whereHas('destinatarios', fn ($q) => $q->where('usuario_id', Auth::id())
                ->when($request->boolean('arquivadas'), fn ($w) => $w->whereNotNull('arquivado_em'), fn ($w) => $w->whereNull('arquivado_em')));
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
            'filters'    => ['tipo' => $tipo, 'arquivadas' => $request->boolean('arquivadas')],
        ]);
    }

    public function create(): Response
    {
        $usuarios = User::where('id', '!=', Auth::id())->where('tipo', 'interno')->whereNull('suspenso_em')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('GED/Circulares/Create', [
            'variaveis'    => Circular::variaveisParaEditor(),
            'rascunho'     => \App\Support\Rascunhos::abrir(request()->integer('rascunho') ?: null, Auth::user(), 'circular'),
            'autorizacoes' => EmNomeDe::autorizacoes(),
            'usuarios' => $usuarios,
            'unidades' => $this->unidades(),
            'opcoes_respostas' => \App\Support\Circulares::RESPOSTAS,
        ]);
    }

    /** Setores ativos da UG, em ordem de árvore, para escolher o destino (TR 4.46.3). */
    private function unidades(): array
    {
        $nos = \App\Models\UgOrganograma::where('ug_id', session('ug_id'))->where('ativo', true)->orderBy('codigo')->orderBy('nome')->get(['id', 'nome', 'sigla', 'nivel', 'parent_id']);
        $porPai = $nos->groupBy(fn ($n) => $n->parent_id ?? 0);
        $saida = [];
        $visitar = function (int $pai) use (&$visitar, &$saida, $porPai) {
            foreach ($porPai[$pai] ?? [] as $n) {
                $saida[] = ['id' => $n->id, 'nome' => $n->sigla ? "{$n->nome} ({$n->sigla})" : $n->nome, 'nivel' => $n->nivel];
                $visitar($n->id);
            }
        };
        $visitar(0);

        return $saida;
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
            'destino_setores.*'     => ['integer', 'exists:ug_organograma,id'],
            'setor_origem'          => ['nullable', 'string', 'max:150'],
            'data_arquivamento_auto'=> ['nullable', 'date', 'after_or_equal:today'],
            'files'                 => ['nullable', 'array'],
            'files.*'               => ['file', 'max:51200'],
            'urgente'               => ['nullable', 'boolean'],
            'respostas'             => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Support\Circulares::RESPOSTAS))],
        ]);
        $unidadesDestino = array_map('intval', (array) $request->input('destino_setores', []));

        // Em nome de outro servidor, por contrassenha (TR 4.18)
        $contrassenha = \App\Support\EmNomeDe::conferir($request);

        try {
            DB::beginTransaction();
            $origem = \App\Support\EmNomeDe::consumir($contrassenha);

            $year = date('Y');

            $lastNum = Circular::whereYear('created_at', $year)
                ->max(DB::raw("CAST(SPLIT_PART(numero, '/', 2) AS INTEGER)"));

            $nextNum = ($lastNum ?? 0) + 1;
            $numero  = 'CIR-' . $year . '/' . str_pad((string) $nextNum, 6, '0', STR_PAD_LEFT);

            $circular = Circular::create([
                'numero'                => $numero,
                'assunto'               => $request->input('assunto'),
                'conteudo'              => $request->input('conteudo'),
                'remetente_id'          => $origem['remetente_id'],
                'digitador_id'          => $origem['digitador_id'],
                'contrassenha_id'       => $origem['contrassenha_id'],
                'setor_origem'          => $request->input('setor_origem'),
                'destino_tipo'          => $request->input('destino_tipo'),
                // Nomes para exibir e imprimir; ids para encaminhar e conferir
                'destino_setores'       => $unidadesDestino ? \App\Models\UgOrganograma::whereIn('id', $unidadesDestino)->pluck('nome')->all() : null,
                'destino_unidades'      => $unidadesDestino ?: null,
                'urgente'               => $request->boolean('urgente'),
                'respostas'             => $request->input('respostas'),
                'status'                => 'enviado',
                'enviado_em'            => now(),
                'data_arquivamento_auto'=> $request->input('data_arquivamento_auto'),
            ]);
            \App\Support\EmNomeDe::avisarAutorizador($origem, 'circular', $circular->id, "Circular {$numero} — {$circular->assunto}");

            // Quem recebe: toda a UG, os setores escolhidos (com os subsetores) ou as pessoas (TR 4.46.3)
            $userIds = \App\Support\Circulares::destinatarios($request->input('destino_tipo'), $unidadesDestino, (array) $request->input('destinatarios', []), (int) $origem['remetente_id']);
            if ($userIds->isEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['destino_setores' => 'Ninguém atende os setores escolhidos.']);
            }

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store(\App\Tenant\TenantStorage::pasta('circulares'), 'documentos');

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

            // Anexos guardados no rascunho automático (TR 4.16.8)
            foreach (\App\Support\Rascunhos::entregarAnexos($request->integer('rascunho_id') ?: null, 'circulares', Auth::user()) as $anexo) {
                CircularAnexo::create(['circular_id' => $circular->id] + $anexo);
            }

            \App\Support\Circulares::incluir($circular, $userIds, 'Nova circular recebida');

            DB::commit();
            \App\Support\Rascunhos::concluir($request->integer('rascunho_id') ?: null, Auth::user());
            // @ menções, # citações e ! assinaturas do texto (TR 4.16.13–4.16.18)
            if ($avisos = \App\Support\Marcadores::aposSalvar('circular', $circular->id, $circular->conteudo, Auth::user(), $circular)) {
                session()->flash('warning', implode(' ', $avisos));
            }

            return redirect("/circulares/{$circular->id}")->with('success', 'Circular enviada com sucesso. Número: ' . $numero);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao enviar circular: ' . $e->getMessage());
        }
    }

    /** Download de anexo (a rota que a tela usava não existia). Mesma regra de ver a circular. */
    public function downloadAnexo($id, $anexoId)
    {
        $circular = Circular::with('destinatarios')->findOrFail($id);
        $userId = Auth::id();
        if ($circular->remetente_id !== $userId && ! $circular->destinatarios->contains('usuario_id', $userId)) {
            abort(403);
        }
        $anexo = \App\Models\Processo\CircularAnexo::where('circular_id', $circular->id)->findOrFail($anexoId);

        return \App\Support\Anexos::baixar($anexo->arquivo_path, $anexo->nome);
    }

    public function show($id): Response
    {
        $circular = Circular::with([
            'remetente', 'digitador:id,name',
            'destinatarios.usuario',
            'anexos.enviadoPor',
        ])->findOrFail($id);

        $userId = Auth::id();
        $isRemetente    = \App\Support\Circulares::ehRemetente($circular, $userId);
        $isDestinatario = $circular->destinatarios->contains('usuario_id', $userId);

        // Mencionado no texto (@) ou chamado a assinar (!) também vê — TR 4.16.16
        if (! $isRemetente && ! $isDestinatario && ! \App\Support\Marcadores::temAcesso('circular', $circular->id, $userId)) {
            abort(403, 'Você não tem permissão para visualizar esta circular.');
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

        \App\Support\Acessos::registrar('circular', $circular->id, Auth::user());

        return Inertia::render('GED/Circulares/Show', [
            'classificacao' => \App\Support\Classificacao::doItem('circular', $circular->id),
            'circular' => $circular,
            'conteudo_html' => $circular->conteudoFinal(),
            'marcadores'      => ['citado_em' => \App\Support\Marcadores::citadoEm('circular', $circular->id), 'pessoas' => \App\Support\Marcadores::doDocumento('circular', $circular->id)],
            'acessos'  => \App\Support\Acessos::de('circular', $circular->id),
            'respostas_lista' => \App\Support\Circulares::respostasVisiveis($circular, $userId)->map(fn ($r) => [
                'id' => $r->id, 'autor' => $r->usuario?->name, 'conteudo_html' => $r->conteudo_html,
                'em' => $r->created_at?->toIso8601String(), 'meu' => $r->usuario_id === $userId,
            ]),
            'eu' => [
                'remetente'    => $isRemetente,
                'destinatario' => $isDestinatario,
                'arquivada'    => (bool) $circular->destinatarios->firstWhere('usuario_id', $userId)?->arquivado_em,
                'silenciada'   => (bool) $circular->destinatarios->firstWhere('usuario_id', $userId)?->silenciado,
                'pode_responder' => $circular->respostas !== 'nenhuma' && $circular->status !== 'arquivado' && ($isRemetente || $isDestinatario),
            ],
            'unidades' => $isRemetente ? $this->unidades() : [],
        ]);
    }

    /** Resposta à circular (TR 4.46.6): abertas, todos veem; restritas, só o remetente. */
    public function responder(Request $request, int $id)
    {
        $d = $request->validate(['conteudo' => ['required', 'string']]);
        $circular = Circular::findOrFail($id);
        $user = Auth::user();
        abort_if($circular->respostas === 'nenhuma', 422, 'Esta circular é meramente informativa: não recebe respostas.');
        abort_if($circular->status === 'arquivado', 422, 'Circular arquivada.');
        abort_unless(\App\Support\Circulares::ehRemetente($circular, $user->id) || $circular->destinatarios()->where('usuario_id', $user->id)->exists(), 403);
        if (trim(strip_tags($d['conteudo'], '<img>')) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['conteudo' => 'Escreva a resposta.']);
        }
        $circular->respostasLista()->create(['usuario_id' => $user->id, 'conteudo' => \App\Support\HtmlSeguro::limpar($d['conteudo'])]);
        \App\Support\Circulares::avisarResposta($circular, $user);

        return back()->with('success', $circular->respostas === 'restritas' ? 'Resposta enviada ao remetente.' : 'Resposta publicada.');
    }

    /** Arquivar só para mim / desarquivar (TR 4.46.9). */
    public function arquivarParaMim(int $id)
    {
        $dest = CircularDestinatario::where('circular_id', $id)->where('usuario_id', Auth::id())->firstOrFail();
        $dest->update(['arquivado_em' => $dest->arquivado_em ? null : now()]);

        return back()->with('success', $dest->arquivado_em ? 'Circular arquivada para você.' : 'Circular de volta às recebidas.');
    }

    /** Silenciar os avisos de respostas (TR 4.46.9). */
    public function silenciar(int $id)
    {
        $dest = CircularDestinatario::where('circular_id', $id)->where('usuario_id', Auth::id())->firstOrFail();
        $dest->update(['silenciado' => ! $dest->silenciado]);

        return back()->with('success', $dest->silenciado ? 'Você não será avisado das respostas.' : 'Avisos de respostas ligados.');
    }

    /** Encaminhar a setores que não estavam na circular (TR 4.46.10) — só quem enviou. */
    public function encaminhar(Request $request, int $id)
    {
        $d = $request->validate(['setores' => ['required', 'array', 'min:1'], 'setores.*' => ['integer', 'exists:ug_organograma,id']]);
        $circular = Circular::findOrFail($id);
        abort_unless(\App\Support\Circulares::ehRemetente($circular, Auth::id()), 403, 'Só quem enviou encaminha a circular.');
        $ids = \App\Support\Circulares::destinatarios('setores', $d['setores'], [], (int) $circular->remetente_id);
        $n = \App\Support\Circulares::incluir($circular, $ids, 'Circular encaminhada ao seu setor');
        $nomes = \App\Models\UgOrganograma::whereIn('id', $d['setores'])->pluck('nome')->all();
        $circular->update([
            'destino_setores'  => array_values(array_unique(array_merge((array) $circular->destino_setores, $nomes))),
            'destino_unidades' => array_values(array_unique(array_merge((array) $circular->destino_unidades, array_map('intval', $d['setores'])))),
        ]);

        return back()->with('success', "Circular encaminhada: {$n} servidor(es) novo(s).");
    }

    /** Arquivar a circular para todos — só quem enviou (antes qualquer um arquivava). */
    public function arquivar($id)
    {
        $circular = Circular::findOrFail($id);
        abort_unless(\App\Support\Circulares::ehRemetente($circular, Auth::id()), 403, 'Só quem enviou arquiva a circular para todos.');

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
            return redirect()->back()->with('error', 'A pasta selecionada não pertence a UG desta circular.');
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

            $pdf = Relatorio::pdf('pdf.circular', [
                'circular'  => $circular,
                'qrCodeUrl' => url("/circulares/verificar/{$circular->qr_code_token}"),
                'ug'        => \App\Models\Ug::find($circular->ug_id),
            ]);
            $pdfBytes = $pdf->output();

            $filename = 'circular-' . str_replace(['/', '\\'], '-', $circular->numero) . '.pdf';
            $path = \App\Tenant\TenantStorage::pasta('documentos') . '/' . date('Y/m') . '/' . uniqid() . '-' . $filename;
            \Illuminate\Support\Facades\Storage::disk('documentos')->put($path, $pdfBytes);

            $textoPesquisavel = collect([
                'Circular', $circular->numero, $circular->assunto, \App\Support\Variaveis::textoPuro($circular->conteudoFinal()),
                $circular->setor_origem, $circular->remetente?->name,
            ])->filter()->implode(' | ');

            $documento = \App\Models\Documento::create([
                'nome'              => 'Circular ' . $circular->numero,
                'descricao'         => $circular->assunto,
                'tipo_documental_id'=> \App\Support\TiposDocumentais::id('Circular'),
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

        $pdf = Relatorio::pdf('pdf.circular', $data);

        $filename = "circular-{$circular->numero}.pdf";
        $filename = str_replace('/', '-', $filename);

        return $pdf->download($filename);
    }
}
