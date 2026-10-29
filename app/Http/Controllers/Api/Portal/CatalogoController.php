<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Portal;

use App\Models\Portal\CategoriaServico;
use App\Models\Portal\Servico;
use App\Models\Processo\TipoProcesso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Carta de Serviços da UG, para o Portal do Cidadão montar a vitrine. Só o
 * que está ativo/publicado. A Carta é editada aqui no GPE Docs (Configurações
 * › Portal do Cidadão › Carta de Serviços); o portal só lê.
 */
class CatalogoController extends PortalApiController
{
    public function show(Request $request): JsonResponse
    {
        $ug = $this->ug($request);

        $categorias = CategoriaServico::query()->withoutGlobalScope('ug')
            ->where('ug_id', $ug->id)
            ->where('ativo', true)
            ->orderBy('ordem')->orderBy('nome')
            ->get();

        $servicos = Servico::query()->withoutGlobalScope('ug')
            ->where('ug_id', $ug->id)
            ->where('publicado', true)
            ->whereIn('categoria_id', $categorias->pluck('id'))
            ->orderBy('ordem')->orderBy('titulo')
            ->get();

        $slugCategoria = $categorias->pluck('slug', 'id');
        $tipos = TipoProcesso::whereIn('id', $servicos->pluck('tipo_processo_id')->filter()->unique())->get()->keyBy('id');

        return response()->json([
            'ug' => [
                'nome'     => $ug->nome,
                'site'     => $ug->site,
                'email'    => $ug->email_institucional,
                'telefone' => $ug->telefone,
            ],
            // Identidade visual do município para o portal (TR 4.22.1): cor predominante e brasão
            'identidade' => array_intersect_key(\App\Support\IdentidadeVisual::paraTela(), ['cor' => 1, 'brasao_url' => 1]),
            'categorias' => $categorias->map(fn (CategoriaServico $c) => [
                'slug'      => $c->slug,
                'nome'      => $c->nome,
                'descricao' => $c->descricao,
                'icone'     => $c->icone,
                'cor'       => $c->cor,
                'area'      => $c->area,
                'ordem'     => $c->ordem,
            ])->values(),
            'servicos' => $servicos->map(fn (Servico $s) => [
                'slug'                   => $s->slug,
                'categoria_slug'         => $slugCategoria[$s->categoria_id] ?? null,
                'titulo'                 => $s->titulo,
                'publico_alvo'           => $s->publico_alvo,
                'descricao_curta'        => $s->descricao_curta,
                'descricao_completa'     => $s->descricao_completa,
                'requisitos'             => $s->requisitos,
                'documentos_necessarios' => $s->documentos_necessarios ?? [],
                'prazo_entrega'          => $s->prazo_entrega,
                'custo'                  => $s->custo,
                'orgao_responsavel'      => $s->orgao_responsavel,
                'legislacao'             => $s->legislacao,
                'palavras_chave'         => $s->palavras_chave ?? [],
                'icone'                  => $s->icone,
                'permite_anonimo'        => (bool) $s->permite_anonimo,
                'acao_fiscal'            => $s->acao_fiscal,
                // Solicitação vira processo sozinha (tipo de processo + setor).
                'abre_processo'          => $s->tipo_processo_id !== null && $s->setor_responsavel_id !== null,
                'ordem'                  => $s->ordem,
                // Formulário e documentos exigidos do assunto (TR 4.38.3–4.38.9, 4.38.18)
                'formulario'             => self::formulario($tipos[$s->tipo_processo_id] ?? null),
                'documentos_exigidos'    => self::documentos($tipos[$s->tipo_processo_id] ?? null),
                // Local no mapa (nao | opcional | obrigatoria) e avaliação ao concluir (TR 4.32.21, 4.32.25)
                'localizacao'            => $tipos[$s->tipo_processo_id]?->localizacao ?? 'nao',
                'avaliacao'              => (bool) ($tipos[$s->tipo_processo_id]?->avaliacao ?? false),
            ])->values(),
        ]);
    }

    /** Campos do assunto para o portal montar o formulário (mesmas regras do GED). */
    private static function formulario(?TipoProcesso $tipo): array
    {
        return collect($tipo?->schema_formulario ?? [])
            ->filter(fn ($c) => trim((string) ($c['campo'] ?? '')) !== '')
            ->map(fn ($c) => [
                'campo'       => trim((string) $c['campo']),
                'tipo'        => $c['tipo'] ?? 'text',
                'rotulo'      => (string) ($c['label'] ?? $c['campo']),
                'obrigatorio' => ! empty($c['obrigatorio']),
                'opcoes'      => ($c['tipo'] ?? '') === 'select'
                    ? array_values(array_filter(array_map('trim', explode(',', (string) ($c['opcoes'] ?? ''))), 'strlen')) : [],
                'formula'     => ($c['tipo'] ?? '') === 'calculo' ? (string) ($c['formula'] ?? '') : null,
                'ajuda'       => ($c['ajuda'] ?? '') ?: null,
                // GET api/portal/ajuda-imagem/{id} (com o token do portal)
                'ajuda_imagem_id' => preg_match('/([0-9a-f-]{36})$/', (string) ($c['ajuda_imagem'] ?? ''), $m) ? $m[1] : null,
            ])->values()->all();
    }

    private static function documentos(?TipoProcesso $tipo): array
    {
        return collect($tipo?->anexos_exigidos ?? [])->values()->map(fn ($d, $i) => [
            'indice'      => $i,
            'nome'        => (string) ($d['nome'] ?? 'Documento'),
            'obrigatorio' => (bool) ($d['obrigatorio'] ?? true),
            // O portal aceita só PDF, JPG e PNG (AnexosSolicitacaoService::TIPOS)
            'extensoes'   => array_values(array_intersect(
                array_filter(array_map(fn ($e) => strtolower(trim($e, " .\t")), explode(',', (string) ($d['extensoes'] ?? '')))) ?: ['pdf', 'jpg', 'png'],
                ['pdf', 'jpg', 'jpeg', 'png'])),
        ])->all();
    }
}
