<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Oficio extends Model
{
    use SoftDeletes;

    protected $table = 'proc_oficios';

    protected $fillable = [
        'numero',
        'assunto',
        'conteudo',
        'remetente_id',
        'setor_origem',
        'destinatario_nome',
        'destinatario_email',
        'destinatario_cargo',
        'destinatario_orgao',
        'status',
        'enviado_em',
        'entregue_em',
        'lido_em',
        'arquivado_em',
        'rastreio_token',
        'qr_code_token',
    ];

    protected function casts(): array
    {
        return [
            'enviado_em'   => 'datetime',
            'entregue_em'  => 'datetime',
            'lido_em'      => 'datetime',
            'arquivado_em' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Oficio $oficio) {
            if (empty($oficio->qr_code_token)) {
                $oficio->qr_code_token = (string) Str::uuid();
            }

            if (empty($oficio->rastreio_token)) {
                $oficio->rastreio_token = Str::random(64);
            }
        });
    }

    public function remetente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remetente_id');
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(OficioAnexo::class);
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(OficioResposta::class);
    }
}
