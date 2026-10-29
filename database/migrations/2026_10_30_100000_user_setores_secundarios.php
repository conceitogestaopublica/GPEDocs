<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Setores secundários do servidor (TR 4.9.2): o principal segue em
 * users.unidade_id; aqui os demais setores em que ele também atende.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_setores', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('unidade_id')->constrained('ug_organograma')->cascadeOnDelete();
            $t->timestamps();
            $t->primary(['user_id', 'unidade_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_setores');
    }
};
