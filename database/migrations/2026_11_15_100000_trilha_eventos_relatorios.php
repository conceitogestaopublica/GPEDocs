<?php

use App\Support\AgenteUsuario;
use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trilha de eventos e relatórios (TR 4.25.4–6, 4.25.12–16): eventos de acesso, cadastros e
 * configurações num só lugar (código, setor, IP, SO, dispositivo, navegador), relatórios gerados em
 * segundo plano com aviso e guardados por 7 dias, e relatórios agendados entregues sozinhos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ged_eventos', function (Blueprint $t) {
            $t->id();
            $t->string('codigo', 20);                    // ex.: ACE.ENTRADA, CFG.ALT
            $t->string('categoria', 20);                 // acesso | cadastro | configuracao | processo | documento | comunicacao | operacao
            $t->string('descricao', 500);
            $t->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedBigInteger('unidade_id')->nullable();   // setor do usuário no momento do evento
            $t->unsignedBigInteger('ug_id')->nullable();
            $t->string('login', 150)->nullable();        // o que foi digitado, quando não há usuário
            $t->string('rota', 120)->nullable();
            $t->string('entidade_tipo', 40)->nullable();
            $t->unsignedBigInteger('entidade_id')->nullable();
            $t->boolean('sucesso')->default(true);
            $t->jsonb('detalhes')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('so', 40)->nullable();
            $t->string('dispositivo', 20)->nullable();
            $t->string('navegador', 40)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index('created_at');
            $t->index(['usuario_id', 'created_at']);
            $t->index(['categoria', 'created_at']);
        });

        Schema::create('ged_relatorios', function (Blueprint $t) {
            $t->id();
            $t->string('tipo', 20);                      // eventos | acessos
            $t->jsonb('filtros');
            $t->string('status', 15)->default('na_fila'); // na_fila | gerando | pronto | erro
            $t->string('arquivo_path')->nullable();
            $t->unsignedInteger('linhas')->nullable();
            $t->text('erro')->nullable();
            $t->foreignId('solicitado_por')->constrained('users')->cascadeOnDelete();
            $t->unsignedBigInteger('agendamento_id')->nullable();
            $t->timestamp('expira_em')->nullable();      // 7 dias depois de pronto (4.25.6)
            $t->timestamps();
            $t->index(['solicitado_por', 'created_at']);
        });

        Schema::create('ged_relatorios_agendados', function (Blueprint $t) {
            $t->id();
            $t->string('nome', 120);
            $t->string('tipo', 20);
            $t->jsonb('filtros');                        // período relativo ao envio (ex.: últimos 7 dias)
            $t->string('frequencia', 10);                // diaria | semanal | mensal
            $t->boolean('enviar_email')->default(true);
            $t->boolean('ativo')->default(true);
            $t->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('proximo_em');
            $t->timestamp('ultimo_em')->nullable();
            $t->timestamps();
        });

        // Os acessos já registrados (ged_login_logs) entram na trilha
        if (Schema::hasTable('ged_login_logs')) {
            DB::table('ged_login_logs as l')->leftJoin('users as u', 'u.id', '=', 'l.user_id')->orderBy('l.id')
                ->select(['l.*', 'u.unidade_id'])->chunk(500, function ($linhas) {
                    DB::table('ged_eventos')->insert($linhas->map(function ($l) {
                        [$so, $disp, $nav] = AgenteUsuario::analisar($l->user_agent);

                        return ['codigo' => $l->sucesso ? 'ACE.ENTRADA' : 'ACE.RECUSA', 'categoria' => 'acesso',
                            'descricao' => $l->sucesso ? 'Entrada no sistema' . (! empty($l->origem) ? " ({$l->origem})" : '') : 'Entrada recusada: ' . ($l->motivo ?? '-'),
                            'usuario_id' => $l->user_id, 'unidade_id' => $l->unidade_id, 'login' => $l->login, 'sucesso' => (bool) $l->sucesso,
                            'ip' => $l->ip, 'so' => $so, 'dispositivo' => $disp, 'navegador' => $nav, 'user_agent' => mb_substr((string) $l->user_agent, 0, 500),
                            'created_at' => $l->created_at];
                    })->all());
                });
        }

        $ids = Permissoes::sincronizarCatalogo();
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['admin.configuracoes'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['relatorio.eventos'], $ids);
        }
    }

    public function down(): void
    {
        DB::table('ged_permissions')->where('nome', 'relatorio.eventos')->delete();
        Schema::dropIfExists('ged_relatorios_agendados');
        Schema::dropIfExists('ged_relatorios');
        Schema::dropIfExists('ged_eventos');
    }
};
