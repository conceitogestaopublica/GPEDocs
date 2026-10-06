<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Documento;
use App\Models\User;
use App\Models\Versao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DocumentoController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Documento::with(['tipoDocumental', 'autor', 'pasta', 'metadados'])
            ->whereNull('deleted_at');

        // Filtro por favoritos, recentes, populares
        $filtro = $request->input('filtro');
        if ($filtro === 'favoritos') {
            $query->whereIn('id', Auth::user()->favoritos()->pluck('documento_id'));
        } elseif ($filtro === 'recentes') {
            $query->where('autor_id', Auth::id())->orderByDesc('updated_at');
        } elseif ($filtro === 'populares') {
            $query->where('autor_id', Auth::id())->orderByDesc('updated_at');
        } elseif ($filtro === 'arquivados') {
            $query->where('status', 'arquivado');
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'ilike', "%{$search}%")
                  ->orWhere('descricao', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('tipo')) {
            $query->where('tipo_documental_id', $request->input('tipo'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('pasta_id')) {
            $query->where('pasta_id', $request->input('pasta_id'));
        }

        $documentos = $query->orderByDesc('updated_at')->paginate(20)->withQueryString();

        // Marcar favoritos do usuario
        $favoritoIds = Auth::user()->favoritos()->pluck('documento_id')->toArray();

        $usuarios = User::where('id', '!=', Auth::id())->orderBy('name')->get(['id', 'name', 'email']);

        return Inertia::render('GED/Documentos/Index', [
            'documentos'   => $documentos,
            'filters'      => $request->only(['search', 'tipo', 'status', 'pasta_id', 'filtro']),
            'favorito_ids' => $favoritoIds,
            'usuarios'     => $usuarios,
        ]);
    }

    public function show($id): Response
    {
        $documento = Documento::with([
            'versoes.autor',
            'metadados',
            'tags',
            'auditLogs.usuario',
            'compartilhamentos',
            'fluxoInstancias.fluxo',
            'tipoDocumental',
            'autor',
            'pasta',
            'solicitacoesAssinatura.assinaturas.signatario',
            'solicitacoesAssinatura.assinaturas.certificado',
            'solicitacoesAssinatura.solicitante',
        ])->findOrFail($id);

        $isFavorito = Auth::user()->favoritos()->where('documento_id', $id)->exists();
        $usuarios = User::where('id', '!=', Auth::id())->orderBy('name')->get(['id', 'name', 'email']);

        // Info da assinatura ICP (a mais recente) — para banner "vendo versao assinada"
        $assinada = \App\Models\Assinatura::where('documento_id', $documento->id)
            ->where('status', 'assinado')
            ->whereNotNull('arquivo_assinado_path')
            ->orderByDesc('assinado_em')
            ->first();

        $versaoAssinada = $assinada ? [
            'tipo'         => $assinada->tipo_assinatura,
            'assinado_em'  => $assinada->assinado_em?->format('d/m/Y H:i'),
            'cpf'          => $assinada->cpf_signatario,
            'assinatura_id'=> $assinada->id,
        ] : null;

        $this->registrarVisualizacao($documento);
        $documento->load('auditLogs.usuario'); // inclui a visualização que acabou de ser registrada

        // A tela lê versões, metadados, auditoria e etiquetas no nível de cima — antes iam
        // só aninhados em `documento` e as abas ficavam sempre vazias.
        $versaoAtualNumero = (int) $documento->versoes->max('versao');

        return Inertia::render('GED/Documentos/Show', [
            'documento'        => $documento->setAttribute('tipo_nome', $documento->tipoDocumental?->nome)
                                            ->setAttribute('autor_nome', $documento->autor?->name),
            'versoes'          => $documento->versoes->sortByDesc('versao')->values()->map(fn ($v) => [
                'id'         => $v->id,
                'versao'     => $v->versao,
                'tamanho'    => $v->tamanho,
                'hash'       => $v->hash_sha256,
                'comentario' => $v->comentario,
                'autor_nome' => $v->autor?->name,
                'created_at' => $v->created_at,
                'atual'      => $v->versao === $versaoAtualNumero,
            ]),
            'metadados'        => $documento->metadados,
            'tags'             => $documento->tags,
            'audit_logs'       => $documento->auditLogs->sortByDesc('created_at')->values()->map(fn ($l) => [
                'id'           => $l->id,
                'acao'         => $l->acao,
                'detalhes'     => $l->detalhes,
                'ip'           => $l->ip,
                'created_at'   => $l->created_at,
                'usuario_nome' => $l->usuario?->name,
            ]),
            'pode_nova_versao' => ! $this->assinaturaEmCurso($documento) && $documento->status !== 'cancelado',
            'is_favorito'      => $isFavorito,
            'usuarios'         => $usuarios,
            'versao_assinada'  => $versaoAssinada,
        ]);
    }

    /**
     * Registra a abertura da ficha — é o que alimenta "Recentes" e "Mais acessados", que
     * contam a ação `visualizacao` e ficavam sempre vazios. A mesma pessoa reabrindo em
     * até 10 minutos não gera novo registro.
     */
    private function registrarVisualizacao(Documento $documento): void
    {
        $recente = AuditLog::where('documento_id', $documento->id)
            ->where('usuario_id', Auth::id())
            ->where('acao', 'visualizacao')
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        if (! $recente) {
            AuditLog::create([
                'documento_id' => $documento->id,
                'usuario_id'   => Auth::id(),
                'acao'         => 'visualizacao',
                'detalhes'     => ['versao' => $documento->versao_atual],
                'ip'           => request()->ip(),
                'user_agent'   => request()->userAgent(),
            ]);
        }
    }

    /** Trocar o arquivo durante a coleta de assinaturas invalidaria o que já foi assinado. */
    private function assinaturaEmCurso(Documento $documento): bool
    {
        return \App\Models\SolicitacaoAssinatura::where('documento_id', $documento->id)
            ->whereIn('status', ['pendente', 'em_andamento'])
            ->exists();
    }

    /** Nova versão pela interface (antes só a integração externa criava versões). */
    public function novaVersao(Request $request, $id)
    {
        $request->validate([
            'arquivo'    => ['required', 'file', 'max:51200'],
            'comentario' => ['nullable', 'string', 'max:500'],
        ]);

        $documento = Documento::findOrFail($id);
        if ($documento->status === 'cancelado') {
            return back()->with('error', 'Documento cancelado: não recebe novas versões.');
        }
        if ($this->assinaturaEmCurso($documento)) {
            return back()->with('error', 'Há assinatura em andamento: conclua ou cancele a solicitação antes de enviar outra versão.');
        }

        $file = $request->file('arquivo');
        $path = $file->store(\App\Tenant\TenantStorage::pasta('documentos'), 'documentos');
        $ocrTexto = $file->getMimeType() === 'application/pdf'
            ? (new \App\Services\PdfTextExtractor())->extrair($file->getRealPath())
            : null;

        $this->gravarVersao($documento, $path, $file->getSize(), $file->getMimeType(),
            hash_file('sha256', $file->getRealPath()), $request->input('comentario') ?: 'Nova versão', $ocrTexto, $request);

        return back()->with('success', 'Nova versão registrada.');
    }

    /** Restaura uma versão antiga criando uma versão nova com o mesmo arquivo — o histórico não muda. */
    public function restaurarVersao(Request $request, $id, $versao)
    {
        $documento = Documento::findOrFail($id);
        if ($documento->status === 'cancelado' || $this->assinaturaEmCurso($documento)) {
            return back()->with('error', 'Não é possível restaurar versão com assinatura em andamento ou em documento cancelado.');
        }

        $antiga = Versao::where('documento_id', $documento->id)->where('versao', (int) $versao)->firstOrFail();
        $disk = Storage::disk('documentos');
        if (! $disk->exists($antiga->arquivo_path)) {
            return back()->with('error', 'Arquivo da versão não encontrado.');
        }

        $ocrTexto = $documento->mime_type === 'application/pdf'
            ? (new \App\Services\PdfTextExtractor())->extrair($disk->path($antiga->arquivo_path))
            : null;

        $this->gravarVersao($documento, $antiga->arquivo_path, $antiga->tamanho, $documento->mime_type,
            $antiga->hash_sha256, "Restauração da versão {$antiga->versao}", $ocrTexto, $request);

        return back()->with('success', "Versão {$antiga->versao} restaurada como versão atual.");
    }

    /** Download de uma versão específica (o download comum entrega só a vigente). */
    public function downloadVersao(Request $request, $id, $versao)
    {
        $documento = Documento::findOrFail($id);
        if ($documento->status === 'cancelado') {
            return back()->with('error', 'Documento cancelado pela origem. Download não disponível.');
        }

        $v = Versao::where('documento_id', $documento->id)->where('versao', (int) $versao)->firstOrFail();
        if (! Storage::disk('documentos')->exists($v->arquivo_path)) {
            return back()->with('error', 'Arquivo da versão não encontrado.');
        }

        AuditLog::create([
            'documento_id' => $documento->id,
            'usuario_id'   => Auth::id(),
            'acao'         => 'download',
            'detalhes'     => ['versao' => $v->versao],
            'ip'           => $request->ip(),
            'user_agent'   => $request->userAgent(),
        ]);

        $ext = pathinfo($v->arquivo_path, PATHINFO_EXTENSION);
        $nome = preg_replace('/[^\w\-. ]/u', '-', $documento->nome) . "-v{$v->versao}" . ($ext ? ".{$ext}" : '');

        return Storage::disk('documentos')->download($v->arquivo_path, $nome);
    }

    private function gravarVersao(Documento $documento, string $path, int $tamanho, ?string $mime, string $hash, string $comentario, ?string $ocrTexto, Request $request): void
    {
        DB::transaction(function () use ($documento, $path, $tamanho, $mime, $hash, $comentario, $ocrTexto, $request) {
            $numero = (int) Versao::where('documento_id', $documento->id)->max('versao') + 1;

            Versao::create([
                'documento_id' => $documento->id,
                'versao'       => $numero,
                'arquivo_path' => $path,
                'tamanho'      => $tamanho,
                'hash_sha256'  => $hash,
                'autor_id'     => Auth::id(),
                'comentario'   => $comentario,
            ]);

            $documento->update([
                'versao_atual' => $numero,
                'tamanho'      => $tamanho,
                'mime_type'    => $mime ?? $documento->mime_type,
                'ocr_texto'    => $ocrTexto,
            ]);

            AuditLog::create([
                'documento_id' => $documento->id,
                'usuario_id'   => Auth::id(),
                'acao'         => 'nova_versao',
                'detalhes'     => ['versao' => $numero, 'comentario' => $comentario, 'hash' => $hash],
                'ip'           => $request->ip(),
                'user_agent'   => $request->userAgent(),
            ]);
        });
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome'              => ['required', 'string', 'max:255'],
            'descricao'         => ['nullable', 'string'],
            'tipo_documental_id'=> ['required', 'integer', 'exists:ged_tipos_documentais,id'],
            'pasta_id'          => ['nullable', 'integer', 'exists:ged_pastas,id'],
            'arquivo'           => ['required', 'file', 'max:51200'],
        ]);

        try {
            DB::beginTransaction();

            $file = $request->file('arquivo');
            $path = $file->store(\App\Tenant\TenantStorage::pasta('documentos'), 'documentos');

            // Extrai texto se for PDF — para busca full-text no repositorio
            $ocrTexto = null;
            if ($file->getMimeType() === 'application/pdf') {
                $ocrTexto = (new \App\Services\PdfTextExtractor())->extrair($file->getRealPath());
            }

            $documento = Documento::create([
                'nome'              => $request->input('nome'),
                'descricao'         => $request->input('descricao'),
                'tipo_documental_id'=> $request->input('tipo_documental_id'),
                'pasta_id'          => $request->input('pasta_id'),
                'versao_atual'      => 1,
                'tamanho'           => $file->getSize(),
                'mime_type'         => $file->getMimeType(),
                'autor_id'          => Auth::id(),
                'status'            => 'rascunho',
                'ocr_texto'         => $ocrTexto,
            ]);

            Versao::create([
                'documento_id' => $documento->id,
                'versao'       => 1,
                'arquivo_path' => $path,
                'tamanho'      => $file->getSize(),
                'hash_sha256'  => hash_file('sha256', $file->getRealPath()),
                'autor_id'     => Auth::id(),
                'comentario'   => 'Versao inicial',
            ]);

            AuditLog::create([
                'documento_id' => $documento->id,
                'usuario_id'   => Auth::id(),
                'acao'         => 'criacao',
                'detalhes'     => ['nome' => $documento->nome],
                'ip'           => $request->ip(),
                'user_agent'   => $request->userAgent(),
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Documento criado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao criar documento: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nome'              => ['required', 'string', 'max:255'],
            'descricao'         => ['nullable', 'string'],
            'tipo_documental_id'=> ['nullable', 'integer', 'exists:ged_tipos_documentais,id'],
            'pasta_id'          => ['nullable', 'integer', 'exists:ged_pastas,id'],
            'status'            => ['nullable', 'string', 'in:rascunho,publicado,revisao,arquivado'],
        ]);

        try {
            $documento = Documento::findOrFail($id);
            $campos = ['nome', 'descricao', 'tipo_documental_id', 'pasta_id', 'status'];
            $antes = $documento->only($campos);

            $documento->update($request->only($campos));

            // Valor anterior e novo de cada campo alterado (antes só o novo, de 3 campos).
            $alterados = [];
            foreach ($campos as $c) {
                if ($antes[$c] != $documento->{$c}) {
                    $alterados[$c] = ['de' => $antes[$c], 'para' => $documento->{$c}];
                }
            }

            AuditLog::create([
                'documento_id' => $documento->id,
                'usuario_id'   => Auth::id(),
                'acao'         => 'edicao',
                'detalhes'     => $alterados,
                'ip'           => $request->ip(),
                'user_agent'   => $request->userAgent(),
            ]);

            return redirect()->back()->with('success', 'Documento atualizado com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao atualizar documento: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $documento = Documento::findOrFail($id);
            $documento->delete();

            AuditLog::create([
                'documento_id' => $documento->id,
                'usuario_id'   => Auth::id(),
                'acao'         => 'exclusao',
                'detalhes'     => ['nome' => $documento->nome],
                'ip'           => request()->ip(),
                'user_agent'   => request()->userAgent(),
            ]);

            return redirect('/documentos')->with('success', 'Documento excluido com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao excluir documento: ' . $e->getMessage());
        }
    }

    /**
     * Move um ou varios documentos para uma pasta (ou para a raiz, com pasta_id=null).
     * Aceita lote via array de IDs.
     */
    public function moverPasta(Request $request)
    {
        $validated = $request->validate([
            'documento_ids'   => ['required', 'array', 'min:1'],
            'documento_ids.*' => ['integer', 'exists:ged_documentos,id'],
            'pasta_id'        => ['nullable', 'integer', 'exists:ged_pastas,id'],
        ]);

        $pasta = null;
        if ($validated['pasta_id']) {
            $pasta = \DB::table('ged_pastas')->where('id', $validated['pasta_id'])->first();
        }

        $atualizados = 0;
        $erros = [];

        foreach ($validated['documento_ids'] as $docId) {
            $doc = Documento::find($docId);
            if (! $doc) {
                $erros[] = "Documento #{$docId} nao encontrado.";
                continue;
            }
            if ($pasta && $pasta->ug_id !== $doc->ug_id) {
                $erros[] = "Documento {$doc->nome}: pasta nao pertence a UG.";
                continue;
            }
            $pastaAnterior = $doc->pasta_id;
            $doc->update(['pasta_id' => $validated['pasta_id'] ?? null]);
            AuditLog::create([
                'documento_id' => $doc->id,
                'usuario_id'   => Auth::id(),
                'acao'         => 'movimentacao',
                'detalhes'     => ['pasta_id' => ['de' => $pastaAnterior, 'para' => $doc->pasta_id]],
                'ip'           => request()->ip(),
                'user_agent'   => request()->userAgent(),
            ]);
            $atualizados++;
        }

        $destino = $pasta?->nome ?: 'Raiz';
        $msg = $atualizados === 1
            ? "1 documento movido para \"{$destino}\"."
            : "{$atualizados} documentos movidos para \"{$destino}\".";

        if (! empty($erros)) {
            return redirect()->back()->with('warning', $msg . ' Avisos: ' . implode(' ', $erros));
        }
        return redirect()->back()->with('success', $msg);
    }

    public function download($id, Request $request)
    {
        $documento = Documento::with('versaoAtual')->findOrFail($id);
        $versao = $documento->versaoAtual;

        if (!$versao) {
            return redirect()->back()->with('error', 'Arquivo nao encontrado.');
        }

        if ($documento->status === 'cancelado') {
            return redirect()->back()->with('error',
                'Documento cancelado pela origem (sistema externo). Download nao disponivel.'
            );
        }

        // Quando ?original=1, ignora assinatura e retorna o PDF pre-assinatura
        $caminho = $request->boolean('original')
            ? $versao->arquivo_path
            : $this->caminhoVersaoOficial($documento, $versao);

        if (! Storage::disk('documentos')->exists($caminho)) {
            return redirect()->back()->with('error', 'Arquivo nao encontrado.');
        }

        AuditLog::create([
            'documento_id' => $documento->id,
            'usuario_id'   => Auth::id(),
            'acao'         => 'download',
            'detalhes'     => ['versao' => $versao->versao, 'oficial' => ! request()->boolean('original')],
            'ip'           => request()->ip(),
            'user_agent'   => request()->userAgent(),
        ]);

        return Storage::disk('documentos')->download($caminho, $this->sanitizarNomeArquivo($documento));
    }

    public function preview($id, Request $request)
    {
        $documento = Documento::with('versaoAtual')->findOrFail($id);
        $versao = $documento->versaoAtual;

        if (!$versao) {
            return redirect()->back()->with('error', 'Arquivo nao encontrado.');
        }

        // Quando ?original=1, ignora assinatura e retorna o PDF pre-assinatura
        $caminho = $request->boolean('original')
            ? $versao->arquivo_path
            : $this->caminhoVersaoOficial($documento, $versao);

        if (! Storage::disk('documentos')->exists($caminho)) {
            return redirect()->back()->with('error', 'Arquivo nao encontrado.');
        }

        return Storage::disk('documentos')->response($caminho, $this->sanitizarNomeArquivo($documento), [
            'Content-Type' => $documento->mime_type,
        ]);
    }

    /**
     * Devolve o caminho da versao "oficial" do documento: se houver assinatura
     * ICP concluida com PDF assinado (PAdES-BES), retorna esse arquivo. Caso
     * contrario, retorna o caminho da versao atual (PDF original).
     */
    private function caminhoVersaoOficial(Documento $documento, $versao): string
    {
        $assinada = \App\Models\Assinatura::where('documento_id', $documento->id)
            ->where('status', 'assinado')
            ->whereNotNull('arquivo_assinado_path')
            ->orderByDesc('assinado_em')
            ->first();

        if ($assinada && $assinada->arquivo_assinado_path
            && Storage::disk('documentos')->exists($assinada->arquivo_assinado_path)) {
            return $assinada->arquivo_assinado_path;
        }

        return $versao->arquivo_path;
    }

    /**
     * Remove caracteres invalidos do nome para uso em Content-Disposition (HTTP).
     * `/` e `\` quebram o makeDisposition do Symfony.
     */
    private function sanitizarNomeArquivo(Documento $doc): string
    {
        $nome = str_replace(['/', '\\'], '-', $doc->nome ?? 'documento');
        // Adiciona extensao se nao tiver
        if (! pathinfo($nome, PATHINFO_EXTENSION) && $doc->mime_type) {
            $ext = match (true) {
                str_contains($doc->mime_type, 'pdf') => '.pdf',
                str_contains($doc->mime_type, 'png') => '.png',
                str_contains($doc->mime_type, 'jpeg') || str_contains($doc->mime_type, 'jpg') => '.jpg',
                default => '',
            };
            $nome .= $ext;
        }
        return $nome;
    }

    public function toggleFavorito($id)
    {
        $user = Auth::user();
        $exists = $user->favoritos()->where('documento_id', $id)->exists();

        if ($exists) {
            $user->favoritos()->detach($id);
            $msg = 'Documento removido dos favoritos.';
        } else {
            $user->favoritos()->attach($id, ['created_at' => now()]);
            $msg = 'Documento adicionado aos favoritos.';
        }

        return redirect()->back()->with('success', $msg);
    }

    public function alterarStatus(Request $request, $id)
    {
        $request->validate([
            'status' => ['required', 'string', 'in:rascunho,publicado,revisao,arquivado'],
        ]);

        $documento = Documento::findOrFail($id);
        $statusAnterior = $documento->status;
        $documento->update(['status' => $request->input('status')]);

        AuditLog::create([
            'documento_id' => $documento->id,
            'usuario_id'   => Auth::id(),
            'acao'         => 'alteracao_status',
            'detalhes'     => ['de' => $statusAnterior, 'para' => $request->input('status')],
            'ip'           => $request->ip(),
            'user_agent'   => $request->userAgent(),
        ]);

        return redirect()->back()->with('success', 'Status alterado com sucesso.');
    }
}
