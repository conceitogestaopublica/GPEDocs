<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Portal\CategoriaServico;
use App\Models\Portal\Servico;
use App\Models\Tenant;
use App\Models\Ug;
use App\Tenant\TenantContext;
use Illuminate\Console\Command;

/**
 * Traz para a Carta de Serviços de uma UG a Carta exportada pelo Portal do
 * Cidadão (Next.js) — `node prisma/scripts/exportar-catalogo.mjs`. É o caminho
 * de migração para o GPE Docs virar a fonte única da Carta (ADR-0018 do
 * portal): a área do portal vira `area` da categoria, e o serviço de
 * autoatendimento fiscal vira `acao_fiscal`.
 *
 * Não sobrescreve por padrão: categoria/serviço que já existe na UG (mesmo
 * slug) é pulado, para não desfazer o que a Prefeitura já ajustou. Use
 * --sobrescrever conscientemente.
 *
 *   docker exec -i app-docs php artisan portal:importar-carta --tenant=5 --ug=taquaracu < carta.json
 */
class ImportarCartaPortal extends Command
{
    protected $signature = 'portal:importar-carta
        {arquivo=- : JSON exportado pelo portal ("-" = entrada padrão)}
        {--tenant= : Tenant alvo (id ou subdomínio)}
        {--ug= : portal_slug da UG que recebe a Carta}
        {--sobrescrever : Atualiza categorias e serviços que já existem (mesmo slug)}';

    protected $description = 'Importa para uma UG a Carta de Serviços exportada pelo Portal do Cidadão';

    /** Ambiente do portal → área da categoria no GPE Docs. */
    private const AREA = [
        'atendimento'   => 'contribuinte',
        'protocolo'     => 'protocolo',
        'servidor'      => 'servidor',
        'transparencia' => 'transparencia',
    ];

    public function handle(): int
    {
        $tenant = Tenant::active()->get()->first(fn (Tenant $t) => (string) $t->id === (string) $this->option('tenant')
            || $t->subdomain === $this->option('tenant'));
        if (! $tenant) {
            $this->error('Informe --tenant (id ou subdomínio) de um tenant ativo do GPEDocs.');

            return self::FAILURE;
        }

        $arquivo = (string) $this->argument('arquivo');
        $conteudo = $arquivo === '-' ? stream_get_contents(STDIN) : @file_get_contents($arquivo);
        $carta = json_decode((string) $conteudo, true);
        if (! is_array($carta) || ($carta['versao'] ?? null) !== 1) {
            $this->error('Arquivo inválido: esperado o JSON (versao 1) do exportar-catalogo do portal.');

            return self::FAILURE;
        }

        $ctx = app(TenantContext::class);
        $ctx->set($tenant);
        try {
            $ug = Ug::query()->where('portal_slug', (string) $this->option('ug'))->first();
            if (! $ug) {
                $this->error('UG não encontrada: informe --ug com o portal_slug da UG.');

                return self::FAILURE;
            }

            $sobrescrever = (bool) $this->option('sobrescrever');
            $conta = ['categorias' => [0, 0], 'servicos' => [0, 0]];
            $categoriaId = [];

            foreach ($carta['categorias'] ?? [] as $c) {
                $d = $c['dados'] ?? [];
                $existe = CategoriaServico::withoutGlobalScope('ug')->where('ug_id', $ug->id)->where('slug', $c['slug'])->first();
                if (! $existe || $sobrescrever) {
                    $existe = CategoriaServico::withoutGlobalScope('ug')->updateOrCreate(
                        ['ug_id' => $ug->id, 'slug' => $c['slug']],
                        [
                            'nome'      => $d['nome'] ?? $c['slug'],
                            'icone'     => $d['icone'] ?? null,
                            'cor'       => $d['cor'] ?? null,
                            'descricao' => $d['descricao'] ?? null,
                            'area'      => self::AREA[$c['ambienteSlug'] ?? ''] ?? null,
                            'ordem'     => (int) ($c['ordem'] ?? 0),
                            'ativo'     => true,
                        ],
                    );
                    $conta['categorias'][0]++;
                } else {
                    $conta['categorias'][1]++;
                }
                $categoriaId[$c['slug']] = $existe->id;
            }

            foreach ($carta['servicos'] ?? [] as $s) {
                $d = $s['dados'] ?? [];
                $existe = Servico::withoutGlobalScope('ug')->where('ug_id', $ug->id)->where('slug', $s['slug'])->exists();
                if ($existe && ! $sobrescrever) {
                    $conta['servicos'][1]++;

                    continue;
                }
                $acao = ($d['tipo_fluxo'] ?? null) === 'self_service_fiscal' ? ($d['fiscal_acao'] ?? null) : null;
                if ($acao && ! array_key_exists($acao, Servico::ACOES_FISCAIS)) {
                    $this->warn("Serviço {$s['slug']}: ação fiscal \"{$acao}\" desconhecida — importado sem ação fiscal.");
                    $acao = null;
                }
                Servico::withoutGlobalScope('ug')->updateOrCreate(
                    ['ug_id' => $ug->id, 'slug' => $s['slug']],
                    [
                        'categoria_id'           => $categoriaId[$s['categoriaSlug'] ?? ''] ?? null,
                        'titulo'                 => $d['titulo'] ?? $s['slug'],
                        'publico_alvo'           => in_array($d['publico_alvo'] ?? '', array_keys(Servico::PUBLICOS), true) ? $d['publico_alvo'] : 'cidadao',
                        'permite_anonimo'        => (bool) ($d['permite_anonimo'] ?? false),
                        'descricao_curta'        => $d['descricao_curta'] ?? null,
                        'descricao_completa'     => $d['descricao_completa'] ?? null,
                        'requisitos'             => $d['requisitos'] ?? null,
                        'documentos_necessarios' => $d['documentos_necessarios'] ?? [],
                        'prazo_entrega'          => $d['prazo_entrega'] ?? null,
                        'custo'                  => $d['custo'] ?? null,
                        'orgao_responsavel'      => $d['orgao_responsavel'] ?? null,
                        'legislacao'             => $d['legislacao'] ?? null,
                        'palavras_chave'         => $d['palavras_chave'] ?? [],
                        'icone'                  => $d['icone'] ?? null,
                        'acao_fiscal'            => $acao,
                        'publicado'              => (bool) ($s['publicado'] ?? true),
                        'ordem'                  => (int) ($s['ordem'] ?? 0),
                    ],
                );
                $conta['servicos'][0]++;
            }

            $this->table(['', 'Gravados', 'Já existiam (pulados)'], [
                ['Categorias', ...$conta['categorias']],
                ['Serviços', ...$conta['servicos']],
            ]);
            $this->line("UG: {$ug->nome} (portal_slug {$ug->portal_slug}). Serviços de solicitação só abrem processo depois de ter tipo de processo e setor na Carta.");

            return self::SUCCESS;
        } finally {
            $ctx->clear();
        }
    }
}
