<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Portal\Banner;
use App\Models\Portal\CategoriaServico;
use App\Models\Portal\Servico;
use App\Models\Portal\Solicitacao;
use App\Models\Ug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portal Cidadao — Carta de Servicos.
 *
 * Roteado por subdominio: cada UG tem seu proprio dominio (ex: pmparaguacu.gpedocs.com.br).
 * O parametro {ug} vem do subdominio e bate com `portal_slug` da UG.
 *
 * Queries usam withoutGlobalScope('ug') porque nao ha sessao com ug_id em rotas publicas.
 */
class PortalController extends Controller
{
    public function home(string $ug): Response
    {
        $ugModel = $this->resolverUg($ug);

        $categorias = CategoriaServico::query()
            ->withoutGlobalScope('ug')
            ->where('ug_id', $ugModel->id)
            ->where('ativo', true)
            ->withCount(['servicos as servicos_publicados_count' => fn ($q) => $q
                ->withoutGlobalScope('ug')->where('publicado', true)])
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get();

        $maisAcessados = Servico::query()
            ->withoutGlobalScope('ug')
            ->where('ug_id', $ugModel->id)
            ->where('publicado', true)
            ->orderByDesc('visualizacoes')
            ->limit(8)
            ->get(['id', 'slug', 'titulo', 'descricao_curta', 'icone', 'categoria_id']);

        $totalServicos = Servico::query()
            ->withoutGlobalScope('ug')
            ->where('ug_id', $ugModel->id)
            ->where('publicado', true)
            ->count();

        // Solicitacoes do cidadao logado (resumo + contagem por status)
        $minhasSolicitacoes = collect();
        $contagemSolicitacoes = [];
        $cidadao = Auth::guard('cidadao')->user();
        if ($cidadao) {
            $minhasSolicitacoes = Solicitacao::query()
                ->withoutGlobalScope('ug')
                ->where('ug_id', $ugModel->id)
                ->where('cidadao_id', $cidadao->id)
                ->with('servico:id,titulo,slug,icone')
                ->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'codigo', 'status', 'descricao', 'resposta', 'servico_id', 'created_at']);

