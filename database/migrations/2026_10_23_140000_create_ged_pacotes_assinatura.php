<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pacote de assinatura para signatário EXTERNO: vários documentos, um link só por pessoa
 * (/assinar/pacote/{token}) — em vez de um e-mail e um link por documento.
 *
 * O pacote nasce da solicitação em lote (tela) ou da API com o campo `pacote` (o gpe2 manda
 * um empenho por chamada; o mesmo código de pacote agrupa todos). Cada documento continua
 * com a própria Assinatura (carimbo, termo, código e selo próprios).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ged_pacotes_assinatura', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 200);
            $table->foreignId('solicitante_id')->constrained('users', indexName: 'ged_pacotes_assinatura_x_users_X_solicitante_id');
            $table->string('sistema_origem', 50)->nullable();   // API: código do sistema integrado
            $table->string('codigo_externo', 100)->nullable();  // API: campo `pacote`
            $table->timestamps();

            $table->unique(['sistema_origem', 'codigo_externo']);
        });

        Schema::create('ged_pacote_convites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pacote_id')->constrained('ged_pacotes_assinatura', indexName: 'ged_pacote_convites_x_ged_pacotes_assinatura_X_pacote_id')->cascadeOnDelete();
            $table->string('cpf', 11);
            $table->string('nome', 200);
            $table->string('email', 150)->nullable();
            $table->string('telefone', 30)->nullable();
            $table->string('token_hash', 64)->unique();
            $table->text('token');                       // cifrado (cast encrypted) — "copiar link"
            $table->timestamp('expira_em')->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->timestamp('acessado_em')->nullable();
            $table->timestamps();

            $table->unique(['pacote_id', 'cpf']);
        });

        Schema::table('ged_assinaturas', function (Blueprint $table) {
            $table->foreignId('pacote_convite_id')->nullable()
                ->constrained('ged_pacote_convites', indexName: 'ged_assinaturas_x_ged_pacote_convites_X_pacote_convite_id')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ged_assinaturas', function (Blueprint $table) {
            $table->dropForeign('ged_assinaturas_x_ged_pacote_convites_X_pacote_convite_id');
            $table->dropColumn('pacote_convite_id');
        });
        Schema::dropIfExists('ged_pacote_convites');
        Schema::dropIfExists('ged_pacotes_assinatura');
    }
};
