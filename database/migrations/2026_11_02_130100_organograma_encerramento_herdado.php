<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Setores importados do gpe2 que ficaram ativos debaixo de um órgão/unidade encerrado
 * (o gpe2 encerra o de cima e deixa os departamentos sem data). Herdam o encerramento
 * do pai — menos os que ainda têm servidor ou item em aberto, que pedem realocação.
 */
return new class extends Migration
{
    public function up(): void
    {
        do {
            $orfaos = DB::table('ug_organograma as f')->join('ug_organograma as p', 'p.id', '=', 'f.parent_id')
                ->whereNotNull('f.legado_tipo')->where('f.ativo', true)->where('p.ativo', false)
                ->whereNotExists(fn ($q) => $q->from('users')->whereColumn('users.unidade_id', 'f.id'))
                ->whereNotExists(fn ($q) => $q->from('user_setores')->whereColumn('user_setores.unidade_id', 'f.id'))
                ->whereNotExists(fn ($q) => $q->from('proc_inbox')->whereColumn('proc_inbox.destino_unidade_id', 'f.id')
                    ->whereNotIn('proc_inbox.status', ['concluido', 'cancelado', 'arquivado']))
                ->get(['f.id', 'p.dt_encerramento']);
            foreach ($orfaos as $o) {
                DB::table('ug_organograma')->where('id', $o->id)
                    ->update(['ativo' => false, 'dt_encerramento' => $o->dt_encerramento, 'updated_at' => now()]);
            }
        } while ($orfaos->isNotEmpty());
    }

    public function down(): void
    {
        // Sem volta automática: não dá para distinguir dos desativados à mão depois.
    }
};
