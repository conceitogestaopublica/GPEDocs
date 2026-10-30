<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contrassenha (TR 4.18): o autorizador gera uma senha de uso limitado para
 * um servidor (digitador) redigir e enviar ofício, memorando ou circular em
 * nome dele. O documento guarda quem digitou e por qual contrassenha.
 */
return new class extends Migration
{
    private const DOCUMENTOS = ['proc_oficios', 'proc_memorandos', 'proc_circulares'];

    public function up(): void
    {
        Schema::create('ged_contrassenhas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('autorizador_id')->constrained('users', indexName: 'ged_contrassenhas_x_users_X_autorizador_id');
            $t->foreignId('digitador_id')->constrained('users', indexName: 'ged_contrassenhas_x_users_X_digitador_id');
            $t->string('codigo_hash');
            $t->string('final', 4);                 // últimos 4 caracteres, para reconhecer na lista
            $t->unsignedInteger('usos_max');
            $t->unsignedInteger('usos')->default(0);
            $t->timestamp('valida_ate');
            $t->timestamp('cancelada_em')->nullable();
            $t->string('observacao', 300)->nullable();
            $t->timestamps();
            $t->index(['digitador_id', 'autorizador_id']);
        });

        foreach (self::DOCUMENTOS as $tabela) {
            Schema::table($tabela, function (Blueprint $t) {
                $t->unsignedBigInteger('digitador_id')->nullable();
                $t->unsignedBigInteger('contrassenha_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::DOCUMENTOS as $tabela) {
            Schema::table($tabela, fn (Blueprint $t) => $t->dropColumn(['digitador_id', 'contrassenha_id']));
        }
        Schema::dropIfExists('ged_contrassenhas');
    }
};
