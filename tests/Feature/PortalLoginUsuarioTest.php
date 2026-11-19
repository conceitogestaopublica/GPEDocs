<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Portal\Cidadao;
use App\Models\User;
use App\Services\Portal\CidadaoPortalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Login no Portal do Cidadão com o USUÁRIO do GPE Docs (servidor, funcionário): a senha é a de
 * usuário, o CPF é obrigatório (é a chave do cidadão) e usuário suspenso não entra.
 *
 * Precisa de Postgres migrado — ver o comentário de ExcluirContaCidadaoTest (mesmos comandos).
 */
class PortalLoginUsuarioTest extends TestCase
{
    private const CPF = '52998224725';

    private const SENHA = 'senha-do-usuario';

    private CidadaoPortalService $servico;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.connections.'.config('database.default').'.driver') !== 'pgsql') {
            $this->markTestSkipped('Precisa de Postgres migrado (ver ExcluirContaCidadaoTest).');
        }
        DB::beginTransaction();
        $this->servico = app(CidadaoPortalService::class);
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function usuario(array $extra = []): User
    {
        return User::forceCreate($extra + [
            'name' => 'Vitor Teste', 'email' => 'vitor@sistema-apresentacao.local', 'cpf' => self::CPF,
            'password' => Hash::make(self::SENHA),
        ]);
    }

    public function test_usuario_com_cpf_entra_por_email_e_por_cpf(): void
    {
        $u = $this->usuario();

        $porEmail = $this->servico->autenticar('Vitor@Sistema-Apresentacao.local', self::SENHA);
        $this->assertNotNull($porEmail, 'e-mail sem diferença de maiúsculas deve achar o usuário');
        $this->assertSame($u->id, $porEmail->user_id, 'o cadastro de cidadão nasce ligado ao usuário');
        $this->assertSame($porEmail->id, $this->servico->autenticar('529.982.247-25', self::SENHA)?->id);
    }

    public function test_usuario_sem_cpf_nao_entra_pelo_portal(): void
    {
        $this->usuario(['cpf' => null]);

        $this->assertNull($this->servico->autenticar('vitor@sistema-apresentacao.local', self::SENHA),
            'sem CPF não há cidadão nem conta no portal: o e-mail entra no GPE Docs, mas não no portal');
    }

    public function test_cpf_vazio_ou_incompleto_conta_como_sem_cpf(): void
    {
        $this->usuario(['cpf' => '']);
        $this->assertNull($this->servico->autenticar('vitor@sistema-apresentacao.local', self::SENHA));

        DB::table('users')->update(['cpf' => '123']);
        $this->assertNull($this->servico->autenticar('vitor@sistema-apresentacao.local', self::SENHA));
        $this->assertSame(0, Cidadao::count(), 'não pode nascer cidadão sem documento');
    }

    public function test_usuario_suspenso_nao_entra(): void
    {
        $this->usuario(['suspenso_em' => now()]);

        $this->assertNull($this->servico->autenticar('vitor@sistema-apresentacao.local', self::SENHA));
        $this->assertNull($this->servico->autenticar(self::CPF, self::SENHA));
        $this->assertNull($this->servico->porIdentidadeComprovada(self::CPF), 'nem por gov.br ou certificado');
        $this->assertFalse($this->servico->temAcesso(self::CPF));
    }

    public function test_suspender_depois_corta_o_cidadao_ja_ligado(): void
    {
        $u = $this->usuario();
        $c = $this->servico->autenticar(self::CPF, self::SENHA);
        $this->assertNotNull($c, 'pré-condição: entrou e o cidadão ficou ligado');

        $u->forceFill(['suspenso_em' => now()])->save();

        $this->assertNull($this->servico->autenticar(self::CPF, self::SENHA));
        $this->assertNull($this->servico->autenticar('vitor@sistema-apresentacao.local', self::SENHA));
        $this->assertNull($this->servico->porIdentidadeComprovada(self::CPF));

        $u->forceFill(['suspenso_em' => null])->save();
        $this->assertSame($c->id, $this->servico->autenticar(self::CPF, self::SENHA)?->id, 'reativado, volta a entrar');
    }

    public function test_servidor_somente_portal_entra(): void
    {
        $this->usuario(['somente_portal' => true, 'legado_funcionario_id' => 123]);

        $c = $this->servico->autenticar(self::CPF, self::SENHA);
        $this->assertNotNull($c, 'o acesso do servidor só do portal é justamente pelo portal');
        $this->assertTrue(CidadaoPortalService::paraApi($c)['servidor']);
    }

    public function test_cadastro_de_cidadao_antigo_com_o_mesmo_email_tem_senha_propria(): void
    {
        // Pessoa que já tinha se cadastrado como cidadão (sem ligação com o usuário): vale a senha do cadastro.
        $this->usuario();
        Cidadao::create([
            'nome' => 'Vitor', 'email' => 'vitor@sistema-apresentacao.local', 'cpf' => '11144477735',
            'senha' => Hash::make('senha-do-cadastro'), 'email_verificado_em' => now(), 'ativo' => true,
        ]);

        $this->assertNull($this->servico->autenticar('vitor@sistema-apresentacao.local', self::SENHA),
            'com cadastro de cidadão no e-mail, a senha conferida é a dele, não a de usuário');
        $this->assertNotNull($this->servico->autenticar('vitor@sistema-apresentacao.local', 'senha-do-cadastro'));
    }

    public function test_email_trocado_no_usuario_entra_pelo_novo_no_mesmo_cidadao(): void
    {
        // Entrou pelo CPF (nasce o cidadão com o e-mail de então); depois a Prefeitura trocou o
        // e-mail do usuário. Pelo e-mail novo, o cidadão ligado é o mesmo — antes, criar outro
        // esbarrava no user_id único e o portal mostrava "sistema fora do ar".
        $u = $this->usuario();
        $c = $this->servico->autenticar(self::CPF, self::SENHA);
        $this->assertNotNull($c, 'pré-condição: o cidadão nasceu pelo CPF');

        $u->forceFill(['email' => 'app.teste@gpecloud.com.br'])->save();

        $this->assertSame($c->id, $this->servico->autenticar('App.Teste@gpecloud.com.br', self::SENHA)?->id);
        $this->assertSame($c->id, $this->servico->porIdentidadeComprovada('app.teste@gpecloud.com.br')?->id);
        $this->assertSame(1, Cidadao::where('user_id', $u->id)->count());
        $this->assertNull($this->servico->autenticar('app.teste@gpecloud.com.br', 'errada'));
    }

    public function test_cidadao_ligado_inativo_nao_entra_pelo_email_do_usuario(): void
    {
        $u = $this->usuario();
        $c = $this->servico->autenticar(self::CPF, self::SENHA);
        $c->forceFill(['ativo' => false])->save();
        $u->forceFill(['email' => 'outro@sistema-apresentacao.local'])->save();

        $this->assertNull($this->servico->autenticar('outro@sistema-apresentacao.local', self::SENHA),
            'o cadastro desativado continua desativado, mesmo entrando pelo e-mail do usuário');
        $this->assertSame(1, Cidadao::where('user_id', $u->id)->count());
    }

    // ── Login pelo DOCUMENTO ────────────────────────────────────────────────────────────────

    private function cidadao(array $extra = []): Cidadao
    {
        return Cidadao::create($extra + [
            'nome' => 'Maria', 'email' => 'maria@exemplo.com', 'cpf' => self::CPF,
            'senha' => Hash::make('senha-da-maria'), 'email_verificado_em' => now(), 'ativo' => true,
        ]);
    }

    public function test_cidadao_entra_pelo_cpf_com_ou_sem_mascara(): void
    {
        $c = $this->cidadao();

        foreach (['52998224725', '529.982.247-25', ' 529.982.247-25 '] as $doc) {
            $this->assertSame($c->id, $this->servico->autenticar($doc, 'senha-da-maria')?->id, "documento '{$doc}'");
        }
        $this->assertNull($this->servico->autenticar(self::CPF, 'errada'));
    }

    public function test_cpf_gravado_com_mascara_em_cadastro_antigo(): void
    {
        $c = $this->cidadao(['cpf' => '529.982.247-25']);

        $this->assertSame($c->id, $this->servico->autenticar(self::CPF, 'senha-da-maria')?->id);
    }

    public function test_cnpj_entra_com_ou_sem_mascara(): void
    {
        $c = $this->cidadao(['cpf' => '11222333000181', 'email' => 'empresa@exemplo.com']);

        $this->assertSame($c->id, $this->servico->autenticar('11.222.333/0001-81', 'senha-da-maria')?->id);
        $this->assertSame($c->id, $this->servico->autenticar('11222333000181', 'senha-da-maria')?->id);
    }

    public function test_usuario_com_cpf_gravado_com_mascara_entra_pelo_documento(): void
    {
        $this->usuario(['cpf' => '529.982.247-25']);

        $c = $this->servico->autenticar(self::CPF, self::SENHA);
        $this->assertNotNull($c);
        $this->assertSame(self::CPF, $c->cpf, 'o cidadão nasce com o documento só em dígitos');
    }

    /**
     * O externo duplicado que a integração criava: mesmo CPF, e-mail provisório, senha aleatória.
     * Desde `documento_unico_usuarios` o banco não aceita mais o CPF repetido; os bancos que ainda não
     * rodaram essa migração têm. O índice sai só dentro da transação do teste (o Postgres desfaz o DDL).
     */
    private function duplicadoExterno(): User
    {
        DB::statement('DROP INDEX IF EXISTS users_documento_unico');

        return User::forceCreate([
            'name' => 'Vitor (integração)', 'email' => 'externo-'.self::CPF.'@integracao.local', 'cpf' => self::CPF,
            'tipo' => 'externo', 'password' => Hash::make('senha-aleatoria-'.bin2hex(random_bytes(8))),
        ]);
    }

    public function test_cpf_duplicado_pela_integracao_a_senha_certa_entra(): void
    {
        $externo = $this->duplicadoExterno();   // id MENOR: antes era o escolhido
        $real = $this->usuario();

        $c = $this->servico->autenticar('529.982.247-25', self::SENHA);

        $this->assertNotNull($c, 'a senha da conta real não pode ser conferida contra a do duplicado');
        $this->assertSame($real->id, $c->user_id);
        $this->assertNotSame($externo->id, $c->user_id);
        $this->assertNull($this->servico->autenticar(self::CPF, 'errada'));
    }

    public function test_cidadao_ligado_ao_duplicado_e_religado_a_conta_real(): void
    {
        $externo = $this->duplicadoExterno();
        $real = $this->usuario();
        $c = $this->cidadao(['user_id' => $externo->id, 'email' => 'vitor@sistema-apresentacao.local']);

        $entrou = $this->servico->autenticar(self::CPF, self::SENHA);

        $this->assertSame($c->id, $entrou?->id, 'o mesmo cadastro de cidadão, não um novo');
        $this->assertSame($real->id, $entrou->user_id, 'religado à conta cuja senha conferiu');
        $this->assertSame(1, Cidadao::count());
    }

    public function test_conta_da_senha_suspensa_nao_entra_nem_pela_outra_do_documento(): void
    {
        $this->duplicadoExterno();
        $this->usuario(['suspenso_em' => now()]);

        $this->assertNull($this->servico->autenticar(self::CPF, self::SENHA), 'a conta da senha está suspensa');
    }

    public function test_rota_autenticar_pelo_documento_com_mascara(): void
    {
        config(['portal.api_sistema' => 'portal-cidadao']);
        $this->withoutMiddleware(\App\Http\Middleware\ResolveTenant::class);
        DB::table('ged_sistemas_integrados')->insert([
            'codigo' => 'portal-cidadao', 'nome' => 'Portal', 'api_token_hash' => hash('sha256', 'tok-portal'),
            'api_token_prefix' => 'tok-port', 'ativo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->duplicadoExterno();
        $this->usuario();
        $h = ['Authorization' => 'Bearer tok-portal', 'Accept' => 'application/json'];

        $this->postJson('/api/portal/cidadaos/identificar', ['documento' => '529.982.247-25'], $h)
            ->assertOk()->assertJson(['existe' => true, 'email_confirmado' => true]);
        $this->postJson('/api/portal/cidadaos/autenticar', ['documento' => '529.982.247-25', 'senha' => self::SENHA], $h)
            ->assertOk()->assertJsonPath('cidadao.documento', self::CPF);
        $this->postJson('/api/portal/cidadaos/autenticar', ['documento' => self::CPF, 'senha' => 'errada'], $h)
            ->assertUnauthorized();
    }
}
