<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recurso do requerente no e-SIC (Lei nº 12.527/2011, art. 15 e 16): contra a negativa de acesso ou a
 * resposta incompleta, em 1ª e 2ª instância — 10 dias para interpor, 5 dias para a autoridade decidir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esic_recursos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('processo_id')->constrained('proc_processos')->cascadeOnDelete();
            $t->unsignedTinyInteger('instancia');                     // 1 | 2
            $t->text('motivo');
            $t->string('origem', 20);                                  // portal | balcao | email | telefone | presencial
            $t->unsignedBigInteger('cidadao_id')->nullable();          // portal_cidadaos (quem recorreu pelo portal)
            $t->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('interposto_em');
            $t->timestamp('decidir_ate');
            $t->string('decisao', 25)->nullable();                     // provido | parcialmente_provido | nao_provido
            $t->text('fundamentacao')->nullable();
            $t->foreignId('decidido_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decidido_em')->nullable();
            $t->timestamps();
            $t->unique(['processo_id', 'instancia']);
        });

        $ids = Permissoes::sincronizarCatalogo();
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['esic.gerir'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['esic.recursos'], $ids);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('esic_recursos');
        DB::table('ged_permissions')->where('nome', 'esic.recursos')->delete();
    }
};
