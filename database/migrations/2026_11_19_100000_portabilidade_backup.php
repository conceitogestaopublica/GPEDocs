<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Portabilidade e backup (TR 4.26.13–17): backups do município (banco + arquivos, completo e incremental)
 * com manifesto de hashes e verificação, conferência periódica da integridade do acervo (hash gravado ×
 * arquivo), exportação completa em diretórios padronizados com dicionário de dados, e o modo somente leitura
 * do ambiente pós-contratual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('port_config', function (Blueprint $t) {
            $t->id();
            $t->boolean('somente_leitura')->default(false);
            $t->string('somente_leitura_motivo', 300)->nullable();
            $t->unsignedSmallInteger('retencao_dias')->default(35);      // backups mais antigos são apagados (o último completo fica)
            $t->timestamps();
        });
        Schema::create('port_backups', function (Blueprint $t) {
            $t->id();
            $t->string('tipo', 12);                       // completo | incremental
            $t->foreignId('base_id')->nullable()->constrained('port_backups')->nullOnDelete();   // incremental: desde qual backup
            $t->string('status', 12)->default('gerando'); // gerando | pronto | erro
            $t->string('destino', 30);                    // disco de backup (local, s3…)
            $t->string('arquivo')->nullable();
            $t->unsignedBigInteger('tamanho')->nullable();
            $t->unsignedInteger('arquivos')->default(0);
            $t->string('sha256', 64)->nullable();
            $t->string('banco_sha256', 64)->nullable();
            $t->timestamp('verificado_em')->nullable();
            $t->boolean('verificado_ok')->nullable();
            $t->text('erro')->nullable();
            $t->timestamp('inicio_em')->nullable();
            $t->timestamp('fim_em')->nullable();
            $t->timestamps();
        });
        Schema::create('port_verificacoes', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('conferidos')->default(0);
            $t->unsignedInteger('ok')->default(0);
            $t->unsignedInteger('divergentes')->default(0);
            $t->unsignedInteger('ausentes')->default(0);
            $t->jsonb('problemas')->nullable();           // [{tabela, id, path, problema}] (até 500)
            $t->timestamp('inicio_em')->nullable();
            $t->timestamp('fim_em')->nullable();
            $t->timestamps();
        });
        Schema::create('port_exportacoes', function (Blueprint $t) {
            $t->id();
            $t->string('status', 12)->default('na_fila'); // na_fila | gerando | pronto | erro
            $t->string('arquivo')->nullable();
            $t->unsignedBigInteger('tamanho')->nullable();
            $t->unsignedInteger('tabelas')->default(0);
            $t->unsignedInteger('arquivos')->default(0);
            $t->string('sha256', 64)->nullable();
            $t->text('erro')->nullable();
            $t->foreignId('solicitado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        $ids = Permissoes::sincronizarCatalogo();
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['admin.configuracoes'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['admin.portabilidade'], $ids);
        }
    }

    public function down(): void
    {
        DB::table('ged_permissions')->where('nome', 'admin.portabilidade')->delete();
        foreach (['port_exportacoes', 'port_verificacoes', 'port_backups', 'port_config'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
