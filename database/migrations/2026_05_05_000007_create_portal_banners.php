<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Banners do Portal Cidadao (carrossel — N banners por UG).
 * Substitui os campos inline em `ugs.banner_*` que ficam deprecated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_banners', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ug_id')->constrained('ugs')->cascadeOnDelete();
            $t->string('imagem_path', 255);
            $t->string('titulo', 200)->nullable();
            $t->text('subtitulo')->nullable();
            $t->string('link_url', 500)->nullable();
            $t->string('link_label', 60)->nullable();
            $t->integer('ordem')->default(0);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->index(['ug_id', 'ordem']);
        });

        // Migra banner inline (ugs.banner_*) para a nova tabela
        $ugsComBanner = DB::table('ugs')
            ->whereNotNull('banner_path')
            ->get(['id', 'banner_path', 'banner_titulo', 'banner_subtitulo', 'banner_link_url', 'banner_link_label', 'banner_ativo']);

        foreach ($ugsComBanner as $ug) {
            DB::table('portal_banners')->insert([
                'ug_id'        => $ug->id,
                'imagem_path'  => $ug->banner_path,
                'titulo'       => $ug->banner_titulo,
                'subtitulo'    => $ug->banner_subtitulo,
                'link_url'     => $ug->banner_link_url,
                'link_label'   => $ug->banner_link_label,
                'ordem'        => 0,
                'ativo'        => $ug->banner_ativo,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_banners');
    }
};
