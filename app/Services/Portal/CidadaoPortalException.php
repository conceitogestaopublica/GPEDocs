<?php

declare(strict_types=1);

namespace App\Services\Portal;

use RuntimeException;

/** Regra do cadastro do cidadão violada — a mensagem vai como está para a tela. */
class CidadaoPortalException extends RuntimeException
{
    public function __construct(string $mensagem, public readonly int $status = 422)
    {
        parent::__construct($mensagem);
    }
}
