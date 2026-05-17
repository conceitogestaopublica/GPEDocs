<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UgOrganograma;
use App\Support\TextoNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizarNomesSetores extends Command
{
    protected $signature = 'setores:normalizar-nome
                            {--ug= : Restringe a uma UG especifica (id)}
                            {--dry-run : Apenas mostra o que seria alterado, sem gravar}';

    protected $description = 'Aplica sentence case (apenas primeira letra maiuscula) nos nomes dos setores (nos folha do organograma).';

    public function handle(): int
    {
        $ugId   = $this->option('ug');
        $dryRun = (bool) $this->option('dry-run');

        // Folhas = nos cujo id nao aparece como parent_id de ninguem.
        $idsComFilhos = DB::table('ug_organograma')
            ->whereNotNull('parent_id')
            ->when($ugId, fn ($q) => $q->where('ug_id', (int) $ugId))
            ->distinct()
            ->pluck('parent_id')
            ->all();

        $folhas = UgOrganograma::query()
            ->when($ugId, fn ($q) => $q->where('ug_id', (int) $ugId))
            ->whereNotIn('id', $idsComFilhos ?: [0])
            ->orderBy('ug_id')
            ->orderBy('nome')
            ->get(['id', 'ug_id', 'parent_id', 'nivel', 'nome']);

        if ($folhas->isEmpty()) {
            $this->warn('Nenhum setor (no folha) encontrado.');
            return self::SUCCESS;
        }

        $alterados = 0;
        $iguais    = 0;

        foreach ($folhas as $no) {
            $novo = TextoNormalizer::sentenceCase($no->nome);

            if ($novo === $no->nome) {
                $iguais++;
                continue;
            }

            $this->line(sprintf(
                ' UG %d  N%d  #%d  %s  →  %s',
                $no->ug_id, $no->nivel, $no->id, $no->nome, $novo
            ));

            if (! $dryRun) {
                // Atualiza direto via query builder para nao disparar o
                // mutator do Model (evita verificacao redundante de folha).
                DB::table('ug_organograma')->where('id', $no->id)->update(['nome' => $novo]);
            }

            $alterados++;
        }

        $this->newLine();
        $this->info(sprintf(
            '%s%d setor(es) ajustado(s), %d ja estavam no padrao.',
            $dryRun ? '[DRY-RUN] ' : '',
            $alterados,
            $iguais
        ));

        return self::SUCCESS;
    }
}
