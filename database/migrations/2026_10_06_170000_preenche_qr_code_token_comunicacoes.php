<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Memorando, ofício e circular geram o token do QR na criação pelo modelo; registros
 * inseridos direto no banco (seeders, importações) podem ter ficado sem — e o PDF sairia
 * com um QR para /…/verificar/ vazio.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['proc_memorandos', 'proc_oficios', 'proc_circulares'] as $tabela) {
            DB::table($tabela)->whereNull('qr_code_token')->orderBy('id')->lazyById()
                ->each(fn ($r) => DB::table($tabela)->where('id', $r->id)->update(['qr_code_token' => (string) \Illuminate\Support\Str::uuid()]));
        }
    }

    public function down(): void
    {
        // Tokens preenchidos já podem estar impressos em PDFs: não são removidos.
    }
};
