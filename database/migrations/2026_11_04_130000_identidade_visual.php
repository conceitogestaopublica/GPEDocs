<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identidade visual do município (TR 4.22.1): cor predominante (sistema e portal), brasão
 * mostrado no acesso (o de uma UG) e imagem de fundo da tela de acesso. Uma linha por banco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ged_identidade_visual', function (Blueprint $t) {
            $t->id();
            $t->string('cor_primaria', 7)->nullable();     // #rrggbb; vazio = ciano padrão do GPE
            $t->foreignId('brasao_ug_id')->nullable()->constrained('ugs')->nullOnDelete();
            $t->string('fundo_login_path', 500)->nullable();
            $t->foreignId('atualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ged_identidade_visual');
    }
};
