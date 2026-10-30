<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Férias, afastamentos e licenças do servidor (TR 4.7.15): quem escolhe
 * signatário ou destinatário vê que a pessoa está fora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ged_usuario_ausencias', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users', indexName: 'ged_usuario_ausencias_x_users_X_user_id')->cascadeOnDelete();
            $t->string('tipo', 20);                 // ferias | afastamento | licenca
            $t->date('inicio');
            $t->date('fim');
            $t->string('observacao', 300)->nullable();
            $t->timestamps();
            $t->index(['user_id', 'inicio', 'fim']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ged_usuario_ausencias');
    }
};
