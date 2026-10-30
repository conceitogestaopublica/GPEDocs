<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tramita GOV.BR (TR 4.28, Portaria SEGES/ME 9.412/2022): envio, recebimento e recusa de ofícios e
 * processos entre entes pelo barramento, com NRE (número de registro eletrônico) e IDT (identificador do
 * trâmite). O adaptador é configurável: "simulador" (homologação interna, sem credencial) ou "pen"
 * (o barramento real, com o certificado e o repositório/estrutura recebidos na adesão).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tramita_config', function (Blueprint $t) {
            $t->id();
            $t->boolean('ativo')->default(false);
            $t->string('adaptador', 15)->default('simulador');   // simulador | pen
            $t->string('ambiente', 15)->default('homologacao');   // homologacao | producao
            $t->string('url', 300)->nullable();
            $t->string('certificado_path')->nullable();           // .pem/.pfx do cliente (mTLS), fora do public
            $t->text('certificado_senha')->nullable();            // cifrada (Crypt)
            $t->unsignedBigInteger('repositorio_id')->nullable(); // repositório de estruturas do ente no barramento
            $t->string('estrutura_id', 40)->nullable();           // unidade do ente que envia e recebe
            $t->string('estrutura_nome', 200)->nullable();
            $t->foreignId('tipo_recebido_id')->nullable()->constrained('proc_tipos_processo')->nullOnDelete();
            $t->foreignId('unidade_entrada_id')->nullable()->constrained('ug_organograma')->nullOnDelete();
            $t->boolean('aceite_automatico')->default(false);
            $t->timestamp('sincronizado_em')->nullable();
            $t->timestamps();
        });
        Schema::create('tramita_tramites', function (Blueprint $t) {
            $t->id();
            $t->string('sentido', 10);                            // enviado | recebido
            $t->string('tipo', 10);                               // oficio | processo
            $t->string('status', 25);                             // enviado | concluido | recusado | cancelado | erro | aguardando_aceite | recebido
            $t->string('nre', 40)->nullable();
            $t->string('idt', 40)->nullable();
            $t->foreignId('oficio_id')->nullable()->constrained('proc_oficios')->nullOnDelete();
            $t->foreignId('processo_id')->nullable()->constrained('proc_processos')->nullOnDelete();
            $t->string('outro_repositorio', 40)->nullable();      // do destino (enviado) ou da origem (recebido)
            $t->string('outro_estrutura', 40)->nullable();
            $t->string('outro_nome', 250)->nullable();
            $t->string('assunto', 300);
            $t->jsonb('metadados')->nullable();                   // o que foi enviado / recebido
            $t->jsonb('componentes')->nullable();                 // [{nome, mime, tamanho, hash, path}]
            $t->jsonb('recibo')->nullable();                      // recibo de conclusão (data/hora, NRE, IDT, hashes)
            $t->string('recusa_motivo', 40)->nullable();
            $t->text('recusa_justificativa')->nullable();
            $t->text('erro')->nullable();
            $t->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('enviado_em')->nullable();
            $t->timestamp('concluido_em')->nullable();
            $t->timestamps();
            $t->unique(['sentido', 'idt']);
            $t->index(['sentido', 'status']);
        });
        // Barramento simulado (adaptador "simulador"): o que o "outro ente" tem a receber ou enviou para nós
        Schema::create('tramita_simulador', function (Blueprint $t) {
            $t->id();
            $t->string('idt', 40)->unique();
            $t->string('nre', 40);
            $t->string('sentido', 10);                            // para_fora (nós enviamos) | para_nos (ente externo enviou)
            $t->string('destino_estrutura', 40)->nullable();
            $t->string('status', 20);                             // pendente | recebido | recusado
            $t->jsonb('pacote');                                  // metadados + componentes (base64 no para_nos)
            $t->jsonb('recibo')->nullable();
            $t->timestamps();
        });

        $ids = Permissoes::sincronizarCatalogo();
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['admin.configuracoes'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['tramita.usar', 'tramita.configurar'], $ids);
        }
    }

    public function down(): void
    {
        DB::table('ged_permissions')->whereIn('nome', ['tramita.usar', 'tramita.configurar'])->delete();
        Schema::dropIfExists('tramita_simulador');
        Schema::dropIfExists('tramita_tramites');
        Schema::dropIfExists('tramita_config');
    }
};
