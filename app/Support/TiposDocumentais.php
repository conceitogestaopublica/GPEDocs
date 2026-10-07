<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Tipo documental dos documentos que o próprio sistema gera (decisão de processo,
 * memorando, ofício, circular). Antes os controllers gravavam ids fixos (1, 2, 25): em
 * banco cujos tipos têm outra numeração o documento ia com tipo errado — ou a decisão do
 * processo falhava por chave estrangeira, como num ente novo criado pelo template.
 */
final class TiposDocumentais
{
    /** Id do tipo pelo nome (sem diferenciar maiúsculas e acentos); cria se não existir. */
    public static function id(string $nome, ?string $descricao = null): int
    {
        $chave = self::normalizar($nome);
        $existente = DB::table('ged_tipos_documentais')->get(['id', 'nome'])
            ->first(fn ($t) => self::normalizar((string) $t->nome) === $chave);

        if ($existente) {
            return (int) $existente->id;
        }

        return (int) DB::table('ged_tipos_documentais')->insertGetId([
            'nome'       => $nome,
            'descricao'  => $descricao ?? 'Gerado pelo sistema',
            'ativo'      => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private static function normalizar(string $s): string
    {
        return mb_strtolower(trim(\Illuminate\Support\Str::ascii($s)));
    }
}
