<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cidadão do portal que é também usuário do GPE Docs (servidor, funcionário): uma pessoa,
 * uma senha. Com user_id, o portal confere a senha do usuário — trocar lá vale aqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_cidadaos', function (Blueprint $t) {
            $t->foreignId('user_id')->nullable()->unique()->after('id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('portal_cidadaos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('user_id');
        });
    }
};
