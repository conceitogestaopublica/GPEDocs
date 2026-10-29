<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\RastreioEmail;
use Illuminate\Http\Request;

/**
 * Rotas públicas do rastreio de e-mail (TR 4.11.12–4.11.14): o pixel da abertura, o link do
 * clique (que só segue para o destino assinado) e o retorno de entrega/falha do provedor.
 */
class RastreioEmailController extends Controller
{
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function aberto(Request $request, string $token)
    {
        RastreioEmail::registrar($token, 'abertura', $request->userAgent());

        return response(base64_decode(self::GIF), 200, ['Content-Type' => 'image/gif', 'Cache-Control' => 'no-store, no-cache, must-revalidate']);
    }

    public function clique(Request $request, string $token)
    {
        $destino = (string) $request->query('u');
        abort_unless($destino !== '' && RastreioEmail::confere($token, $destino, (string) $request->query('s')), 404);
        RastreioEmail::registrar($token, 'abertura', $request->userAgent());   // clicou, então abriu
        RastreioEmail::registrar($token, 'clique', $request->userAgent());

        return redirect()->away($destino);
    }

    /**
     * Retorno do provedor de e-mail: {token, evento: entregue|falhou, motivo?}. O token vem do
     * cabeçalho X-GED-Rastreio que o provedor devolve. Protegido pelo segredo na URL.
     */
    public function webhook(Request $request, string $segredo)
    {
        $esperado = (string) config('services.email_webhook.segredo');
        abort_unless($esperado !== '' && hash_equals($esperado, $segredo), 404);
        $d = $request->validate([
            'token'  => ['required', 'string', 'size:40'],
            'evento' => ['required', 'in:entregue,falhou'],
            'motivo' => ['nullable', 'string', 'max:1000'],
        ]);
        RastreioEmail::registrar($d['token'], $d['evento'] === 'entregue' ? 'entrega' : 'falha', null, $d['motivo'] ?? null);

        return response()->noContent();
    }
}
