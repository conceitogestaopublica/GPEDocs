<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\ResolveTenant;
use App\Models\Portal\Cidadao;
use App\Models\User;
use App\Services\Portal\CidadaoPortalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Exclusão da conta do cidadão pelo titular (portal / app GPE Cloud): o que some, o que fica
 * por obrigação legal, e que a pessoa pode voltar a se cadastrar.
 *
 * Precisa de Postgres com o esquema migrado (o código usa regexp_replace e jsonb) — com o
 * SQLite padrão do phpunit.xml, a classe é pulada. Para rodar:
 *
 *   docker run -d --name ged-teste-pg -e POSTGRES_PASSWORD=teste -e POSTGRES_DB=ged_teste \
 *     -p 127.0.0.1:55432:5432 postgres:16-alpine
 *   export DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=ged_teste \
 *     DB_USERNAME=postgres DB_PASSWORD=teste
 *   php artisan migrate --database=pgsql --path=database/migrations --force
 *   php artisan test --filter=ExcluirContaCidadaoTest
 */
class ExcluirContaCidadaoTest extends TestCase
{
    private const CPF = '52998224725';

    private const TOKEN_PORTAL = 'token-do-portal-para-teste';

    private CidadaoPortalService $servico;

    protected function setUp(): void
    {
        parent::setUp();
        // Antes de qualquer conexão: com o SQLite padrão (ou sem o driver dele) a classe é pulada.
        if (config('database.connections.'.config('database.default').'.driver') !== 'pgsql') {
            $this->markTestSkipped('Precisa de Postgres migrado (ver o comentário da classe).');
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

    /** Cadastro confirmado, como o portal deixa depois do código no e-mail. */
    private function cidadao(array $extra = []): Cidadao
    {
        return Cidadao::create($extra + [
            'nome'                => 'Maria da Silva',
            'email'               => 'maria@exemplo.com',
            'cpf'                 => self::CPF,
            'telefone'            => '31999990000',
            'senha'               => Hash::make('senha-antiga-123'),
            'email_verificado_em' => now(),
            'ativo'               => true,
            'nomes_anteriores'    => ['Maria Souza'],
            'endereco'            => ['cep' => '39800000', 'logradouro' => 'Rua A', 'numero' => '10', 'complemento' => null, 'bairro' => 'Centro', 'municipio' => 'Teófilo Otoni', 'uf' => 'MG'],
        ]);
    }

    /** Solicitação mínima (UG + serviço) em nome do titular e, opcionalmente, do procurador. */
    private function solicitacao(int $titularId, ?int $procuradorId = null): int
    {
        $ug = DB::table('ugs')->insertGetId([
            'codigo' => 'UG'.random_int(1000, 9999), 'nome' => 'Prefeitura', 'email_institucional' => 'pm@exemplo.gov.br',
        ]);
        $servico = DB::table('portal_servicos')->insertGetId([
            'ug_id' => $ug, 'titulo' => 'Poda de árvore', 'slug' => 'poda-'.random_int(1000, 9999),
        ]);

        return DB::table('portal_solicitacoes')->insertGetId([
            'codigo' => 'SOL-'.random_int(100000, 999999), 'ug_id' => $ug, 'servico_id' => $servico,
            'descricao' => 'Árvore caindo na calçada', 'cidadao_id' => $titularId, 'procurador_cidadao_id' => $procuradorId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function contatoDe(int $cidadaoId): ?object
    {
        return DB::table('contatos')->where('cidadao_id', $cidadaoId)->first();
    }

    // ── Sem registros: apaga ────────────────────────────────────────────────────────────────

    public function test_sem_registros_o_cadastro_e_apagado(): void
    {
        $c = $this->cidadao();

        $this->assertSame('apagado', $this->servico->excluir($c));
        $this->assertNull(Cidadao::find($c->id), 'sem nenhum registro em nome dele, nada justifica guardar o cadastro');
    }

    public function test_contato_que_nasceu_do_portal_perde_os_dados_de_contato_e_o_vinculo(): void
    {
        $c = $this->cidadao();
        $contato = $this->contatoDe($c->id);
        $this->assertNotNull($contato, 'pré-condição: o cadastro do portal cria o contato da base única');
        $this->assertSame('portal', $contato->origem);
        DB::table('contatos')->where('id', $contato->id)->update(['campos' => json_encode(['profissao' => 'Professora'])]);

        $this->servico->excluir($c);

        $depois = DB::table('contatos')->where('id', $contato->id)->first();
        $this->assertNull($depois->cidadao_id);
        $this->assertNull($depois->email);
        $this->assertNull($depois->celular);
        $this->assertNull($depois->endereco);
        $this->assertNull($depois->campos, 'os campos complementares vieram do autocadastro do portal');
        $this->assertSame('Maria da Silva', $depois->nome, 'nome e documento ficam: o contato pode ser requerente de processo');
        $this->assertSame(self::CPF, $depois->cpf);
    }

    public function test_contato_de_outra_origem_so_e_desligado(): void
    {
        $c = $this->cidadao();
        $contato = $this->contatoDe($c->id);
        DB::table('contatos')->where('id', $contato->id)->update(['origem' => 'processo', 'email' => 'maria@prefeitura-cadastro.com']);

        $this->servico->excluir($c);

        $depois = DB::table('contatos')->where('id', $contato->id)->first();
        $this->assertNull($depois->cidadao_id);
        $this->assertSame('maria@prefeitura-cadastro.com', $depois->email,
            'o que a Prefeitura cadastrou por outro caminho não é dado desta conta');
    }

    // ── Com registros: anonimiza ────────────────────────────────────────────────────────────

    public function test_com_solicitacao_fica_so_o_autor_e_some_todo_o_resto(): void
    {
        $c = $this->cidadao(['token_verificacao' => hash('sha256', '123456'), 'token_verificacao_expira_em' => now()->addMinutes(10)]);
        $c->forceFill(['email_novo' => 'novo@exemplo.com', 'email_novo_token' => 'x', 'email_novo_expira_em' => now()->addMinutes(5)])->save();
        $sol = $this->solicitacao($c->id);

        $this->assertSame('anonimizado', $this->servico->excluir($c));

        $d = Cidadao::find($c->id);
        $this->assertNotNull($d, 'a solicitação continua apontando para o autor');
        $this->assertSame('Maria da Silva', $d->nome);
        $this->assertSame(self::CPF, $d->cpf);
        $this->assertFalse($d->ativo);
        $this->assertNotNull($d->excluido_em);
        $this->assertSame("excluido-{$c->id}@conta-excluida.invalid", $d->email);
        $this->assertFalse(Hash::check('senha-antiga-123', $d->senha), 'a senha antiga não pode mais abrir nada');
        foreach (['telefone', 'endereco', 'nomes_anteriores', 'user_id', 'email_verificado_em', 'token_verificacao',
            'token_verificacao_expira_em', 'email_novo', 'email_novo_token', 'email_novo_expira_em', 'remember_token'] as $campo) {
            $this->assertNull($d->{$campo}, "{$campo} deveria ter sido apagado");
        }
        $this->assertSame($c->id, (int) DB::table('portal_solicitacoes')->where('id', $sol)->value('cidadao_id'));
    }

    public function test_quem_so_foi_procurador_tambem_fica_como_autor(): void
    {
        $titular = $this->cidadao();
        $procurador = $this->cidadao(['cpf' => '11144477735', 'email' => 'joao@exemplo.com', 'nome' => 'João Procurador']);
        $this->solicitacao($titular->id, $procurador->id);

        $this->assertSame('anonimizado', $this->servico->excluir($procurador));
        $this->assertTrue(Cidadao::find($titular->id)->ativo, 'o titular não é afetado');
    }

    public function test_anonimizar_nao_manda_o_email_de_marcador_para_os_contatos(): void
    {
        $c = $this->cidadao();
        $contato = $this->contatoDe($c->id);
        DB::table('contatos')->where('id', $contato->id)->update(['origem' => 'processo']);
        $this->solicitacao($c->id);

        $this->servico->excluir($c);

        $emails = DB::table('contatos')->pluck('email')->filter()->all();
        foreach ($emails as $email) {
            $this->assertStringNotContainsString('conta-excluida.invalid', $email);
        }
        $this->assertSame(1, DB::table('contatos')->where('cpf', self::CPF)->count(), 'não pode nascer contato novo da anonimização');
    }

    // ── Depois da exclusão ──────────────────────────────────────────────────────────────────

    public function test_excluido_some_das_buscas_e_nao_entra_mais(): void
    {
        $c = $this->cidadao();
        $this->solicitacao($c->id);
        $this->servico->excluir($c);

        $this->assertNull($this->servico->porDocumento(self::CPF));
        $this->assertNull($this->servico->porLogin('maria@exemplo.com'));
        $this->assertFalse($this->servico->temAcesso(self::CPF));
        $this->assertNull($this->servico->autenticar(self::CPF, 'senha-antiga-123'));
        $this->assertNull($this->servico->autenticar('maria@exemplo.com', 'senha-antiga-123'));
        $this->assertNull($this->servico->porIdentidadeComprovada(self::CPF), 'nem gov.br nem certificado reabrem a conta');
    }

    public function test_pode_se_cadastrar_de_novo_com_o_mesmo_cpf_e_email(): void
    {
        $c = $this->cidadao();
        $this->solicitacao($c->id);
        $this->servico->excluir($c);

        ['cidadao' => $novo] = $this->servico->cadastrar([
            'documento' => self::CPF, 'nome' => 'Maria da Silva', 'email' => 'maria@exemplo.com', 'senha' => 'senha-nova-456',
        ]);

        $this->assertNotSame($c->id, $novo->id, 'o cadastro novo é outra linha: os registros antigos não voltam para ele');
        $this->assertNull($novo->email_verificado_em, 'cadastro novo confirma o e-mail de novo');
        $this->assertSame($novo->id, $this->servico->porDocumento(self::CPF)->id);
    }

    public function test_cadastro_apagado_tambem_libera_o_cpf(): void
    {
        $c = $this->cidadao();
        $this->servico->excluir($c);

        ['cidadao' => $novo] = $this->servico->cadastrar([
            'documento' => self::CPF, 'nome' => 'Maria', 'email' => 'maria@exemplo.com', 'senha' => 'senha-nova-456',
        ]);
        $this->assertNotNull($novo->id);
    }

    public function test_usuario_do_gpe_docs_continua_usuario(): void
    {
        $usuario = User::forceCreate([
            'name' => 'Servidor Teste', 'email' => 'servidor@prefeitura.gov.br', 'cpf' => self::CPF,
            'password' => Hash::make('senha-do-usuario'),
        ]);
        $c = $this->servico->autenticar(self::CPF, 'senha-do-usuario');
        $this->assertSame($usuario->id, $c->user_id, 'pré-condição: o cidadão nasce ligado ao usuário');
        $this->solicitacao($c->id);

        $this->assertSame('anonimizado', $this->servico->excluir($c));

        $usuario->refresh();
        $this->assertTrue(Hash::check('senha-do-usuario', $usuario->password), 'a senha de usuário é da Prefeitura, não desta conta');
        $this->assertNull(Cidadao::find($c->id)->user_id);

        // Entrar de novo com a senha de usuário cria um cadastro novo — os registros antigos não voltam.
        $novo = $this->servico->autenticar(self::CPF, 'senha-do-usuario');
        $this->assertNotNull($novo);
        $this->assertNotSame($c->id, $novo->id);
    }

    // ── Rota da API ─────────────────────────────────────────────────────────────────────────

    private function comToken(string $codigo = 'portal-cidadao'): array
    {
        $token = $codigo === 'portal-cidadao' ? self::TOKEN_PORTAL : 'token-de-outro-sistema';
        DB::table('ged_sistemas_integrados')->insert([
            'codigo' => $codigo, 'nome' => $codigo, 'api_token_hash' => hash('sha256', $token),
            'api_token_prefix' => substr($token, 0, 8), 'ativo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
    }

    public function test_rota_exclui_e_a_conta_deixa_de_existir_para_o_portal(): void
    {
        config(['portal.api_sistema' => 'portal-cidadao']);
        $this->withoutMiddleware(ResolveTenant::class);
        $headers = $this->comToken();
        $c = $this->cidadao();
        $this->solicitacao($c->id);

        $this->postJson("/api/portal/cidadaos/{$c->id}/excluir", [], $headers)
            ->assertOk()->assertExactJson(['resultado' => 'anonimizado']);

        $this->getJson("/api/portal/cidadaos/{$c->id}", $headers)->assertNotFound();
        $this->postJson("/api/portal/cidadaos/{$c->id}/excluir", [], $headers)->assertNotFound();
        $this->postJson('/api/portal/cidadaos/identificar', ['documento' => self::CPF], $headers)
            ->assertOk()->assertJson(['existe' => false]);
        $this->postJson('/api/portal/cidadaos/autenticar', ['documento' => self::CPF, 'senha' => 'senha-antiga-123'], $headers)
            ->assertUnauthorized();
    }

    public function test_rota_sem_registros_responde_apagado(): void
    {
        config(['portal.api_sistema' => 'portal-cidadao']);
        $this->withoutMiddleware(ResolveTenant::class);
        $c = $this->cidadao();

        $this->postJson("/api/portal/cidadaos/{$c->id}/excluir", [], $this->comToken())
            ->assertOk()->assertExactJson(['resultado' => 'apagado']);
    }

    public function test_rota_so_aceita_o_sistema_do_portal(): void
    {
        config(['portal.api_sistema' => 'portal-cidadao']);
        $this->withoutMiddleware(ResolveTenant::class);
        $c = $this->cidadao();

        $this->postJson("/api/portal/cidadaos/{$c->id}/excluir", [], ['Accept' => 'application/json'])->assertUnauthorized();
        $this->postJson("/api/portal/cidadaos/{$c->id}/excluir", [], $this->comToken('gpe2'))->assertForbidden();
        $this->assertTrue(Cidadao::find($c->id)->ativo, 'nenhuma das duas pode ter excluído');
    }

    public function test_rota_com_id_inexistente_responde_404(): void
    {
        config(['portal.api_sistema' => 'portal-cidadao']);
        $this->withoutMiddleware(ResolveTenant::class);

        $this->postJson('/api/portal/cidadaos/999999999/excluir', [], $this->comToken())->assertNotFound();
    }
}
