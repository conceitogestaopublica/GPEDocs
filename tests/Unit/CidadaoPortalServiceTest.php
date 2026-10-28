<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Portal\CidadaoPortalService;
use PHPUnit\Framework\TestCase;

/**
 * Cadastro único do cidadão para o Portal do Cidadão: o documento (CPF ou
 * CNPJ) é a chave — só dígitos, com dígito verificador — e o e-mail nunca
 * volta inteiro para a tela.
 */
class CidadaoPortalServiceTest extends TestCase
{
    public function test_cpf_e_cnpj_validos_com_ou_sem_mascara(): void
    {
        $this->assertTrue(CidadaoPortalService::documentoValido('529.982.247-25'));
        $this->assertTrue(CidadaoPortalService::documentoValido('52998224725'));
        $this->assertTrue(CidadaoPortalService::documentoValido('11.222.333/0001-81'));
        $this->assertTrue(CidadaoPortalService::documentoValido('11222333000181'));
    }

    public function test_documento_invalido_e_recusado(): void
    {
        $this->assertFalse(CidadaoPortalService::documentoValido('529.982.247-24'));   // DV errado
        $this->assertFalse(CidadaoPortalService::documentoValido('111.111.111-11'));   // repetido
        $this->assertFalse(CidadaoPortalService::documentoValido('11.222.333/0001-80')); // DV errado
        $this->assertFalse(CidadaoPortalService::documentoValido('00000000000000'));
        $this->assertFalse(CidadaoPortalService::documentoValido('1234567'));            // tamanho
    }

    public function test_email_mascarado(): void
    {
        $this->assertSame('j***@gmail.com', CidadaoPortalService::mascararEmail('joeljardim@gmail.com'));
        $this->assertNull(CidadaoPortalService::mascararEmail(null));
        $this->assertNull(CidadaoPortalService::mascararEmail('sem-arroba'));
    }
}
