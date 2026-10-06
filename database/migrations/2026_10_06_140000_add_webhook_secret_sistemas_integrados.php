<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O modelo SistemaIntegrado grava o segredo HMAC dos webhooks em `webhook_secret`, mas
 * nenhuma migration criava a coluna (a de webhooks só a citava em ->after(), que o
 * PostgreSQL ignora). As bases que vieram de dump já a têm; um ente novo criado só por
 * migrations quebrava ao cadastrar o primeiro sistema integrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ged_sistemas_integrados', 'webhook_secret')) {
            Schema::table('ged_sistemas_integrados', function (Blueprint $table) {
                $table->string('webhook_secret', 255)->nullable(); // mesmo tipo das bases de produção
            });
        }
    }

    public function down(): void
    {
        // Não remove: nas bases vindas de dump a coluna é anterior a esta migration.
    }
};
