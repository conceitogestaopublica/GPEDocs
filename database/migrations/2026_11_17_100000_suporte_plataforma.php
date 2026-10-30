<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Suporte na plataforma (TR 4.26.1–10): central de ajuda (assuntos e artigos para autoatendimento),
 * atendentes com nível e presença, atendimentos numerados com participantes, mensagens (inclusive notas
 * internas e eventos do sistema), escalonamento até chamado da TI, avaliação e reabertura por e-mail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sup_assuntos', function (Blueprint $t) {
            $t->id();
            $t->string('nome', 120);
            $t->string('descricao', 300)->nullable();
            $t->unsignedSmallInteger('ordem')->default(0);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('sup_artigos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assunto_id')->constrained('sup_assuntos')->cascadeOnDelete();
            $t->string('titulo', 200);
            $t->string('resumo', 400)->nullable();
            $t->text('conteudo');                       // HTML saneado
            $t->boolean('publicado')->default(true);
            $t->unsignedInteger('visualizacoes')->default(0);
            $t->unsignedInteger('resolveu')->default(0);       // "isso resolveu meu problema"
            $t->unsignedInteger('nao_resolveu')->default(0);
            $t->timestamps();
        });
        Schema::create('sup_atendentes', function (Blueprint $t) {
            $t->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $t->unsignedTinyInteger('nivel')->default(1);      // 1 = primeiro atendimento, 2 = especialista, 3 = TI
            $t->boolean('disponivel')->default(true);
            $t->timestamp('visto_em')->nullable();             // presença: o painel do atendente avisa a cada minuto
            $t->timestamps();
        });
        Schema::create('sup_atendimentos', function (Blueprint $t) {
            $t->id();
            $t->string('numero', 20)->unique();                // SUP-2026/000001
            $t->foreignId('assunto_id')->nullable()->constrained('sup_assuntos')->nullOnDelete();
            $t->string('titulo', 200);
            $t->string('status', 20)->default('aberto');       // aberto | em_atendimento | resolvido | fechado | cancelado
            $t->unsignedTinyInteger('nivel')->default(1);
            $t->foreignId('solicitante_id')->constrained('users');
            $t->foreignId('atendente_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('canal', 10)->default('chat');          // chat | email
            $t->foreignId('processo_id')->nullable()->constrained('proc_processos')->nullOnDelete();   // chamado da TI (nível 3)
            $t->unsignedTinyInteger('avaliacao_nota')->nullable();
            $t->text('avaliacao_comentario')->nullable();
            $t->timestamp('avaliado_em')->nullable();
            $t->uuid('token')->unique();                       // link do e-mail (avaliar / reabrir)
            $t->timestamp('primeira_resposta_em')->nullable();
            $t->timestamp('resolvido_em')->nullable();
            $t->timestamp('fechado_em')->nullable();
            $t->unsignedSmallInteger('reaberturas')->default(0);
            $t->timestamps();
            $t->index(['status', 'nivel']);
            $t->index(['solicitante_id', 'status']);
        });
        Schema::create('sup_participantes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('atendimento_id')->constrained('sup_atendimentos')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('papel', 15);                           // solicitante | atendente | convidado
            $t->unsignedBigInteger('lido_ate')->default(0);    // id da última mensagem vista
            $t->foreignId('adicionado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['atendimento_id', 'user_id']);
        });
        Schema::create('sup_mensagens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('atendimento_id')->constrained('sup_atendimentos')->cascadeOnDelete();
            $t->foreignId('autor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('tipo', 10)->default('mensagem');       // mensagem | nota (só atendentes) | sistema
            $t->text('conteudo');
            $t->timestamp('created_at')->useCurrent();
            $t->index(['atendimento_id', 'id']);
        });
        Schema::create('sup_config', function (Blueprint $t) {
            $t->id();
            $t->string('email_entrada', 150)->nullable();      // e-mail que abre atendimento (4.26.1)
            $t->foreignId('chamado_tipo_id')->nullable()->constrained('proc_tipos_processo')->nullOnDelete();
            $t->foreignId('chamado_unidade_id')->nullable()->constrained('ug_organograma')->nullOnDelete();   // setor da TI
            $t->unsignedSmallInteger('horas_fechamento')->default(72);   // resolvido sem resposta → fechado
            $t->timestamps();
        });

        $ids = Permissoes::sincronizarCatalogo();
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['admin.configuracoes'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['suporte.atender', 'suporte.gerir'], $ids);
        }
    }

    public function down(): void
    {
        DB::table('ged_permissions')->whereIn('nome', ['suporte.atender', 'suporte.gerir'])->delete();
        foreach (['sup_config', 'sup_mensagens', 'sup_participantes', 'sup_atendimentos', 'sup_atendentes', 'sup_artigos', 'sup_assuntos'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
