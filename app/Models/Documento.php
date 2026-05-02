<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUg;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Documento extends Model
{
    use SoftDeletes, BelongsToUg;

    protected $table = 'ged_documentos';

    protected $fillable = [
        'ug_id',
        'nome',
        'descricao',
        'tipo_documental_id',
        'pasta_id',
        'versao_atual',
        'tamanho',
        'mime_type',
        'autor_id',
        'status',
        'classificacao',
        'ocr_texto',
        'check_out_por',
        'check_out_em',
        'qr_code_token',
        'sistema_origem',
        'numero_externo',
        'metadados_externos',
        'callback_url',
        'callback_executado',
        'callback_executado_em',
    ];

    protected $casts = [
        'metadados_externos'   => 'array',
        'callback_executado'   => 'boolean',
        'callback_executado_em'=> 'datetime',
        'check_out_em'         => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($doc) {
            $doc->qr_code_token = $doc->qr_code_token ?: (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'check_out_em' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function tipoDocumental(): BelongsTo
    {
        return $this->belongsTo(TipoDocumental::class, 'tipo_documental_id');
    }

    public function pasta(): BelongsTo
    {
        return $this->belongsTo(Pasta::class, 'pasta_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    public function versoes(): HasMany
    {
        return $this->hasMany(Versao::class, 'documento_id');
    }

    public function metadados(): HasMany
    {
        return $this->hasMany(Metadado::class, 'documento_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'ged_documento_tags', 'documento_id', 'tag_id');
    }

    public function compartilhamentos(): HasMany
    {
        return $this->hasMany(Compartilhamento::class, 'documento_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'documento_id');
    }

    public function fluxoInstancias(): HasMany
    {
        return $this->hasMany(FluxoInstancia::class, 'documento_id');
    }

    public function versaoAtual(): HasOne
    {
        return $this->hasOne(Versao::class, 'documento_id')->latestOfMany('versao');
    }

    public function solicitacoesAssinatura(): HasMany
    {
        return $this->hasMany(SolicitacaoAssinatura::class, 'documento_id');
    }

    public function assinaturas(): HasMany
    {
        return $this->hasMany(Assinatura::class, 'documento_id');
    }
}
