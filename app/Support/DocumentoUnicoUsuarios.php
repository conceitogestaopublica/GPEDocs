<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Garantia, no banco, de que um CPF/CNPJ está em uma conta só.
 *
 * O índice compara só os dígitos, porque `users.cpf` tem documentos com e sem máscara. Não pode
 * ser criado enquanto houver repetidos, e a junção de contas é decisão caso a caso (a conta é
 * referenciada por dezenas de tabelas): a migration e o comando usuarios:documentos-duplicados
 * só criam o índice com o banco limpo, e nada aqui altera ou apaga dados.
 *
 * Só PostgreSQL: o SQLite dos testes não tem regexp_replace.
 */
final class DocumentoUnicoUsuarios
{
    public const INDICE = 'users_documento_unico';

    private const DIGITOS = "regexp_replace(cpf, '[^0-9]', '', 'g')";

    public static function aplicavel(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    /** Contas cujo documento se repete, agrupadas por documento e com a interna primeiro. */
    public static function duplicados(): Collection
    {
        $digitos = self::DIGITOS;

        return collect(DB::select("
            SELECT id, name, email, tipo, {$digitos} AS documento
              FROM users
             WHERE {$digitos} IN (
                   SELECT {$digitos} FROM users
                    WHERE {$digitos} <> ''
                    GROUP BY 1
                   HAVING COUNT(*) > 1)
             ORDER BY documento, CASE WHEN tipo = 'externo' THEN 1 ELSE 0 END, id
        "));
    }

    public static function criarIndice(): void
    {
        $digitos = self::DIGITOS;

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS ' . self::INDICE . " ON users (({$digitos})) WHERE {$digitos} <> ''");
    }

    public static function removerIndice(): void
    {
        DB::statement('DROP INDEX IF EXISTS ' . self::INDICE);
    }
}
