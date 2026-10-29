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

    /** Enviada no GED (Meu perfil ou administrador). */
    public static function salvar(User $user, UploadedFile $arquivo): void
    {
        self::gravar($user, (string) file_get_contents($arquivo->getRealPath()), null);
    }

    /**
     * Foto vinda de outro sistema (ex.: RH do gpe2): $origem identifica de onde,
     * para a importação seguinte saber se precisa trocar.
     */
    public static function salvarDeBytes(User $user, string $bytes, string $origem): void
    {
        self::gravar($user, $bytes, $origem);
    }

    public static function remover(User $user): void
    {
        self::apagarArquivo($user);
        $user->forceFill(['foto_path' => null, 'foto_origem' => null])->save();
    }

    private static function gravar(User $user, string $bytes, ?string $origem): void
    {
        $origemImg = @imagecreatefromstring($bytes);
        if ($origemImg === false) {
            throw new RuntimeException('Imagem não reconhecida. Use JPG ou PNG.');
        }

        $w = imagesx($origemImg);
        $h = imagesy($origemImg);
        $lado = min($w, $h);
        $destino = imagecreatetruecolor(self::LADO, self::LADO);
        imagefill($destino, 0, 0, imagecolorallocate($destino, 255, 255, 255)); // PNG transparente vira fundo branco
        imagecopyresampled($destino, $origemImg, 0, 0, intdiv($w - $lado, 2), intdiv($h - $lado, 2), self::LADO, self::LADO, $lado, $lado);

        ob_start();
        imagejpeg($destino, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($origemImg);
        imagedestroy($destino);

        $caminho = TenantStorage::pasta('fotos') . "/usuario-{$user->id}-" . Str::lower(Str::random(8)) . '.jpg';
        $disco = Storage::disk('documentos');
        $disco->put($caminho, $jpeg);
        self::donoDoStorage($disco->path($caminho));

        self::apagarArquivo($user);
        $user->forceFill(['foto_path' => $caminho, 'foto_origem' => $origem])->save();
    }

    /** Gravada por comando como root (importação): o PHP da web precisa conseguir ler. */
    private static function donoDoStorage(string $arquivo): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0 && is_file($arquivo)) {
            $dono = fileowner(storage_path('app'));
            $grupo = filegroup(storage_path('app'));
            @chown($arquivo, $dono);
            @chgrp($arquivo, $grupo);
            @chown(dirname($arquivo), $dono);
            @chgrp(dirname($arquivo), $grupo);
        }
    }

    private static function apagarArquivo(User $user): void
    {
        if ($user->foto_path) {
            Storage::disk('documentos')->delete($user->foto_path);
        }
    }
}
