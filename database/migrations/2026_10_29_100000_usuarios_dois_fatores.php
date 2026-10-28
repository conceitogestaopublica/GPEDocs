<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verificação em duas etapas por aplicativo autenticador (TOTP, RFC 6238).
 * Segredo e códigos de recuperação ficam cifrados (APP_KEY); o último passo
 * de 30 s aceito impede reaproveitar o mesmo código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->text('dois_fatores_segredo')->nullable();
            $t->text('dois_fatores_recuperacao')->nullable();
            $t->timestamp('dois_fatores_ativado_em')->nullable();
            $t->bigInteger('dois_fatores_ultimo_passo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['dois_fatores_segredo', 'dois_fatores_recuperacao', 'dois_fatores_ativado_em', 'dois_fatores_ultimo_passo']);
        });
    }
};
