<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Bairro;
use App\Models\Logradouro;
use App\Models\Municipio;
use App\Models\Uf;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Popula a cadeia uf -> municipio -> bairro -> logradouro com o minimo
 * necessario para os seeders demo: MG, "Cidade Modelo", bairro "Centro" e
 * logradouro "Praca da Matriz". Idempotente.
 *
 * `nome` da UF guarda a sigla ("MG") — o front e os imports usam a sigla como
 * identificador de estado, entao manter no `nome` da UF mantem o shape flat
 * compativel com o EnderecoForm.
 */
class EnderecosDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        DB::transaction(function () {
            $mg = Uf::firstOrCreate(['nome' => 'MG']);

            $cidadeModelo = Municipio::firstOrCreate(
                ['uf_id' => $mg->id, 'nome' => 'Cidade Modelo'],
                // sem ibge_id — municipio ficticio para demo
            );

            $centro = Bairro::firstOrCreate(
                ['municipio_id' => $cidadeModelo->id, 'nome' => 'Centro'],
            );

            $pracaMatriz = Logradouro::firstOrCreate(
                [
                    'bairro_id' => $centro->id,
                    'nome'      => 'Praça da Matriz',
                    'cep'       => '37000-000',
                ],
            );

            $this->command?->info(sprintf(
                'Enderecos demo: UF %s -> %s -> %s -> %s (id=%d).',
                $mg->nome,
                $cidadeModelo->nome,
                $centro->nome,
                $pracaMatriz->nome,
                $pracaMatriz->id,
            ));
        });
    }
}
