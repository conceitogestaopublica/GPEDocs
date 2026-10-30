<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expediente do município (TR 4.13.9–4.13.11): dias úteis da semana, horário de
 * funcionamento, feriados e pontos facultativos. Com ele o prazo automático do
 * assunto pode contar só dias úteis (proc_tipos_processo.prazo_dias_uteis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expediente', function (Blueprint $t) {
            $t->id();
            $t->jsonb('dias_semana');               // ISO: 1 = segunda … 7 = domingo
            $t->time('hora_inicio');
            $t->time('hora_fim');
            $t->timestamps();
        });
        DB::table('expediente')->insert([
            'dias_semana' => json_encode([1, 2, 3, 4, 5]),
            'hora_inicio' => '08:00', 'hora_fim' => '17:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::create('expediente_feriados', function (Blueprint $t) {
            $t->id();
            $t->date('data')->unique();
            $t->string('nome', 150);
            $t->string('tipo', 20)->default('feriado');   // feriado | ponto_facultativo
            $t->timestamps();
        });

        Schema::table('proc_tipos_processo', function (Blueprint $t) {
            $t->boolean('prazo_dias_uteis')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('proc_tipos_processo', function (Blueprint $t) {
            $t->dropColumn('prazo_dias_uteis');
        });
        Schema::dropIfExists('expediente_feriados');
        Schema::dropIfExists('expediente');
    }
};
