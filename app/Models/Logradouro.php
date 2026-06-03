<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Logradouro extends Model
{
    protected $table = 'logradouros';

    protected $fillable = ['nome', 'cep', 'bairro_id', 'last_user'];

    public function bairro(): BelongsTo
    {
        return $this->belongsTo(Bairro::class, 'bairro_id');
    }

    public function ultimoUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_user');
    }

    /**
     * Resolve (ou cria) a cadeia uf -> municipio -> bairro -> logradouro a
     * partir do shape flat usado nos forms / imports. Devolve null se faltar
     * qualquer campo essencial (logradouro, bairro, cidade ou uf) — o caller
     * deve interpretar como "endereco nao informado".
     */
    public static function resolverFromFlat(array $endereco): ?self
    {
        $logradouroNome = trim((string) ($endereco['logradouro'] ?? ''));
        $bairroNome     = trim((string) ($endereco['bairro']     ?? ''));
        $cidadeNome     = trim((string) ($endereco['cidade']     ?? ''));
        $ufNome         = trim((string) ($endereco['uf']         ?? ''));

        if ($logradouroNome === '' || $bairroNome === '' || $cidadeNome === '' || $ufNome === '') {
            return null;
        }

        $uf = Uf::firstOrCreate(['nome' => strtoupper($ufNome)]);

        $municipio = Municipio::firstOrCreate(
            ['uf_id' => $uf->id, 'nome' => $cidadeNome],
        );

        $bairro = Bairro::firstOrCreate(
            ['municipio_id' => $municipio->id, 'nome' => $bairroNome],
        );

        $cep = $endereco['cep'] ?? null;

        return self::firstOrCreate(
            [
                'bairro_id' => $bairro->id,
                'nome'      => $logradouroNome,
                'cep'       => $cep,
            ],
        );
    }
}
