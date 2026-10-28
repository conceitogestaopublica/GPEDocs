<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\DescadastroEmail;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Página do link "deixar de receber" dos e-mails. O link é assinado (rota "signed"): só quem recebeu
 * o e-mail tem o endereço certo. O POST também atende o botão de um clique dos leitores de e-mail
 * (List-Unsubscribe-Post).
 */
class DescadastroEmailController extends Controller
{
    public function mostrar(Request $request)
    {
        $email = DescadastroEmail::decodificar((string) $request->query('e'));
        abort_unless($email, 404);

        return Inertia::render('Email/Descadastrar', [
            'email'         => $this->mascarar($email),
            'descadastrado' => DescadastroEmail::descadastrado($email),
            'acao'          => $request->fullUrl(),
        ]);
    }

    public function confirmar(Request $request)
    {
        $email = DescadastroEmail::decodificar((string) $request->query('e'));
        abort_unless($email, 404);

        if ($request->input('voltar')) {
            DescadastroEmail::reativar($email);
        } else {
            DescadastroEmail::registrar($email, $request->ip());
        }

        // Botão de um clique do leitor de e-mail: não é navegação, basta 200.
        if (! $request->header('X-Inertia') && $request->input('List-Unsubscribe') === 'One-Click') {
            return response()->noContent(200);
        }

        return redirect($request->fullUrl());
    }

    /** jo***@gmail.com — confirma o endereço sem expô-lo inteiro na página. */
    private function mascarar(string $email): string
    {
        [$nome, $dominio] = explode('@', $email, 2);

        return mb_substr($nome, 0, 2) . str_repeat('*', max(3, mb_strlen($nome) - 2)) . '@' . $dominio;
    }
}
