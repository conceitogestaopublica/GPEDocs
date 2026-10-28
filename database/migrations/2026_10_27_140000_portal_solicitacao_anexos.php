<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Arquivos enviados pelo cidadão com a solicitação do portal (ex.: atestado médico). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_solicitacao_anexos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('solicitacao_id')->constrained('portal_solicitacoes')->cascadeOnDelete();
            $t->string('nome', 200);
            $t->string('arquivo_path', 500);
            $t->unsignedBigInteger('tamanho');
            $t->string('mime_type', 100);
            $t->string('hash_sha256', 64);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_solicitacao_anexos');
    }
};
