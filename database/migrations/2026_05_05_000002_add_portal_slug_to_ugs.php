<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ugs', function (Blueprint $t) {
            $t->string('portal_slug', 80)->nullable()->after('codigo');
        });

        $ugs = DB::table('ugs')->get();
        $usados = [];
        foreach ($ugs as $ug) {
            $base = Str::slug(strtolower((string) $ug->codigo));
            $slug = $base;
            $i = 2;
            while (in_array($slug, $usados, true)) {
                $slug = $base.'-'.$i++;
            }
            $usados[] = $slug;
            DB::table('ugs')->where('id', $ug->id)->update(['portal_slug' => $slug]);
        }

        Schema::table('ugs', function (Blueprint $t) {
            $t->unique('portal_slug');
        });
    }

    public function down(): void
    {
        Schema::table('ugs', function (Blueprint $t) {
            $t->dropUnique(['portal_slug']);
            $t->dropColumn('portal_slug');
        });
    }
};
