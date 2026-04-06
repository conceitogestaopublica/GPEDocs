<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ged_documentos', function (Blueprint $table) {
            $table->uuid('qr_code_token')->nullable()->unique()->after('classificacao');
        });

        // Backfill existing documents
        DB::table('ged_documentos')->whereNull('qr_code_token')->get()->each(function ($doc) {
            DB::table('ged_documentos')->where('id', $doc->id)->update(['qr_code_token' => Str::uuid()]);
        });
    }

    public function down(): void
    {
        Schema::table('ged_documentos', function (Blueprint $table) {
            $table->dropColumn('qr_code_token');
        });
    }
};
