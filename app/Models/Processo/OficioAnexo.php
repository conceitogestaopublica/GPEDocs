<?php

declare(strict_types=1);

namespace App\Models\Processo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OficioAnexo extends Model
{
    protected $table = 'proc_oficio_anexos';

    protected $fillable = [
        'oficio_id',
        'nome',
        'arquivo_path',
        'tamanho',
        'mime_type',
        'solicitar_assinatura',
        'enviado_por',
    ];

    protected function casts(): array
    {
        return [
            'solicitar_assinatura' => 'boolean',
        ];
    }

    public function oficio(): BelongsTo
    {
        return $this->belongsTo(Oficio::class);
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }
}
