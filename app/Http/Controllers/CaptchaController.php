<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Captcha;

/** Imagem do captcha (público). Cada pedido gera um código novo para o contexto. */
class CaptchaController extends Controller
{
    public function imagem(string $contexto)
    {
        abort_unless(Captcha::contextoValido($contexto), 404);

        return response(Captcha::gerarImagem($contexto), 200, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
