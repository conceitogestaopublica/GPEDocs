<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tramitação multissetorial e envio em cópia (TR 4.9.4, 4.9.5, 4.9.14).
 *
 *  - frente: 1 = fluxo principal; 2, 3… = outros setores que tramitam a sua parte
 *    em paralelo. Cada etapa herda a frente de onde veio.
 *  - modo: 'tramite' ou 'copia' (o setor só toma ciência; o processo não espera).
 *  - estagio / resolvido_*: o setor marca em que pé está e resolve a parte dele.
 *
 * proc_inbox passa a ter uma linha por ETAPA ATIVA do processo (cada setor vê a
 * sua) — antes era só a última tramitação. Sem etapa ativa, segue a última.
 * Coluna nova `copia` no fim (as outras origens: false).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proc_tramitacoes', function (Blueprint $t) {
            $t->smallInteger('frente')->default(1);
            $t->string('modo', 10)->default('tramite');
            $t->string('estagio', 60)->nullable();
            $t->timestamp('resolvido_em')->nullable();
            $t->foreignId('resolvido_por')->nullable()->constrained('users', indexName: 'proc_tramitacoes_x_users_X_resolvido_por');
            $t->index(['processo_id', 'status']);
        });

        $this->criarView(<<<'SQL'
            FROM proc_processos p
            JOIN proc_tramitacoes t ON t.processo_id = p.id
            WHERE p.deleted_at IS NULL
              AND (
                    t.status IN ('pendente', 'recebido')
                 OR (t.id = (SELECT MAX(t2.id) FROM proc_tramitacoes t2 WHERE t2.processo_id = p.id AND t2.modo = 'tramite')
                     AND NOT EXISTS (SELECT 1 FROM proc_tramitacoes t3
                                     WHERE t3.processo_id = p.id AND t3.modo = 'tramite' AND t3.status IN ('pendente', 'recebido')))
              )
        SQL, "(t.modo = 'copia')");
    }

    public function down(): void
    {
        $this->criarView(<<<'SQL'
            FROM proc_processos p
            JOIN proc_tramitacoes t
                ON t.id = (SELECT MAX(t2.id) FROM proc_tramitacoes t2 WHERE t2.processo_id = p.id)
            WHERE p.deleted_at IS NULL
        SQL, null);

        Schema::table('proc_tramitacoes', function (Blueprint $t) {
            $t->dropIndex(['processo_id', 'status']);
            $t->dropForeign('proc_tramitacoes_x_users_X_resolvido_por');
            $t->dropColumn(['frente', 'modo', 'estagio', 'resolvido_em', 'resolvido_por']);
        });
    }

    /** Recria proc_inbox trocando só a origem "processos"; com $copia, acrescenta a coluna. */
    private function criarView(string $origemProcessos, ?string $copia): void
    {
        $col = fn (string $expr) => $copia !== null ? ",\n                {$expr} AS copia" : '';

        DB::statement('DROP VIEW IF EXISTS proc_inbox');
        DB::statement(<<<SQL
            CREATE VIEW proc_inbox AS

            SELECT
                ('M-' || m.id || '-' || d.id)::text AS id,
                'memorando'::text AS tipo, m.id AS item_id, m.numero AS numero, m.assunto AS assunto,
                m.remetente_id AS remetente_id, d.usuario_id AS destino_usuario_id, d.unidade_id AS destino_unidade_id,
                m.status AS status, m.confidencial AS confidencial, d.lido AS lido, d.lido_em AS lido_em,
                m.created_at AS criado_em, m.enviado_em AS enviado_em, m.arquivado_em AS arquivado_em,
                m.ug_id AS ug_id{$col('false')}
            FROM proc_memorandos m
            JOIN proc_memorando_destinatarios d ON d.memorando_id = m.id
            WHERE m.deleted_at IS NULL
              AND NOT EXISTS (SELECT 1 FROM proc_memorando_tramitacoes t WHERE t.memorando_id = m.id)

            UNION ALL

            SELECT
                ('MT-' || m.id || '-' || t.id)::text AS id,
                'memorando'::text AS tipo, m.id AS item_id, m.numero AS numero, m.assunto AS assunto,
                t.origem_usuario_id AS remetente_id, t.destino_usuario_id AS destino_usuario_id, t.destino_unidade_id AS destino_unidade_id,
                m.status AS status, m.confidencial AS confidencial, t.finalizado AS lido, t.recebido_em AS lido_em,
                t.created_at AS criado_em, t.despachado_em AS enviado_em, m.arquivado_em AS arquivado_em,
                m.ug_id AS ug_id{$col('false')}
            FROM proc_memorandos m
            JOIN proc_memorando_tramitacoes t ON t.memorando_id = m.id
            WHERE m.deleted_at IS NULL
              AND t.em_uso = true

            UNION ALL

            SELECT
                ('O-' || o.id)::text AS id,
                'oficio'::text AS tipo, o.id AS item_id, o.numero AS numero, o.assunto AS assunto,
                o.remetente_id AS remetente_id, o.destinatario_usuario_id AS destino_usuario_id, o.destinatario_unidade_id AS destino_unidade_id,
                o.status AS status, false AS confidencial, (o.lido_em_interno IS NOT NULL) AS lido, o.lido_em_interno AS lido_em,
                o.created_at AS criado_em, o.enviado_em AS enviado_em, o.arquivado_em AS arquivado_em,
                o.ug_id AS ug_id{$col('false')}
            FROM proc_oficios o
            WHERE o.deleted_at IS NULL
              AND (o.destinatario_usuario_id IS NOT NULL OR o.destinatario_unidade_id IS NOT NULL)

            UNION ALL

            SELECT
                ('P-' || p.id || '-' || t.id)::text AS id,
                'processo'::text AS tipo, p.id AS item_id, p.numero_protocolo AS numero, p.assunto AS assunto,
                t.remetente_id AS remetente_id, t.destinatario_id AS destino_usuario_id, t.destino_unidade_id AS destino_unidade_id,
                p.status AS status, false AS confidencial, (t.lida_em IS NOT NULL) AS lido, t.lida_em AS lido_em,
                t.created_at AS criado_em, t.despachado_em AS enviado_em, NULL::timestamp AS arquivado_em,
                p.ug_id AS ug_id{$col($copia ?? 'false')}
            {$origemProcessos}
        SQL);
    }
};
