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

        // Filtros da Carta (TR 4.30.1, 4.30.28): busca, órgão, perfil, categoria; ordem alfabética ou mais acessados (4.30.27)
        $q = trim((string) $request->query('q'));
        $orgao = $request->integer('orgao') ?: null;
        $servicos = Servico::query()->withoutGlobalScope('ug')
            ->where('ug_id', $ug->id)
            ->where('publicado', true)
            ->whereIn('categoria_id', $categorias->pluck('id'))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->whereRaw('lower(titulo) like ?', ['%' . mb_strtolower($q) . '%'])
                ->orWhereRaw("lower(coalesce(descricao_curta, '')) like ?", ['%' . mb_strtolower($q) . '%'])
                ->orWhereRaw('lower(cast(palavras_chave as text)) like ?', ['%' . mb_strtolower($q) . '%'])))
            ->when($request->filled('categoria'), fn ($w) => $w->whereIn('categoria_id', $categorias->where('slug', $request->query('categoria'))->pluck('id')))
            ->when($request->filled('perfil'), fn ($w) => $w->where(fn ($x) => $x->whereJsonContains('perfis', (string) $request->query('perfil'))
                ->orWhere(fn ($y) => $y->whereNull('perfis')->where('publico_alvo', $request->query('perfil')))))
            ->when($orgao, fn ($w) => $w->whereIn('setor_responsavel_id', \App\Support\Organograma::comDescendentes($orgao)))
            ->when($request->query('ordem') === 'alfabetica', fn ($w) => $w->orderBy('titulo'))
            ->when($request->query('ordem') === 'acessados', fn ($w) => $w->orderByDesc('visualizacoes')->orderBy('titulo'))
            ->orderBy('ordem')->orderBy('titulo')
            ->get();
        $orgaos = self::orgaosDos($servicos->pluck('setor_responsavel_id')->filter()->unique()->all());

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
            // Política de privacidade e acesso à informação (TR 4.23.1, 4.23.5)
            'textos'     => CentralController::textosPortal(),
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
                // Órgão do organograma com sigla (TR 4.30.3–4.30.4)
                'orgao'                  => $orgaos[$s->setor_responsavel_id] ?? null,
                'canais'                 => $s->canais ?? [],
                'perfis'                 => $s->perfis ?: [$s->publico_alvo],
                'visualizacoes'          => (int) $s->visualizacoes,
                // Lei 13.460 (TR 4.30.14–4.30.26)
                'caracteristicas'        => $s->caracteristicas,
                'etapas'                 => $s->etapas ?? [],
                'prazo_dias'             => $s->prazo_dias,
                'tempo_espera'           => $s->tempo_espera,
                'prioridades'            => $s->prioridades,
                'forma_prestacao'        => $s->forma_prestacao,
                'locais_manifestacao'    => $s->locais_manifestacao,
                'mecanismos_comunicacao' => $s->mecanismos_comunicacao,
                'procedimento_manifestacoes' => $s->procedimento_manifestacoes,
                'mecanismos_consulta'    => $s->mecanismos_consulta,
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
                // ouvidoria | esic | ... — o portal mostra tipo e identificação da manifestação (TR 4.33.8, 4.33.27)
                'modalidade'             => $tipos[$s->tipo_processo_id]?->modalidade,
            ])->values(),
        ]);
    }

    /**
     * Setor responsável → {setor, sigla, orgao, orgao_sigla, orgao_id}: o órgão é o nível 1 acima
     * dele no organograma (TR 4.30.3).
     *
     * @return array<int, array>
     */
    private static function orgaosDos(array $setores): array
    {
        $saida = [];
        foreach (\App\Models\UgOrganograma::whereIn('id', $setores)->get(['id', 'nome', 'sigla', 'parent_id', 'nivel']) as $s) {
            $topo = $s;
            for ($i = 0; $topo->parent_id && $i < 10; $i++) {
                $topo = \App\Models\UgOrganograma::find($topo->parent_id, ['id', 'nome', 'sigla', 'parent_id']) ?? $topo;
            }
            $saida[$s->id] = ['setor' => $s->nome, 'sigla' => $s->sigla, 'orgao_id' => $topo->id, 'orgao' => $topo->nome, 'orgao_sigla' => $topo->sigla];
        }

        return $saida;
    }

    /** Órgãos responsáveis com serviço publicado, para o filtro do portal (TR 4.30.4). */
    public function orgaos(Request $request): JsonResponse
    {
        $ug = $this->ug($request);
        $setores = Servico::query()->withoutGlobalScope('ug')->where('ug_id', $ug->id)->where('publicado', true)
            ->whereNotNull('setor_responsavel_id')->pluck('setor_responsavel_id')->unique()->all();
        $porOrgao = collect(self::orgaosDos($setores))->groupBy('orgao_id');

        return response()->json($porOrgao->map(fn ($l, $id) => ['id' => (int) $id, 'nome' => $l->first()['orgao'], 'sigla' => $l->first()['orgao_sigla'],
            'servicos' => Servico::query()->withoutGlobalScope('ug')->where('ug_id', $ug->id)->where('publicado', true)
                ->whereIn('setor_responsavel_id', \App\Support\Organograma::comDescendentes((int) $id))->count()])
            ->sortBy('nome')->values());
    }

    /** O portal avisa que o serviço foi aberto: alimenta "mais acessados" (TR 4.30.27). */
    public function visualizacao(Request $request, string $slug): JsonResponse
    {
        $ug = $this->ug($request);
        Servico::query()->withoutGlobalScope('ug')->where('ug_id', $ug->id)->where('slug', $slug)->where('publicado', true)->increment('visualizacoes');

        return response()->json(['ok' => true]);
    }

    /** Campos do assunto para o portal montar o formulário (mesmas regras do GED). */
    public static function formulario(?TipoProcesso $tipo): array
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

    public static function documentos(?TipoProcesso $tipo): array
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
