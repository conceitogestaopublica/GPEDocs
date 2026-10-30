<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Busca de usuário pelo CPF (User::porCpf) e escolha da conta que entra pelo CPF no login
 * (User::emailParaLoginPorCpf).
 *
 * O cadastro grava o CPF como foi digitado ("020.576.226-30") e a integração manda só
 * dígitos. E a integração já criou, para o mesmo CPF, um usuário externo duplicado ao lado
 * da conta interna — o login pelo CPF não pode tratar isso como ambíguo.
 */
class UserCpfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Só as colunas que a regra usa (SQLite em memória — phpunit.xml).
        Schema::create('users', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('cpf', 14)->nullable();
            $t->string('tipo', 10)->default('interno');
            $t->string('password')->nullable();
            $t->timestamps();
        });
    }

    private function usuario(string $email, ?string $cpf, string $tipo = 'interno'): void
    {
        DB::table('users')->insert(['name' => $email, 'email' => $email, 'cpf' => $cpf, 'tipo' => $tipo, 'password' => 'x']);
    }

    // ── porCpf: caracterização (comportamento que já existia) ─────────────────

    public function test_acha_cpf_gravado_com_mascara_buscando_so_digitos(): void
    {
        $this->usuario('a@x.com', '020.576.226-30');

        $this->assertSame(['a@x.com'], User::porCpf('02057622630')->pluck('email')->all());
    }

    public function test_acha_cpf_gravado_sem_mascara_buscando_com_mascara(): void
    {
        $this->usuario('a@x.com', '02057622630');

        $this->assertSame(['a@x.com'], User::porCpf('020.576.226-30')->pluck('email')->all());
    }

    public function test_acha_cpf_com_espacos(): void
    {
        $this->usuario('a@x.com', '020 576 226 30');

        $this->assertSame(['a@x.com'], User::porCpf('02057622630')->pluck('email')->all());
    }

    public function test_nao_acha_cpf_diferente(): void
    {
        $this->usuario('a@x.com', '020.576.226-30');

        $this->assertSame([], User::porCpf('02057622631')->pluck('email')->all());
    }

    // ── porCpf: CPF vazio nunca casa (antes casava com quem tinha cpf '') ─────

    public function test_cpf_vazio_nao_casa_com_ninguem(): void
    {
        $this->usuario('sem@x.com', '');
        $this->usuario('pontos@x.com', '...-');

        $this->assertSame([], User::porCpf('')->pluck('email')->all());
        $this->assertSame([], User::porCpf('abc')->pluck('email')->all());
    }

    // ── login pelo CPF ───────────────────────────────────────────────────────

    public function test_login_cpf_unico(): void
    {
        $this->usuario('a@x.com', '020.576.226-30');

        $this->assertSame('a@x.com', User::emailParaLoginPorCpf('02057622630'));
    }

    public function test_login_cpf_inexistente_devolve_vazio(): void
    {
        $this->assertSame('', User::emailParaLoginPorCpf('02057622630'));
    }

    public function test_login_prefere_a_conta_interna_ao_externo_duplicado_da_integracao(): void
    {
        $this->usuario('servidor@prefeitura.gov.br', '020.576.226-30', 'interno');
        $this->usuario('ext-02057622630@externo.local', '02057622630', 'externo');

        $this->assertSame('servidor@prefeitura.gov.br', User::emailParaLoginPorCpf('02057622630'));
    }

    public function test_login_externo_sozinho_entra(): void
    {
        $this->usuario('empresa@x.com', '02057622630', 'externo');

        $this->assertSame('empresa@x.com', User::emailParaLoginPorCpf('02057622630'));
    }

    public function test_login_ambiguo_com_duas_contas_internas(): void
    {
        $this->usuario('a@x.com', '020.576.226-30');
        $this->usuario('b@x.com', '02057622630');

        $this->assertFalse(User::emailParaLoginPorCpf('02057622630'));
    }
}
