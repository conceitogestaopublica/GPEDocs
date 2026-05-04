<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_servicos', function (Blueprint $t) {
            $t->boolean('permite_anonimo')->default(false)->after('publico_alvo');
            $t->foreignId('setor_responsavel_id')->nullable()->after('orgao_responsavel')
                ->constrained('ug_organograma')->nullOnDelete();
            $t->foreignId('tipo_processo_id')->nullable()->after('setor_responsavel_id')
                ->constrained('proc_tipos_processo')->nullOnDelete();
        });

        Schema::table('portal_solicitacoes', function (Blueprint $t) {
            $t->boolean('anonima')->default(false)->after('cidadao_id');
            $t->foreignId('processo_id')->nullable()->after('atendente_id')
                ->constrained('proc_processos')->nullOnDelete();
            $t->index('processo_id');
        });

        // Cidadao_id pode ser null em solicitacoes anonimas
        DB::statement('ALTER TABLE portal_solicitacoes ALTER COLUMN cidadao_id DROP NOT NULL');

        // aberto_por nullable em proc_processos (para processos abertos pelo Portal Cidadao)
        DB::statement('ALTER TABLE proc_processos ALTER COLUMN aberto_por DROP NOT NULL');

        // remetente_id nullable em proc_tramitacoes (primeira tramitacao vem do portal sem servidor remetente)
        DB::statement('ALTER TABLE proc_tramitacoes ALTER COLUMN remetente_id DROP NOT NULL');
    }

    public function down(): void
    {
        Schema::table('portal_solicitacoes', function (Blueprint $t) {
            $t->dropForeign(['processo_id']);
            $t->dropIndex(['processo_id']);
            $t->dropColumn(['anonima', 'processo_id']);
        });

        Schema::table('portal_servicos', function (Blueprint $t) {
            $t->dropForeign(['setor_responsavel_id']);
            $t->dropForeign(['tipo_processo_id']);
            $t->dropColumn(['permite_anonimo', 'setor_responsavel_id', 'tipo_processo_id']);
        });

        DB::statement('ALTER TABLE portal_solicitacoes ALTER COLUMN cidadao_id SET NOT NULL');
        DB::statement('ALTER TABLE proc_processos ALTER COLUMN aberto_por SET NOT NULL');
        DB::statement('ALTER TABLE proc_tramitacoes ALTER COLUMN remetente_id SET NOT NULL');
    }
};
