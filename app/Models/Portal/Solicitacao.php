<?php

declare(strict_types=1);

namespace App\Models\Portal;

use App\Models\Concerns\BelongsToUg;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Solicitacao extends Model
{
    use BelongsToUg;

    protected $table = 'portal_solicitacoes';

    protected $fillable = [
        'codigo',
        'ug_id',
        'servico_id',
        'cidadao_id',
        'anonima',
        'status',
        'descricao',
        'telefone_contato',
        'email_contato',
        'atendente_id',
        'processo_id',
        'resposta',
        'respondida_em',
    ];

    protected function casts(): array
    {
        return [
            'respondida_em' => 'datetime',
            'anonima'       => 'boolean',
        ];
    }

    public const STATUS = [
        'aberta'         => 'Aberta',
        'em_atendimento' => 'Em atendimento',
        'atendida'       => 'Atendida',
        'recusada'       => 'Recusada',
        'cancelada'      => 'Cancelada',
    ];

    public const STATUS_FINAIS = ['atendida', 'recusada', 'cancelada'];

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class, 'servico_id');
    }

    public function cidadao(): BelongsTo
    {
        return $this->belongsTo(Cidadao::class, 'cidadao_id');
    }

    public function atendente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendente_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(SolicitacaoEvento::class, 'solicitacao_id')->orderBy('id');
    }

    public static function gerarCodigo(int $ugId): string
    {
        $ano = date('Y');
        $ultimo = static::query()->withoutGlobalScope('ug')
            ->where('ug_id', $ugId)
            ->where('codigo', 'like', "SOL-{$ano}-%")
            ->orderByDesc('id')
            ->value('codigo');

        $numero = 1;
        if ($ultimo && preg_match('/(\d+)$/', $ultimo, $m)) {
            $numero = ((int) $m[1]) + 1;
        }
        return sprintf('SOL-%s-%05d', $ano, $numero);
    }
}
