<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assinatura por link para signatário EXTERNO (cidadão, empresa, fornecedor) — quem não é
 * usuário do GED recebe um link, faz o cadastro (portal_cidadaos) e assina pela página
 * pública /assinar/{token}.
 *
 * O signatário externo não tem linha em `users`: signatario_id passa a aceitar null e a
 * identidade fica em nome/CPF/e-mail do convite + o cidadão que aceitou (cidadao_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ged_assinaturas', function (Blueprint $table) {
            $table->unsignedBigInteger('signatario_id')->nullable()->change();
            $table->string('email_signatario', 255)->nullable()->change();

            $table->string('tipo_signatario', 10)->default('interno'); // interno | externo
            $table->string('nome_signatario', 200)->nullable();
            $table->string('telefone_signatario', 30)->nullable();
            $table->foreignId('cidadao_id')->nullable()
                ->constrained('portal_cidadaos', indexName: 'ged_assinaturas_x_portal_cidadaos_X_cidadao_id');

            // Convite: o hash localiza o convite pelo link; o token cifrado permite mostrar
            // o link de novo ao solicitante ("copiar link").
            $table->string('convite_token_hash', 64)->nullable()->unique();
            $table->text('convite_token')->nullable();
            $table->timestamp('convite_expira_em')->nullable();
            $table->timestamp('convite_enviado_em')->nullable();
            $table->timestamp('convite_acessado_em')->nullable();

            // Código impresso no carimbo — único por assinatura, consultável em /verificar.
            $table->string('codigo_verificacao', 20)->nullable()->unique();
        });

        Schema::table('portal_cidadaos', function (Blueprint $table) {
            // Confirmação do e-mail por código de 6 dígitos (válido por alguns minutos).
            $table->timestamp('token_verificacao_expira_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('portal_cidadaos', function (Blueprint $table) {
            $table->dropColumn('token_verificacao_expira_em');
        });

        Schema::table('ged_assinaturas', function (Blueprint $table) {
            $table->dropForeign('ged_assinaturas_x_portal_cidadaos_X_cidadao_id');
            $table->dropUnique(['convite_token_hash']);
            $table->dropUnique(['codigo_verificacao']);
            $table->dropColumn([
                'tipo_signatario', 'nome_signatario', 'telefone_signatario', 'cidadao_id',
                'convite_token_hash', 'convite_token', 'convite_expira_em', 'convite_enviado_em',
                'convite_acessado_em', 'codigo_verificacao',
            ]);
        });
    }
};
