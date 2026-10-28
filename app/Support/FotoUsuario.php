<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Tenant\TenantStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Foto do servidor: recorte quadrado central, 400×400 em JPEG (GD), no disco
 * documentos da prefeitura. Arquivo grande ou fora de proporção não vai para a
 * tela de tramitação do jeito que chegou.
 */
final class FotoUsuario
{
    private const LADO = 400;

    public static function salvar(User $user, UploadedFile $arquivo): void
    {
        $origem = @imagecreatefromstring((string) file_get_contents($arquivo->getRealPath()));
        if ($origem === false) {
            throw new RuntimeException('Imagem não reconhecida. Use JPG ou PNG.');
        }

        $w = imagesx($origem);
        $h = imagesy($origem);
        $lado = min($w, $h);
        $destino = imagecreatetruecolor(self::LADO, self::LADO);
        imagefill($destino, 0, 0, imagecolorallocate($destino, 255, 255, 255)); // PNG transparente vira fundo branco
        imagecopyresampled($destino, $origem, 0, 0, intdiv($w - $lado, 2), intdiv($h - $lado, 2), self::LADO, self::LADO, $lado, $lado);

        ob_start();
        imagejpeg($destino, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($origem);
        imagedestroy($destino);

        $caminho = TenantStorage::pasta('fotos') . "/usuario-{$user->id}-" . Str::lower(Str::random(8)) . '.jpg';
        Storage::disk('documentos')->put($caminho, $jpeg);

        self::apagarArquivo($user);
        $user->forceFill(['foto_path' => $caminho])->save();
    }

    public static function remover(User $user): void
    {
        self::apagarArquivo($user);
        $user->forceFill(['foto_path' => null])->save();
    }

    private static function apagarArquivo(User $user): void
    {
        if ($user->foto_path) {
            Storage::disk('documentos')->delete($user->foto_path);
        }
    }
}
