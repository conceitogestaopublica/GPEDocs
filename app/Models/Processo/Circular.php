<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\Concerns\BelongsToUg;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Circular extends Model
{
    use SoftDeletes, BelongsToUg, \App\Models\Concerns\Classificavel;

    public const MODULO_CLASSIFICACAO = 'circular';

    protected $table = 'proc_circulares';

    protected $fillable = [
        'ug_id',
        'numero',
        'assunto',
        'conteudo',
        'remetente_id',
        'digitador_id',
        'contrassenha_id',
        'setor_origem',
        'destino_tipo',
        'destino_setores',
        'status',
        'enviado_em',
        'arquivado_em',
        'data_arquivamento_auto',
        'qr_code_token',
        'documento_id',
    ];

    protected function casts(): array
    {
        return [
            'destino_setores'       => 'array',
            'enviado_em'            => 'datetime',
            'arquivado_em'          => 'datetime',
            'data_arquivamento_auto'=> 'date',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Circular $circular) {
            if (empty($circular->qr_code_token)) {
                $circular->qr_code_token = (string) Str::uuid();
            }
        });
    }

    public function remetente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remetente_id');
    }

    /** Quem digitou, quando o documento saiu em nome do remetente por contrassenha (TR 4.18). */
    public function digitador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'digitador_id');
    }

    public function destinatarios(): HasMany
    {
        return $this->hasMany(CircularDestinatario::class);
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(CircularAnexo::class);
    }
}
