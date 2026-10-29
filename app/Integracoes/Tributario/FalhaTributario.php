<?php

declare(strict_types=1);

namespace App\Integracoes\Tributario;

use RuntimeException;

/** Erro de comunicação ou recusa do sistema tributário (mensagem pronta para a tela). */
final class FalhaTributario extends RuntimeException
{
}