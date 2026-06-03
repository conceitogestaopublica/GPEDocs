<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ug_id')->nullable()->constrained('ugs', indexName: 'chat_mensagens_x_ugs_X_ug_id');
            $table->foreignId('remetente_id')->constrained('users', indexName: 'chat_mensagens_x_users_X_remetente_id');
            $table->foreignId('destinatario_id')->constrained('users', indexName: 'chat_mensagens_x_users_X_destinatario_id');
            $table->text('conteudo');
            $table->timestamp('lida_em')->nullable();
            $table->timestamps();

            $table->index(['destinatario_id', 'lida_em']);
            $table->index(['ug_id', 'remetente_id', 'destinatario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_mensagens');
    }
};
