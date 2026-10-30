<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Endereços que pediram para não receber os avisos automáticos (link "deixar de receber" dos e-mails). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ged_email_descadastros', function (Blueprint $t) {
            $t->id();
            $t->string('email', 255)->unique();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ged_email_descadastros');
    }
};
