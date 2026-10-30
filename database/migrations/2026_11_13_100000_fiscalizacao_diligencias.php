<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiscalização de obras e posturas (TR 4.49): o processo de fiscalização gerado de outro (ex.:
 * denúncia da ouvidoria) guarda a origem; as diligências registram data, fiscal, constatação,
 * resultado, ponto no mapa e os anexos (fotos, plantas) juntados na hora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proc_processos', function (Blueprint $t) {
            $t->foreignId('processo_origem_id')->nullable()->constrained('proc_processos')->nullOnDelete();
        });
        Schema::create('proc_diligencias', function (Blueprint $t) {
            $t->id();
            $t->foreignId('processo_id')->constrained('proc_processos')->cascadeOnDelete();
            $t->foreignId('fiscal_id')->constrained('users');
            $t->timestamp('realizada_em');
            $t->string('resultado', 20);                 // regular | irregular | notificado | autuado | embargado | nao_localizado
            $t->text('constatacao');
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->jsonb('anexos')->nullable();             // ids de proc_anexos juntados na diligência
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proc_diligencias');
        Schema::table('proc_processos', fn (Blueprint $t) => $t->dropConstrainedForeignId('processo_origem_id'));
    }
};
