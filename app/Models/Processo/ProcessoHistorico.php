<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessoHistorico extends Model
{
    protected $table = 'proc_historico';

    public $timestamps = false;

    protected $fillable = [
        'processo_id',
        'usuario_id',
        'acao',
        'detalhes',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'detalhes' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProcessoHistorico $model) {
            $model->created_at = $model->freshTimestamp();
        });
    }

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
