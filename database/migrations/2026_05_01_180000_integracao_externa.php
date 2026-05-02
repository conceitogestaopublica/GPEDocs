<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integracao com sistemas externos (GPE, RH, Patrimonio, etc) que enviam
 * documentos para assinatura digital + arquivamento no GPE Docs.
 *
 * - ged_sistemas_integrados: cadastro dos sistemas com API token (hashed).
 * - ged_documentos.sistema_origem / numero_externo / metadados_externos /
 *   callback_url / callback_executado: rastreabilidade do documento que
 *   veio de fora, mais o webhook de retorno quando todas as assinaturas
 *   forem concluidas.
 * - ged_tipos_documentais.sistema_origem: opcional, identifica que o tipo
 *   e usado por um sistema especifico (ex: "Empenho" -> "gpe").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ged_sistemas_integrados', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();        // ex: "gpe"
            $table->string('nome', 200);                   // "GPE - Sistema de Gestao Publica"
            $table->text('descricao')->nullable();
            $table->string('api_token_hash', 255);         // sha256(token) — token e retornado UMA VEZ no cadastro
            $table->string('api_token_prefix', 12);        // primeiros chars do token (pra exibir mascarado)
            $table->boolean('ativo')->default(true);
            $table->timestamp('ultimo_uso_em')->nullable();
            $table->timestamps();
        });

        Schema::table('ged_documentos', function (Blueprint $table) {
            $table->string('sistema_origem', 50)->nullable()->after('autor_id');
            $table->string('numero_externo', 100)->nullable()->after('sistema_origem');
            $table->json('metadados_externos')->nullable()->after('numero_externo');
            $table->string('callback_url', 500)->nullable()->after('metadados_externos');
            $table->boolean('callback_executado')->default(false)->after('callback_url');
            $table->timestamp('callback_executado_em')->nullable()->after('callback_executado');

            $table->index(['sistema_origem', 'numero_externo']);
        });

        Schema::table('ged_tipos_documentais', function (Blueprint $table) {
            $table->string('sistema_origem', 50)->nullable()->after('schema_metadados');
        });
    }

    public function down(): void
    {
        Schema::table('ged_tipos_documentais', function (Blueprint $table) {
            $table->dropColumn('sistema_origem');
        });

        Schema::table('ged_documentos', function (Blueprint $table) {
            $table->dropIndex(['sistema_origem', 'numero_externo']);
            $table->dropColumn(['sistema_origem', 'numero_externo', 'metadados_externos',
                'callback_url', 'callback_executado', 'callback_executado_em']);
        });

        Schema::dropIfExists('ged_sistemas_integrados');
    }
};
