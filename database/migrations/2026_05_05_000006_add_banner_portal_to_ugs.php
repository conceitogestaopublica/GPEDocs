<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banner promocional do Portal do Cidadao (1 por UG).
 * Aparece na home do portal entre a busca e as categorias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ugs', function (Blueprint $t) {
            $t->string('banner_path', 255)->nullable()->after('brasao_path');
            $t->string('banner_titulo', 200)->nullable()->after('banner_path');
            $t->text('banner_subtitulo')->nullable()->after('banner_titulo');
            $t->string('banner_link_url', 500)->nullable()->after('banner_subtitulo');
            $t->string('banner_link_label', 60)->nullable()->after('banner_link_url');
            // `banner_ativo` ja e criado na migration base (create_ugs_and_organograma).
        });
    }

    public function down(): void
    {
        Schema::table('ugs', function (Blueprint $t) {
            $t->dropColumn(['banner_path', 'banner_titulo', 'banner_subtitulo', 'banner_link_url', 'banner_link_label']);
        });
    }
};
