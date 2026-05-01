<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unifica o conceito de "destino" entre os 4 tipos do GPE Flow
 * (memorando, circular, oficio, processo) para permitir a Inbox unificada
 * (Pessoal e do Setor).
 *
 * Campos novos:
 *   - proc_oficios.destinatario_usuario_id        (oficio interno para usuario)
 *   - proc_oficios.destinatario_unidade_id        (oficio interno para unidade)
 *   - proc_oficios.lido_em
 *   - proc_memorando_destinatarios.unidade_id     (memorando para unidade)
 *   - proc_tramitacoes.destino_unidade_id         (processo para unidade)
 *   - proc_tramitacoes.lida_em
 *
 * Em seguida cria a VIEW SQL proc_inbox unificada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proc_oficios', function (Blueprint $table) {
            $table->foreignId('destinatario_usuario_id')->nullable()->after('remetente_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('destinatario_unidade_id')->nullable()->after('destinatario_usuario_id')
                ->constrained('ug_organograma')->nullOnDelete();
            // proc_oficios ja tem lido_em — pulo se ja existir
            if (! Schema::hasColumn('proc_oficios', 'lido_em_interno')) {
                $table->timestamp('lido_em_interno')->nullable()->after('lido_em');
            }
        });

        Schema::table('proc_memorando_destinatarios', function (Blueprint $table) {
            $table->foreignId('unidade_id')->nullable()->after('usuario_id')
                ->constrained('ug_organograma')->nullOnDelete();
        });

        Schema::table('proc_tramitacoes', function (Blueprint $table) {
            $table->foreignId('destino_unidade_id')->nullable()->after('destinatario_id')
                ->constrained('ug_organograma')->nullOnDelete();
            $table->timestamp('lida_em')->nullable()->after('recebido_em');
        });

        // VIEW SQL unificada — itens encaminhados que cada user pode ver na inbox.
        // Cada linha = um "item" potencial da inbox.
        DB::statement("DROP VIEW IF EXISTS proc_inbox");
        DB::statement(<<<'SQL'
            CREATE VIEW proc_inbox AS

            -- MEMORANDOS (uma linha por destinatario)
            SELECT
                ('M-' || m.id || '-' || d.id)::text AS id,
                'memorando'::text                  AS tipo,
                m.id                               AS item_id,
                m.numero                           AS numero,
                m.assunto                          AS assunto,
                m.remetente_id                     AS remetente_id,
                d.usuario_id                       AS destino_usuario_id,
                d.unidade_id                       AS destino_unidade_id,
                m.status                           AS status,
                m.confidencial                     AS confidencial,
                d.lido                             AS lido,
                d.lido_em                          AS lido_em,
                m.created_at                       AS criado_em,
                m.enviado_em                       AS enviado_em,
                m.arquivado_em                     AS arquivado_em,
                m.ug_id                            AS ug_id
            FROM proc_memorandos m
            JOIN proc_memorando_destinatarios d ON d.memorando_id = m.id
            WHERE m.deleted_at IS NULL

            UNION ALL

            -- OFICIOS INTERNOS (com destinatario_usuario_id ou destinatario_unidade_id)
            SELECT
                ('O-' || o.id)::text                AS id,
                'oficio'::text                      AS tipo,
                o.id                                AS item_id,
                o.numero                            AS numero,
                o.assunto                           AS assunto,
                o.remetente_id                      AS remetente_id,
                o.destinatario_usuario_id           AS destino_usuario_id,
                o.destinatario_unidade_id           AS destino_unidade_id,
                o.status                            AS status,
                false                               AS confidencial,
                (o.lido_em_interno IS NOT NULL)     AS lido,
                o.lido_em_interno                   AS lido_em,
                o.created_at                        AS criado_em,
                o.enviado_em                        AS enviado_em,
                o.arquivado_em                      AS arquivado_em,
                o.ug_id                             AS ug_id
            FROM proc_oficios o
            WHERE o.deleted_at IS NULL
              AND (o.destinatario_usuario_id IS NOT NULL OR o.destinatario_unidade_id IS NOT NULL)

            UNION ALL

            -- PROCESSOS (uma linha pela ULTIMA tramitacao ativa de cada processo)
            SELECT
                ('P-' || p.id || '-' || t.id)::text AS id,
                'processo'::text                    AS tipo,
                p.id                                AS item_id,
                p.numero_protocolo                  AS numero,
                p.assunto                           AS assunto,
                t.remetente_id                      AS remetente_id,
                t.destinatario_id                   AS destino_usuario_id,
                t.destino_unidade_id                AS destino_unidade_id,
                p.status                            AS status,
                false                               AS confidencial,
                (t.lida_em IS NOT NULL)             AS lido,
                t.lida_em                           AS lido_em,
                t.created_at                        AS criado_em,
                t.despachado_em                     AS enviado_em,
                NULL::timestamp                     AS arquivado_em,
                p.ug_id                             AS ug_id
            FROM proc_processos p
            JOIN proc_tramitacoes t
                ON t.id = (SELECT MAX(t2.id) FROM proc_tramitacoes t2 WHERE t2.processo_id = p.id)
            WHERE p.deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS proc_inbox");

        Schema::table('proc_tramitacoes', function (Blueprint $table) {
            $table->dropForeign(['destino_unidade_id']);
            $table->dropColumn(['destino_unidade_id', 'lida_em']);
        });

        Schema::table('proc_memorando_destinatarios', function (Blueprint $table) {
            $table->dropForeign(['unidade_id']);
            $table->dropColumn('unidade_id');
        });

        Schema::table('proc_oficios', function (Blueprint $table) {
            $table->dropForeign(['destinatario_usuario_id']);
            $table->dropForeign(['destinatario_unidade_id']);
            $table->dropColumn(['destinatario_usuario_id', 'destinatario_unidade_id', 'lido_em_interno']);
        });
    }
};
