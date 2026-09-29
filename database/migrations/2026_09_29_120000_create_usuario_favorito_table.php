<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rotinas favoritas por usuário × UG (Ctrl+K → estrela) — padrão do gpe2 (usuario_favorito,
 * lá por gestora). Não confundir com `ged_favoritos`, que são DOCUMENTOS favoritos.
 * ug_id = 0 quando não há UG na sessão (super admin sem UG).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('usuario_favorito')) {
            return;
        }

        Schema::create('usuario_favorito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users', indexName: 'usuario_favorito_x_users_X_user_id')->cascadeOnDelete();
            $table->unsignedBigInteger('ug_id')->default(0);
            $table->string('href', 255);
            $table->string('label', 255);
            $table->string('icon', 60)->nullable();
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'ug_id', 'href']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_favorito');
    }
};
