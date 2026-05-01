<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tramitacao de memorandos — espelha o conceito do GPE legado (prot_tramite):
 * cada vez que alguem RECEBE um memorando e o ENCAMINHA para a frente,
 * cria-se uma linha aqui. A linha tem origem (quem encaminhou) e destino
 * (quem vai receber). A primeira linha so existe depois do primeiro despacho.
 *
 * Em seguida, refaz a VIEW proc_inbox para considerar:
 *   - Para cada memorando, se ha tramitacao(oes) a "ativa" (em_uso=true) define
 *     o destino atual; se nao ha tramitacao ainda, cai pros destinatarios
 *     originais (proc_memorando_destinatarios).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proc_memorando_tramitacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memorando_id')->constrained('proc_memorandos')->cascadeOnDelete();
            $table->foreignId('tramite_origem_id')->nullable()->constrained('proc_memorando_tramitacoes')->nullOnDelete();

            // Origem (quem encaminhou — sempre user)
            $table->foreignId('origem_usuario_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('origem_unidade_id')->nullable()->constrained('ug_organograma')->nullOnDelete();

            // Destino (usuario OU unidade do organograma)
            $table->foreignId('destino_usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('destino_unidade_id')->nullable()->constrained('ug_organograma')->nullOnDelete();

            $table->text('parecer')->nullable();
            $table->boolean('em_uso')->default(true);    // true = aguardando recebimento; false = ja foi tramitado pra frente
            $table->boolean('finalizado')->default(false); // true = recebido pelo destino

            $table->timestamp('despachado_em')->nullable();
            $table->timestamp('recebido_em')->nullable();
            $table->timestamps();

            $table->index(['memorando_id', 'em_uso']);
            $table->index('destino_usuario_id');
            $table->index('destino_unidade_id');
        });

        // Refaz proc_inbox para incluir tramitacoes de memorando
        DB::statement("DROP VIEW IF EXISTS proc_inbox");
        DB::statement(<<<'SQL'
            CREATE VIEW proc_inbox AS

            -- MEMORANDOS sem tramitacao ainda — destinos originais
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
              AND NOT EXISTS (SELECT 1 FROM proc_memorando_tramitacoes t WHERE t.memorando_id = m.id)

            UNION ALL

            -- MEMORANDOS com tramitacao — APENAS as ativas (em_uso=true)
            SELECT
                ('MT-' || m.id || '-' || t.id)::text AS id,
                'memorando'::text                    AS tipo,
                m.id                                 AS item_id,
                m.numero                             AS numero,
                m.assunto                            AS assunto,
                t.origem_usuario_id                  AS remetente_id,
                t.destino_usuario_id                 AS destino_usuario_id,
                t.destino_unidade_id                 AS destino_unidade_id,
                m.status                             AS status,
                m.confidencial                       AS confidencial,
                t.finalizado                         AS lido,
                t.recebido_em                        AS lido_em,
                t.created_at                         AS criado_em,
                t.despachado_em                      AS enviado_em,
                m.arquivado_em                       AS arquivado_em,
                m.ug_id                              AS ug_id
            FROM proc_memorandos m
            JOIN proc_memorando_tramitacoes t ON t.memorando_id = m.id
            WHERE m.deleted_at IS NULL
              AND t.em_uso = true

            UNION ALL

            -- OFICIOS INTERNOS
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

            -- PROCESSOS
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

        // Recria a view antiga (sem tramitacoes de memorando)
        DB::statement(<<<'SQL'
            CREATE VIEW proc_inbox AS
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

        Schema::dropIfExists('proc_memorando_tramitacoes');
    }
};
