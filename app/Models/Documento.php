<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUg;
use App\Models\Concerns\RespeitaSigilo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Documento extends Model
{
    use SoftDeletes, BelongsToUg, RespeitaSigilo, Concerns\Classificavel;

    public const MODULO_CLASSIFICACAO = 'documento';

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

    /**
     * PDF "oficial" no disco documentos: o original assinado mais recente (cada
     * assinatura ICP é acrescentada a ele de forma incremental, então o último
     * contém todas) ou, sem assinatura, a versão atual.
     */
    public function caminhoOficial(): ?string
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('documentos');
        $assinada = $this->assinaturas()
            ->where('status', 'assinado')
            ->whereNotNull('arquivo_assinado_path')
            ->orderByDesc('assinado_em')
            ->orderByDesc('id')
            ->first();

        if ($assinada && $disk->exists($assinada->arquivo_assinado_path)) {
            return $assinada->arquivo_assinado_path;
        }

        return $this->versaoAtual?->arquivo_path;
    }

    /**
     * Cópia para impressão mais recente (carimbos, tarja e termo de todas as
     * assinaturas até ela), ou null se o documento ainda não tem.
     */
    public function caminhoImpressao(): ?string
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('documentos');
        $ultima = $this->assinaturas()
            ->where('status', 'assinado')
            ->whereNotNull('arquivo_impressao_path')
            ->orderByDesc('assinado_em')
            ->orderByDesc('id')
            ->first();

        return $ultima && $disk->exists($ultima->arquivo_impressao_path) ? $ultima->arquivo_impressao_path : null;
    }

    /**
     * Executa $fn com o documento travado para assinatura: duas assinaturas
     * simultâneas partiriam do mesmo arquivo e uma se perderia.
     */
    public function comTravaDeAssinatura(callable $fn): mixed
    {
        return \Illuminate\Support\Facades\Cache::lock('assinatura-documento:' . $this->id, 120)
            ->block(60, $fn);
    }
}
