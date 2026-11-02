<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Licenciamento ambiental: atividades com potencial poluidor e faixas de porte (enquadramento em classe),
 * documentos exigidos por modalidade e classe, a licença ligada ao processo (requerimento → análise →
 * emissão com condicionantes e validade, assinada → vencimento e renovação) e as condicionantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amb_config', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('servico_id')->nullable();          // serviço da Carta que recebe o requerimento (tipo + setor)
            $t->unsignedSmallInteger('dias_aviso_vencimento')->default(120);   // renovação da LO: 120 dias antes (Res. CONAMA 237/97 art. 18 §4º)
            $t->unsignedSmallInteger('dias_aviso_condicionante')->default(15);
            $t->timestamps();
        });
        Schema::create('amb_atividades', function (Blueprint $t) {
            $t->id();
            $t->string('codigo', 20)->unique();
            $t->string('descricao', 300);
            $t->string('potencial', 1);                                   // P | M | G
            $t->string('unidade', 40);                                    // parâmetro do porte (ha, m², empregados…)
            $t->decimal('porte_pequeno_ate', 14, 2);
            $t->decimal('porte_medio_ate', 14, 2);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('amb_documentos', function (Blueprint $t) {
            $t->id();
            $t->string('nome', 200);
            $t->json('modalidades');                                      // ["LP","LI",...]
            $t->unsignedTinyInteger('classe_minima')->default(1);
            $t->boolean('obrigatorio')->default(true);
            $t->string('extensoes', 60)->nullable();                      // "pdf" ou "pdf,jpg"
            $t->unsignedSmallInteger('ordem')->default(0);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('amb_licencas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ug_id')->nullable()->constrained('ugs')->nullOnDelete();
            $t->string('numero', 20)->nullable()->unique();               // na emissão: LA-2026/0001
            $t->foreignId('processo_id')->constrained('proc_processos')->cascadeOnDelete();
            $t->unsignedBigInteger('solicitacao_id')->nullable();
            $t->string('modalidade', 12);                                 // LP | LI | LO | LAS | LOC | RENOVACAO
            $t->foreignId('atividade_id')->constrained('amb_atividades');
            $t->decimal('porte_valor', 14, 2);
            $t->string('porte', 1);
            $t->string('potencial', 1);
            $t->unsignedTinyInteger('classe');
            $t->string('empreendedor_nome', 200);
            $t->string('empreendedor_documento', 20)->nullable();
            $t->string('empreendedor_email', 150)->nullable();
            $t->string('empreendimento_nome', 200)->nullable();
            $t->string('empreendimento_endereco', 300)->nullable();
            $t->foreignId('licenca_anterior_id')->nullable()->constrained('amb_licencas')->nullOnDelete();
            $t->string('status', 20)->default('em_analise');             // em_analise | emitida | indeferida | vencida | renovada
            $t->timestamp('emitida_em')->nullable();
            $t->foreignId('emitida_por')->nullable()->constrained('users')->nullOnDelete();
            $t->date('valida_ate')->nullable();
            $t->foreignId('documento_id')->nullable()->constrained('ged_documentos')->nullOnDelete();
            $t->uuid('codigo_verificacao')->nullable()->unique();
            $t->text('motivo_indeferimento')->nullable();
            $t->timestamp('aviso_vencimento_em')->nullable();
            $t->timestamps();
            $t->index(['status', 'valida_ate']);
        });
        Schema::create('amb_condicionantes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('licenca_id')->constrained('amb_licencas')->cascadeOnDelete();
            $t->text('descricao');
            $t->date('prazo')->nullable();
            $t->timestamp('cumprida_em')->nullable();
            $t->text('cumprimento')->nullable();
            $t->foreignId('cumprida_registrada_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('aviso_em')->nullable();
            $t->timestamps();
        });

        $ids = Permissoes::sincronizarCatalogo();
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['urbanismo.gerir'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['ambiental.gerir'], $ids);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('amb_condicionantes');
        Schema::dropIfExists('amb_licencas');
        Schema::dropIfExists('amb_documentos');
        Schema::dropIfExists('amb_atividades');
        Schema::dropIfExists('amb_config');
        DB::table('ged_permissions')->where('nome', 'ambiental.gerir')->delete();
    }
};
