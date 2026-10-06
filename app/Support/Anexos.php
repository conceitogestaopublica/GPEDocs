<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Entrega de anexos de processos e comunicações — quem chama já conferiu o acesso. */
final class Anexos
{
    public static function baixar(string $caminho, ?string $nome): StreamedResponse
    {
        $disk = Storage::disk('documentos');
        abort_unless($disk->exists($caminho), 404, 'Arquivo do anexo não encontrado.');

        $nome = $nome ?: basename($caminho);
        $inline = request()->boolean('inline');

        return $inline
            ? $disk->response($caminho, $nome)
            : $disk->download($caminho, $nome);
    }
}
