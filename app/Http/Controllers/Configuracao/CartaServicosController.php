<?php

declare(strict_types=1);

namespace App\Http\Controllers\Configuracao;

use App\Http\Controllers\Controller;
use App\Models\Portal\CategoriaServico;
use App\Models\Portal\Servico;
use App\Models\Processo\TipoProcesso;
use App\Models\UgOrganograma;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CartaServicosController extends Controller
{
    public function index(Request $request): Response
    {
        $busca      = (string) $request->input('q', '');
        $categoria  = $request->input('categoria_id');
        $publicoAlvo = $request->input('publico_alvo');

        $servicos = Servico::query()
            ->with('categoria:id,nome,cor,icone')
            ->when($busca !== '', function ($q) use ($busca) {
                $q->where(function ($qq) use ($busca) {
                    $qq->where('titulo', 'ilike', "%{$busca}%")
                       ->orWhere('descricao_curta', 'ilike', "%{$busca}%");
                });
            })
            ->when($categoria, fn ($q) => $q->where('categoria_id', $categoria))
            ->when($publicoAlvo, fn ($q) => $q->where('publico_alvo', $publicoAlvo))
            ->orderBy('ordem')
            ->orderBy('titulo')
            ->paginate(20)
            ->withQueryString();

        $categorias = CategoriaServico::query()
            ->withCount('servicos')
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get();

        // Organograma e multi-tenant: so mostra setores da UG ativa
        $ugAtual = session('ug_id');

        $setores = UgOrganograma::query()
            ->when($ugAtual, fn ($q) => $q->where('ug_id', $ugAtual))
            ->where('ativo', true)
            ->orderBy('nivel')
            ->orderBy('nome')
            ->get(['id', 'nome', 'nivel']);

        // TipoProcesso e global (sem ug_id) — todas UGs compartilham
        $tiposProcesso = TipoProcesso::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'sigla']);

        return Inertia::render('Portal/Admin/CartaServicos/Index', [
            'servicos'      => $servicos,
            'categorias'    => $categorias,
            'setores'       => $setores,
            'tiposProcesso' => $tiposProcesso,
            'filtros'    => [
                'q'             => $busca,
                'categoria_id'  => $categoria,
                'publico_alvo'  => $publicoAlvo,
            ],
            'publicos' => Servico::PUBLICOS,
        ]);
    }

    // ---- Categorias ----

    public function storeCategoria(Request $request)
    {
        $data = $request->validate([
            'nome'      => ['required', 'string', 'max:120'],
            'icone'     => ['nullable', 'string', 'max:60'],
            'cor'       => ['nullable', 'string', 'max:20'],
            'descricao' => ['nullable', 'string'],
            'ordem'     => ['nullable', 'integer'],
        ]);

        $data['slug']  = $this->slugUnicoCategoria($data['nome']);
        $data['ativo'] = true;

        CategoriaServico::create($data);

        return back()->with('success', 'Categoria criada com sucesso.');
    }

    public function updateCategoria(Request $request, int $id)
    {
        $categoria = CategoriaServico::findOrFail($id);

        $data = $request->validate([
            'nome'      => ['required', 'string', 'max:120'],
            'icone'     => ['nullable', 'string', 'max:60'],
            'cor'       => ['nullable', 'string', 'max:20'],
            'descricao' => ['nullable', 'string'],
            'ordem'     => ['nullable', 'integer'],
            'ativo'     => ['nullable', 'boolean'],
        ]);

        if ($data['nome'] !== $categoria->nome) {
            $data['slug'] = $this->slugUnicoCategoria($data['nome'], $categoria->id);
        }

        $categoria->update($data);

        return back()->with('success', 'Categoria atualizada com sucesso.');
    }

    public function destroyCategoria(int $id)
    {
        $categoria = CategoriaServico::withCount('servicos')->findOrFail($id);

        if ($categoria->servicos_count > 0) {
            return back()->with('error', 'Esta categoria tem '.$categoria->servicos_count.' servico(s) vinculados. Mova ou exclua os servicos antes.');
        }

        $categoria->delete();
        return back()->with('success', 'Categoria excluida.');
    }

    // ---- Servicos ----

    public function storeServico(Request $request)
    {
        $data = $this->validateServico($request);
        $data['slug'] = $this->slugUnicoServico($data['titulo']);
        Servico::create($data);

        return back()->with('success', 'Servico criado com sucesso.');
    }

    public function updateServico(Request $request, int $id)
    {
        $servico = Servico::findOrFail($id);
        $data = $this->validateServico($request);

        if ($data['titulo'] !== $servico->titulo) {
            $data['slug'] = $this->slugUnicoServico($data['titulo'], $servico->id);
        }

        $servico->update($data);
        return back()->with('success', 'Servico atualizado com sucesso.');
    }

    public function destroyServico(int $id)
    {
        $servico = Servico::findOrFail($id);
        $servico->delete();
        return back()->with('success', 'Servico excluido.');
    }

    public function togglePublicado(int $id)
    {
        $servico = Servico::findOrFail($id);
        $servico->update(['publicado' => ! $servico->publicado]);
        $estado = $servico->publicado ? 'publicado' : 'despublicado';
        return back()->with('success', "Servico {$estado}.");
    }

    private function validateServico(Request $request): array
    {
        return $request->validate([
            'categoria_id'           => ['nullable', 'integer', 'exists:portal_categorias_servicos,id'],
            'titulo'                 => ['required', 'string', 'max:200'],
            'publico_alvo'           => ['required', 'string', 'in:cidadao,empresa,servidor'],
            'descricao_curta'        => ['nullable', 'string', 'max:500'],
            'descricao_completa'     => ['nullable', 'string'],
            'requisitos'             => ['nullable', 'string'],
            'documentos_necessarios' => ['nullable', 'array'],
            'documentos_necessarios.*' => ['string', 'max:255'],
            'prazo_entrega'          => ['nullable', 'string', 'max:200'],
            'custo'                  => ['nullable', 'string', 'max:200'],
            'canais'                 => ['nullable', 'array'],
            'orgao_responsavel'      => ['nullable', 'string', 'max:200'],
            'setor_responsavel_id'   => ['nullable', 'integer', 'exists:ug_organograma,id'],
            'tipo_processo_id'       => ['nullable', 'integer', 'exists:proc_tipos_processo,id'],
            'permite_anonimo'        => ['nullable', 'boolean'],
            'legislacao'             => ['nullable', 'string'],
            'palavras_chave'         => ['nullable', 'array'],
            'palavras_chave.*'       => ['string', 'max:60'],
            'icone'                  => ['nullable', 'string', 'max:60'],
            'publicado'              => ['nullable', 'boolean'],
            'ordem'                  => ['nullable', 'integer'],
        ]);
    }

    private function slugUnicoCategoria(string $nome, ?int $ignoreId = null): string
    {
        $base = Str::slug($nome);
        $slug = $base;
        $i = 2;
        while (CategoriaServico::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }
        return $slug;
    }

    private function slugUnicoServico(string $titulo, ?int $ignoreId = null): string
    {
        $base = Str::slug($titulo);
        $slug = $base;
        $i = 2;
        while (Servico::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }
        return $slug;
    }
}
