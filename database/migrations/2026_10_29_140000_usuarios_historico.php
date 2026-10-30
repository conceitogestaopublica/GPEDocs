<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico de alterações do cadastro do servidor (TR 4.7.22): cada campo
 * alterado, antes e depois, quem alterou, quando e de onde.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ged_usuario_historico', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users', indexName: 'ged_usuario_historico_x_users_X_user_id')->cascadeOnDelete();
            $t->string('campo', 60);
            $t->text('antes')->nullable();
            $t->text('depois')->nullable();
            $t->unsignedBigInteger('alterado_por')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ged_usuario_historico');
    }
};
