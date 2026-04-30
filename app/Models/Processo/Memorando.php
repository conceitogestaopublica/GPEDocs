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

class Memorando extends Model
{
    use SoftDeletes, BelongsToUg;

    protected $table = 'proc_memorandos';

    protected $fillable = [
        'ug_id',
        'numero',
        'assunto',
        'conteudo',
        'remetente_id',
        'setor_origem',
        'confidencial',
        'status',
        'enviado_em',
        'arquivado_em',
        'data_arquivamento_auto',
        'qr_code_token',
    ];

    protected function casts(): array
    {
        return [
            'confidencial'          => 'boolean',
            'enviado_em'            => 'datetime',
            'arquivado_em'          => 'datetime',
            'data_arquivamento_auto'=> 'date',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Memorando $memorando) {
            if (empty($memorando->qr_code_token)) {
                $memorando->qr_code_token = (string) Str::uuid();
            }
        });
    }

    public function remetente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remetente_id');
    }

    public function destinatarios(): HasMany
    {
        return $this->hasMany(MemorandoDestinatario::class);
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(MemorandoAnexo::class);
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(MemorandoResposta::class);
    }
}