            $contagemSolicitacoes = Solicitacao::query()
                ->withoutGlobalScope('ug')
                ->where('ug_id', $ugModel->id)
                ->where('cidadao_id', $cidadao->id)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();
        }

        return Inertia::render('Portal/Home', [
            'ug'                   => $this->ugPublica($ugModel),
            'categorias'           => $categorias,
            'maisAcessados'        => $maisAcessados,
            'totalServicos'        => $totalServicos,
            'minhasSolicitacoes'   => $minhasSolicitacoes,
            'contagemSolicitacoes' => $contagemSolicitacoes,
            'statusList'           => Solicitacao::STATUS,
        ]);
    }

    public function buscar(Request $request, string $ug): Response
    {
        $ugModel = $this->resolverUg($ug);
        $q = trim((string) $request->input('q', ''));
        $categoriaId  = $request->input('categoria_id');
        $publicoAlvo  = $request->input('publico_alvo');

        $servicos = Servico::query()
            ->withoutGlobalScope('ug')
            ->where('ug_id', $ugModel->id)
            ->where('publicado', true)
            ->with('categoria:id,nome,cor,icone,slug')
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('titulo', 'ilike', "%{$q}%")
                      ->orWhere('descricao_curta', 'ilike', "%{$q}%")
                      ->orWhere('descricao_completa', 'ilike', "%{$q}%")
                      ->orWhereRaw("palavras_chave::text ilike ?", ["%{$q}%"]);
                });
            })
            ->when($categoriaId, fn ($qb) => $qb->where('categoria_id', $categoriaId))
            ->when($publicoAlvo, fn ($qb) => $qb->where('publico_alvo', $publicoAlvo))
            ->orderBy('titulo')
            ->paginate(15)
            ->withQueryString();

        $categorias = CategoriaServico::query()
            ->withoutGlobalScope('ug')
            ->where('ug_id', $ugModel->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'slug', 'icone', 'cor']);

        return Inertia::render('Portal/Buscar', [
            'ug'         => $this->ugPublica($ugModel),
            'servicos'   => $servicos,
            'categorias' => $categorias,
            'publicos'   => Servico::PUBLICOS,
            'filtros'    => [
                'q'            => $q,
                'categoria_id' => $categoriaId,
                'publico_alvo' => $publicoAlvo,
            ],
        ]);
    }

    public function categoria(string $ug, string $slug): Response
    {
        $ugModel = $this->resolverUg($ug);

        $categoria = CategoriaServico::query()
            ->withoutGlobalScope('ug')
            ->where('ug_id', $ugModel->id)
            ->where('slug', $slug)
            ->where('ativo', true)
            ->firstOrFail();

        $servicos = Servico::query()
            ->withoutGlobalScope('ug')
            ->where('ug_id', $ugModel->id)
            ->where('categoria_id', $categoria->id)
            ->where('publicado', true)
            ->orderBy('ordem')
            ->orderBy('titulo')
            ->get(['id', 'slug', 'titulo', 'descricao_curta', 'icone', 'publico_alvo']);

        return Inertia::render('Portal/Categoria', [
            'ug'        => $this->ugPublica($ugModel),
            'categoria' => $categoria,
            'servicos'  => $servicos,
            'publicos'  => Servico::PUBLICOS,
        ]);
    }

    public function servico(string $ug, string $slug): Response
    {
        $ugModel = $this->resolverUg($ug);

        $servico = Servico::query()
            ->withoutGlobalScope('ug')
            ->where('ug_id', $ugModel->id)
            ->where('slug', $slug)
            ->where('publicado', true)
            ->with('categoria:id,nome,cor,icone,slug')
            ->firstOrFail();

        DB::table('portal_servicos')->where('id', $servico->id)->increment('visualizacoes');

        $relacionados = collect();
        if ($servico->categoria_id) {
            $relacionados = Servico::query()
                ->withoutGlobalScope('ug')
                ->where('ug_id', $ugModel->id)
                ->where('categoria_id', $servico->categoria_id)
                ->where('publicado', true)
                ->where('id', '!=', $servico->id)
                ->orderBy('ordem')
                ->limit(4)
                ->get(['id', 'slug', 'titulo', 'descricao_curta', 'icone']);
        }

        return Inertia::render('Portal/Servico', [
            'ug'           => $this->ugPublica($ugModel),
            'servico'      => $servico,
            'relacionados' => $relacionados,
            'publicos'     => Servico::PUBLICOS,
        ]);
    }

    public function brasao(string $ug, int $id)
    {
        $ugModel = $this->resolverUg($ug);
        if ($ugModel->id !== $id || ! $ugModel->brasao_path || ! Storage::disk('documentos')->exists($ugModel->brasao_path)) {
            abort(404);
        }
        return Storage::disk('documentos')->response($ugModel->brasao_path);
    }

    public function banner(string $ug, int $id)
    {
        $ugModel = $this->resolverUg($ug);
        if ($ugModel->id !== $id || ! $ugModel->banner_path || ! Storage::disk('documentos')->exists($ugModel->banner_path)) {
            abort(404);
        }
        return Storage::disk('documentos')->response($ugModel->banner_path);
    }

    /**
     * Serve a imagem de um banner do carrossel (portal_banners).
     * Verifica que o banner pertence a UG do subdominio atual.
     */
    public function bannerImagem(string $ug, int $id)
    {
        $ugModel = $this->resolverUg($ug);
        $banner = Banner::where('ug_id', $ugModel->id)->where('ativo', true)->find($id);
        if (! $banner || ! Storage::disk('documentos')->exists($banner->imagem_path)) {
            abort(404);
        }
        return Storage::disk('documentos')->response($banner->imagem_path);
    }

    private function resolverUg(string $slug): Ug
    {
        return Ug::query()
            ->where('portal_slug', $slug)
            ->where('ativo', true)
            ->firstOrFail();
    }

    private function ugPublica(Ug $ug): array
    {
        $banners = Banner::where('ug_id', $ug->id)
            ->where('ativo', true)
            ->orderBy('ordem')
            ->orderBy('id')
            ->get(['id', 'titulo', 'subtitulo', 'link_url', 'link_label'])
            ->map(fn ($b) => [
                'id'         => $b->id,
                'imagem'     => "/_banner-img/{$b->id}",
                'titulo'     => $b->titulo,
                'subtitulo'  => $b->subtitulo,
                'link_url'   => $b->link_url,
                'link_label' => $b->link_label,
            ])
            ->values()
            ->all();

        return [
            'id'       => $ug->id,
            'codigo'   => $ug->codigo,
            'slug'     => $ug->portal_slug,
            'nome'     => $ug->nome,
            'cidade'   => $ug->cidade,
            'uf'       => $ug->uf,
            'brasao'   => $ug->brasao_path ? "/_brasao/{$ug->id}" : null,
            'site'     => $ug->site,
            'email'    => $ug->email_institucional,
            'telefone' => $ug->telefone,
            'banners'  => $banners,
        ];
    }
}
