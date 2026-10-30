<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Captcha de imagem com código para digitar (TR: "testes de digitação de códigos"), gerado aqui mesmo,
 * sem serviço externo.
 *
 * - Cada contexto (login, cadastro…) tem o seu código na sessão, guardado só como hash e válido por
 *   10 minutos; vale para uma única tentativa, certa ou errada.
 * - Formulários de cadastro e recuperação de senha pedem sempre; os de entrada pedem só depois de
 *   algumas senhas erradas do mesmo IP, para não atrapalhar quem entra todo dia.
 */
final class Captcha
{
    public const TENTATIVAS_SEM_CAPTCHA = 3;

    private const MINUTOS_VALIDADE = 10;
    private const MINUTOS_JANELA_FALHAS = 15;
    /** Sem 0/O, 1/I/L: letras que se confundem na imagem. */
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const CONTEXTOS = ['login', 'senha', 'cidadao-cadastro', 'cidadao-entrar', 'solicitacao', 'assinatura-cadastro', 'assinatura-entrar'];

    public static function contextoValido(string $contexto): bool
    {
        return in_array($contexto, self::CONTEXTOS, true);
    }

    /** Novo código para o contexto; devolve a imagem PNG. */
    public static function gerarImagem(string $contexto): string
    {
        $codigo = '';
        for ($i = 0; $i < 5; $i++) {
            $codigo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }
        session()->put("captcha.{$contexto}", [
            'hash'   => hash('sha256', $codigo . config('app.key')),
            'expira' => now()->addMinutes(self::MINUTOS_VALIDADE)->timestamp,
        ]);

        return self::desenhar($codigo);
    }

    /**
     * Confere o código digitado (campo "captcha") e o descarta. Lança erro de validação no campo.
     */
    public static function validar(Request $request, string $contexto): void
    {
        $guardado = session()->pull("captcha.{$contexto}");
        $digitado = strtoupper(preg_replace('/\s+/', '', (string) $request->input('captcha')));

        $ok = is_array($guardado)
            && ($guardado['expira'] ?? 0) >= now()->timestamp
            && $digitado !== ''
            && hash_equals($guardado['hash'], hash('sha256', $digitado . config('app.key')));

        if (! $ok) {
            throw ValidationException::withMessages([
                'captcha' => $digitado === '' ? 'Digite o código da imagem.' : 'Código da imagem incorreto. Digite o novo código.',
            ]);
        }
    }

    /** Entrada: depois de algumas falhas do mesmo IP, passa a pedir o captcha. */
    public static function exigidoNaEntrada(Request $request, string $contexto): bool
    {
        return (int) Cache::get(self::chaveFalhas($request, $contexto), 0) >= self::TENTATIVAS_SEM_CAPTCHA;
    }

    public static function registrarFalha(Request $request, string $contexto): void
    {
        $chave = self::chaveFalhas($request, $contexto);
        Cache::put($chave, (int) Cache::get($chave, 0) + 1, now()->addMinutes(self::MINUTOS_JANELA_FALHAS));
    }

    public static function limparFalhas(Request $request, string $contexto): void
    {
        Cache::forget(self::chaveFalhas($request, $contexto));
    }

    private static function chaveFalhas(Request $request, string $contexto): string
    {
        return 'captcha-falhas:' . $contexto . ':' . sha1((string) $request->ip());
    }

    /** Letras rotacionadas, com linhas e pontos de ruído — legível para pessoas, chato para OCR simples. */
    private static function desenhar(string $codigo): string
    {
        $l = 180;
        $a = 56;
        $img = imagecreatetruecolor($l, $a);
        imagefilledrectangle($img, 0, 0, $l, $a, imagecolorallocate($img, 245, 247, 251));

        for ($i = 0; $i < 6; $i++) {
            $cor = imagecolorallocate($img, random_int(150, 210), random_int(150, 210), random_int(150, 210));
            imageline($img, random_int(0, $l), random_int(0, $a), random_int(0, $l), random_int(0, $a), $cor);
        }
        for ($i = 0; $i < 160; $i++) {
            imagesetpixel($img, random_int(0, $l), random_int(0, $a), imagecolorallocate($img, random_int(120, 220), random_int(120, 220), random_int(120, 220)));
        }

        $fonte = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        foreach (str_split($codigo) as $i => $letra) {
            $cor = imagecolorallocate($img, random_int(10, 70), random_int(40, 90), random_int(90, 140));
            if (is_file($fonte)) {
                imagettftext($img, 24, random_int(-22, 22), 14 + $i * 32, random_int(36, 46), $cor, $fonte, $letra);
            } else {
                imagestring($img, 5, 20 + $i * 30, 20, $letra, $cor);
            }
        }

        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png;
    }
}
