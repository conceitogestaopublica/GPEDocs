<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificado da prefeitura (e-CNPJ A1) por UG — sela os PDFs assinados por link: o
 * arquivo sai assinado digitalmente pela prefeitura e qualquer alteração invalida o selo.
 *
 * O .pfx e a senha ficam cifrados com a APP_KEY (cast `encrypted`); nunca em texto puro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ged_certificados_institucionais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ug_id')->unique()
                ->constrained('ugs', indexName: 'ged_certificados_institucionais_x_ugs_X_ug_id');
            $table->text('pfx');    // base64 do .pfx, cifrado
            $table->text('senha');  // cifrada
            $table->string('subject_cn', 255);
            $table->string('cnpj', 14)->nullable();
            $table->string('issuer_cn', 255)->nullable();
            $table->string('serial_number', 80)->nullable();
            $table->string('thumbprint_sha256', 64);
            $table->timestamp('valido_de');
            $table->timestamp('valido_ate');
            $table->boolean('icp_brasil')->default(false);
            $table->boolean('ativo')->default(true);
            $table->foreignId('enviado_por')->nullable()
                ->constrained('users', indexName: 'ged_certificados_institucionais_x_users_X_enviado_por');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ged_certificados_institucionais');
    }
};
