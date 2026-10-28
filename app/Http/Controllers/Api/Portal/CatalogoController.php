<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Portal;

use App\Models\Portal\CategoriaServico;
use App\Models\Portal\Servico;
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

        return response()->json([
            'ug' => [
                'nome'     => $ug->nome,
                'site'     => $ug->site,
                'email'    => $ug->email_institucional,
                'telefone' => $ug->telefone,
            ],
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
            ])->values(),
        ]);
    }
}
