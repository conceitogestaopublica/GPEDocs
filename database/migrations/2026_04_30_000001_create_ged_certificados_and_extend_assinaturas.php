<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cpf', 14)->nullable()->after('email');
        });

        Schema::create('ged_certificados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo', 5);                  // A1, A3
            $table->string('subject_cn', 255);          // Common Name (titular)
            $table->string('subject_cpf', 14)->nullable();
            $table->string('subject_dn', 1000);         // DN completo
            $table->string('issuer_cn', 255);           // AC emissora
            $table->string('issuer_dn', 1000);
            $table->string('serial_number', 80);
            $table->string('thumbprint_sha1', 40);
            $table->string('thumbprint_sha256', 64);
            $table->timestamp('valido_de');
            $table->timestamp('valido_ate');
            $table->text('certificado_pem');            // certificado publico (sem chave privada)
            $table->json('cadeia_pem')->nullable();     // certificados intermediarios da cadeia
            $table->string('politica_oid', 80)->nullable();
            $table->boolean('icp_brasil')->default(true);
            $table->boolean('revogado')->default(false);
            $table->timestamp('verificado_em')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'thumbprint_sha256']);
            $table->index('subject_cpf');
        });

        Schema::table('ged_assinaturas', function (Blueprint $table) {
            $table->string('tipo_assinatura', 20)->default('simples')->after('status');
            // simples (Lei 14.063/2020 art. 4, I)
            // avancada (art. 4, II)
            // qualificada (art. 4, III - ICP-Brasil)

            $table->foreignId('certificado_id')->nullable()->after('tipo_assinatura')
                ->constrained('ged_certificados')->nullOnDelete();

            $table->binary('assinatura_pkcs7')->nullable()->after('hash_documento');
            $table->json('cadeia_certificados')->nullable()->after('assinatura_pkcs7');
            $table->string('politica_assinatura', 120)->nullable()->after('cadeia_certificados');
            $table->string('algoritmo_hash', 20)->nullable()->after('politica_assinatura');
            $table->string('arquivo_assinado_path', 500)->nullable()->after('algoritmo_hash');
            $table->string('hash_assinatura_sha256', 64)->nullable()->after('arquivo_assinado_path');
            $table->timestamp('timestamp_assinatura')->nullable()->after('hash_assinatura_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('ged_assinaturas', function (Blueprint $table) {
            $table->dropForeign(['certificado_id']);
            $table->dropColumn([
                'tipo_assinatura',
                'certificado_id',
                'assinatura_pkcs7',
                'cadeia_certificados',
                'politica_assinatura',
                'algoritmo_hash',
                'arquivo_assinado_path',
                'hash_assinatura_sha256',
                'timestamp_assinatura',
            ]);
        });

        Schema::dropIfExists('ged_certificados');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('cpf');
        });
    }
};
