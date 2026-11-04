<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Rules\DocumentoDeUsuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Documento (CPF/CNPJ) da conta: busca (User::porDocumento), conta que entra pelo documento no
 * login (User::emailParaLoginPorDocumento) e regra que impede o mesmo documento em duas contas.
 *
 * O cadastro grava o CPF como foi digitado ("020.576.226-30") e a integração manda só
 * dígitos. E a integração já criou, para o mesmo CPF, um usuário externo duplicado ao lado
 * da conta interna — o login pelo CPF não pode tratar isso como ambíguo.
 */
class UserDocumentoTest extends TestCase
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

    // ── porDocumento: caracterização (comportamento que já existia) ─────────────────

    public function test_acha_cpf_gravado_com_mascara_buscando_so_digitos(): void
    {
        $this->usuario('a@x.com', '020.576.226-30');

        $this->assertSame(['a@x.com'], User::porDocumento('02057622630')->pluck('email')->all());
    }

    public function test_acha_cpf_gravado_sem_mascara_buscando_com_mascara(): void
    {
        $this->usuario('a@x.com', '02057622630');

        $this->assertSame(['a@x.com'], User::porDocumento('020.576.226-30')->pluck('email')->all());
    }

    public function test_acha_cpf_com_espacos(): void
    {
        $this->usuario('a@x.com', '020 576 226 30');

        $this->assertSame(['a@x.com'], User::porDocumento('02057622630')->pluck('email')->all());
    }

    public function test_nao_acha_cpf_diferente(): void
    {
        $this->usuario('a@x.com', '020.576.226-30');

        $this->assertSame([], User::porDocumento('02057622631')->pluck('email')->all());
    }

    // ── porDocumento: documento vazio nunca casa (antes casava com quem tinha cpf '') ─────

    public function test_cpf_vazio_nao_casa_com_ninguem(): void
    {
        $this->usuario('sem@x.com', '');
        $this->usuario('pontos@x.com', '...-');

        $this->assertSame([], User::porDocumento('')->pluck('email')->all());
        $this->assertSame([], User::porDocumento('abc')->pluck('email')->all());
    }

    // ── login pelo CPF ───────────────────────────────────────────────────────

    public function test_login_cpf_unico(): void
    {
        $this->usuario('a@x.com', '020.576.226-30');

        $this->assertSame('a@x.com', User::emailParaLoginPorDocumento('02057622630'));
    }

    public function test_login_cpf_inexistente_devolve_vazio(): void
    {
        $this->assertSame('', User::emailParaLoginPorDocumento('02057622630'));
    }

    public function test_login_prefere_a_conta_interna_ao_externo_duplicado_da_integracao(): void
    {
        $this->usuario('servidor@prefeitura.gov.br', '020.576.226-30', 'interno');
        $this->usuario('ext-02057622630@externo.local', '02057622630', 'externo');

        $this->assertSame('servidor@prefeitura.gov.br', User::emailParaLoginPorDocumento('02057622630'));
    }

    public function test_login_externo_sozinho_entra(): void
    {
        $this->usuario('empresa@x.com', '02057622630', 'externo');

        $this->assertSame('empresa@x.com', User::emailParaLoginPorDocumento('02057622630'));
    }

    public function test_login_ambiguo_com_duas_contas_internas(): void
    {
        $this->usuario('a@x.com', '020.576.226-30');
        $this->usuario('b@x.com', '02057622630');

        $this->assertFalse(User::emailParaLoginPorDocumento('02057622630'));
    }

    public function test_login_por_cnpj_com_ou_sem_mascara(): void
    {
        $this->usuario('empresa@x.com', '12345678000190', 'externo');

        $this->assertSame('empresa@x.com', User::emailParaLoginPorDocumento('12345678000190'));
        $this->assertSame('empresa@x.com', User::emailParaLoginPorDocumento('12.345.678/0001-90'));
    }

    // ── cadastro: o mesmo documento não pode estar em duas contas ────────────

    private function erroDaRegra(?string $documento, ?int $excetoId = null): ?string
    {
        $v = Validator::make(['cpf' => $documento], ['cpf' => ['nullable', new DocumentoDeUsuario($excetoId)]]);

        return $v->errors()->first('cpf') ?: null;
    }

    public function test_cadastro_recusa_documento_de_outra_conta_com_qualquer_mascara(): void
    {
        $this->usuario('a@x.com', '020.576.226-30');

        $this->assertNotNull($this->erroDaRegra('02057622630'));
        $this->assertNotNull($this->erroDaRegra('020.576.226-30'));
    }

    public function test_cadastro_aceita_o_proprio_documento_ao_editar(): void
    {
        $this->usuario('a@x.com', '020.576.226-30');
        $id = (int) DB::table('users')->where('email', 'a@x.com')->value('id');

        $this->assertNull($this->erroDaRegra('02057622630', $id));
    }

    public function test_cadastro_aceita_documento_livre_e_campo_vazio(): void
    {
        $this->assertNull($this->erroDaRegra('02057622630'));
        $this->assertNull($this->erroDaRegra('12.345.678/0001-90'));
        $this->assertNull($this->erroDaRegra(null));
    }

    public function test_cadastro_recusa_quantidade_de_digitos_invalida(): void
    {
        $this->assertNotNull($this->erroDaRegra('12345'));
        $this->assertNotNull($this->erroDaRegra('123456789012'));
    }

    public function test_importador_nao_repete_documento_de_outra_conta(): void
    {
        $this->usuario('a@x.com', '020.576.226-30');

        $this->assertNull(User::documentoDisponivelPara('02057622630', 'b@x.com'));
        $this->assertSame('02057622630', User::documentoDisponivelPara('02057622630', 'a@x.com'));
        $this->assertSame('99999999999', User::documentoDisponivelPara('99999999999', 'b@x.com'));
        $this->assertNull(User::documentoDisponivelPara(null, 'b@x.com'));
    }

    public function test_documento_em_outra_conta(): void
    {
        $this->usuario('a@x.com', '020.576.226-30');
        $id = (int) DB::table('users')->where('email', 'a@x.com')->value('id');

        $this->assertTrue(User::documentoEmOutraConta('02057622630'));
        $this->assertFalse(User::documentoEmOutraConta('02057622630', $id));
        $this->assertFalse(User::documentoEmOutraConta('99999999999'));
        $this->assertFalse(User::documentoEmOutraConta(''));
    }
}
