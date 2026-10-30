<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Descadastro dos avisos automáticos por e-mail (TR: "descadastramento automatizado para quem recebe").
 *
 * - Todo e-mail para fora da prefeitura leva o link (e o cabeçalho List-Unsubscribe), assinado com a
 *   chave do sistema: ninguém descadastra o endereço de outra pessoa.
 * - Descadastrado deixa de receber os avisos de andamento das solicitações. Comunicações oficiais
 *   dirigidas à pessoa (ofício, pedido de assinatura) continuam chegando — são o próprio ato.
 */
final class DescadastroEmail
{
    public static function link(string $email): string
    {
        return URL::signedRoute('email.descadastrar', ['e' => self::codificar($email)]);
    }

    public static function descadastrado(?string $email): bool
    {
        $email = self::normalizar($email);

        return $email !== '' && DB::table('ged_email_descadastros')->where('email', $email)->exists();
    }

    public static function registrar(string $email, ?string $ip): void
    {
        DB::table('ged_email_descadastros')->insertOrIgnore([
            'email'      => self::normalizar($email),
            'ip'         => $ip,
            'created_at' => now(),
        ]);
    }

    public static function reativar(string $email): void
    {
        DB::table('ged_email_descadastros')->where('email', self::normalizar($email))->delete();
    }

    public static function codificar(string $email): string
    {
        return rtrim(strtr(base64_encode(self::normalizar($email)), '+/', '-_'), '=');
    }

    public static function decodificar(string $codigo): ?string
    {
        $email = base64_decode(strtr($codigo, '-_', '+/'), true);

        return $email !== false && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private static function normalizar(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }
}
