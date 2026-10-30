<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quem abriu processo, memorando, ofício ou circular (TR 4.14.1–4.14.4): uma linha
 * por pessoa e documento, com o primeiro e o último acesso e quantas vezes. Serve à
 * lista "Quem acessou" e ao lido/não lido POR USUÁRIO da caixa. O externo (ofício
 * aberto pelo link de rastreio) entra sem usuário, com o nome.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proc_acessos', function (Blueprint $t) {
            $t->id();
            $t->string('tipo', 20);                 // processo | memorando | oficio | circular
            $t->unsignedBigInteger('item_id');
            $t->foreignId('usuario_id')->nullable()->constrained('users', indexName: 'proc_acessos_x_users_X_usuario_id');
            $t->string('externo_nome', 255)->nullable();
            $t->timestamp('primeiro_em');
            $t->timestamp('ultimo_em');
            $t->unsignedInteger('vezes')->default(1);
            $t->string('ip', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->index(['tipo', 'item_id']);
            $t->index(['usuario_id', 'tipo', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proc_acessos');
    }
};
