<?php

use App\Support\Notificacoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notificação multicanal (TR 4.11): categoria para a página de notificações (4.11.3),
 * alcance (para mim × para o meu setor) e urgência para a abrangência que cada servidor
 * escolhe por canal (4.11.4–5), e quando foi lida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ged_notificacoes', function (Blueprint $t) {
            $t->string('categoria', 30)->nullable()->index();
            $t->string('alcance', 10)->default('pessoal');   // pessoal | setor
            $t->boolean('urgente')->default(false);
            $t->timestamp('lida_em')->nullable();
        });
        foreach (DB::table('ged_notificacoes')->select('tipo')->distinct()->pluck('tipo') as $tipo) {
            DB::table('ged_notificacoes')->where('tipo', $tipo)->update(['categoria' => Notificacoes::categoriaDe((string) $tipo)]);
        }
    }

    public function down(): void
    {
        Schema::table('ged_notificacoes', fn (Blueprint $t) => $t->dropColumn(['categoria', 'alcance', 'urgente', 'lida_em']));
    }
};
