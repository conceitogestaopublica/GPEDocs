<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Notifications\RedefinirSenha;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/** E-mail de redefinição de senha: enviado de verdade pelo transporte em memória. */
class RedefinirSenhaEmailTest extends TestCase
{
    private function enviado(): Email
    {
        config(['mail.default' => 'array']);
        $user = (new User())->forceFill(['name' => 'Ana <b>Lima</b>', 'email' => 'ana+teste@x.com']);

        $user->notify(new RedefinirSenha('token-abc'));

        return Mail::mailer('array')->getSymfonyTransport()->messages()->first()->getOriginalMessage();
    }

    public function test_html_leva_identidade_paleta_e_link(): void
    {
        $email = $this->enviado();
        $html = (string) $email->getHtmlBody();

        $this->assertSame('GPE Docs — Redefinição de senha', $email->getSubject());
        $this->assertSame('GPE Docs', $email->getFrom()[0]->getName());
        $this->assertStringContainsString('GPE Docs', $html);
        $this->assertStringContainsString('#0891b2', $html);
        $this->assertStringNotContainsString('#1d4ed8', $html);
        $this->assertStringContainsString('/reset-password/token-abc?email=ana%2Bteste%40x.com', $html);
        $this->assertStringContainsString('60 minutos', $html);
    }

    public function test_nome_da_pessoa_e_escapado(): void
    {
        $html = (string) $this->enviado()->getHtmlBody();

        $this->assertStringContainsString('Ana &lt;b&gt;Lima&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>Lima</b>', $html);
    }

    public function test_icone_vai_embutido_e_ha_versao_em_texto(): void
    {
        $email = $this->enviado();

        $this->assertStringContainsString('src="cid:', (string) $email->getHtmlBody());
        $this->assertCount(1, $email->getAttachments());
        $this->assertStringContainsString('/reset-password/token-abc?email=ana%2Bteste%40x.com', (string) $email->getTextBody());
    }
}
