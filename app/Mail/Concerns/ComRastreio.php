<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use App\Support\RastreioEmail;

/**
 * E-mail externo rastreado (TR 4.11.12): o link principal passa pelo GED (clique), uma
 * imagem de 1 pixel marca a abertura e o cabeçalho X-GED-Rastreio deixa o provedor devolver
 * entrega e falha pelo webhook. Sem token (envio fora do RastreioEmail), nada muda.
 */
trait ComRastreio
{
    public ?string $rastreioToken = null;

    public function rastreado(string $token): static
    {
        $this->rastreioToken = $token;

        return $this;
    }

    /** @return array{link: string, pixel: ?string} */
    protected function aplicarRastreio(string $link): array
    {
        if (! $this->rastreioToken) {
            return ['link' => $link, 'pixel' => null];
        }
        $token = $this->rastreioToken;
        $this->withSymfonyMessage(fn ($m) => $m->getHeaders()->addTextHeader('X-GED-Rastreio', $token));

        return ['link' => RastreioEmail::link($token, $link), 'pixel' => RastreioEmail::pixel($token)];
    }
}
