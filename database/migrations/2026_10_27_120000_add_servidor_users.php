<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Servidor municipal no cadastro de usuários: o funcionário de origem no RH do gpe2
 * (rh_funcionario.id) e a matrícula. Preenchidos pela conversão docs:importar-servidores;
 * é o que vai identificar o usuário na área do servidor do Portal do Cidadão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->unsignedBigInteger('legado_funcionario_id')->nullable()->unique()->after('legado_usuario_id');
            $t->string('matricula', 30)->nullable()->after('legado_funcionario_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['legado_funcionario_id']);
            $t->dropColumn(['legado_funcionario_id', 'matricula']);
        });
    }
};
