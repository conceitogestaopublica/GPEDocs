<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use App\Support\DescadastroEmail;

/**
 * Link "deixar de receber" no rodapé do e-mail e cabeçalho List-Unsubscribe (o botão
 * "cancelar inscrição" do Gmail/Outlook). Chamar no build(), depois do Mail::to().
 */
trait ComDescadastro
{
    protected function comDescadastro(): static
    {
        $email = $this->to[0]['address'] ?? null;
        if (! $email) {
            return $this;
        }

        $link = DescadastroEmail::link($email);

        return $this->with('descadastro', $link)
            ->withSymfonyMessage(function ($mensagem) use ($link) {
                $mensagem->getHeaders()->addTextHeader('List-Unsubscribe', "<{$link}>");
                $mensagem->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
    }
}
