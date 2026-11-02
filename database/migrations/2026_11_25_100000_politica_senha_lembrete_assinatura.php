<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Política de senha (expiração, troca obrigatória, histórico para não reutilizar) e lembrete automático
 * de assinatura pendente (periódico e na véspera do prazo; aviso ao solicitante quando o prazo vence).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('senha_alterada_em')->nullable();
            $t->boolean('senha_trocar')->default(false);     // nova senha obrigatória no próximo acesso
        });
        // Quem já existe começa a contar agora: ninguém expira no dia da implantação
        DB::table('users')->update(['senha_alterada_em' => now()]);

        Schema::create('ged_senhas_anteriores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('hash', 255);
            $t->timestamp('created_at')->useCurrent();
            $t->index(['user_id', 'id']);
        });

        Schema::table('ged_assinaturas', function (Blueprint $t) {
            $t->timestamp('lembrado_em')->nullable();
            $t->timestamp('lembrete_vespera_em')->nullable();
        });
        Schema::table('ged_solicitacoes_assinatura', function (Blueprint $t) {
            $t->timestamp('prazo_vencido_avisado_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ged_solicitacoes_assinatura', fn (Blueprint $t) => $t->dropColumn('prazo_vencido_avisado_em'));
        Schema::table('ged_assinaturas', fn (Blueprint $t) => $t->dropColumn(['lembrado_em', 'lembrete_vespera_em']));
        Schema::dropIfExists('ged_senhas_anteriores');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['senha_alterada_em', 'senha_trocar']));
    }
};
