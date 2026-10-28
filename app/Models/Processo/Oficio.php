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

class Oficio extends Model
{
    use SoftDeletes, BelongsToUg;

    protected $table = 'proc_oficios';

    protected $fillable = [
        'ug_id',
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
        'documento_id',
        'modo_envio',
    ];

    protected function casts(): array
    {
        return [
            'enviado_em'   => 'datetime',
            'entregue_em'  => 'datetime',
            'lido_em'      => 'datetime',
            'arquivado_em' => 'datetime',
            'email_enviado_em' => 'datetime',
            'email_aberto_em'  => 'datetime',
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

    /**
     * Variáveis dos modelos de ofício ({{destinatário}}, {{cargo}}…), com e sem acento.
     * A data é a do envio (ou da criação, enquanto não enviado), por extenso.
     */
    public function variaveis(): array
    {
        $data = ($this->enviado_em ?? $this->created_at ?? now())->locale('pt_BR');

        return [
            'destinatario' => (string) $this->destinatario_nome,
            'cargo'        => (string) $this->destinatario_cargo,
            'orgao'        => (string) $this->destinatario_orgao,
            'assunto'      => (string) $this->assunto,
            'numero'       => (string) $this->numero,
            'data'         => $data->translatedFormat('j \d\e F \d\e Y'),
        ];
    }

    /**
     * Conteúdo pronto para exibir e imprimir: variáveis trocadas pelos dados do ofício e HTML limpo.
     * A troca é feita aqui, e não ao salvar, para o texto acompanhar uma correção do destinatário.
     * Variável desconhecida fica como está, à vista de quem revisa.
     */
    public function conteudoFinal(): string
    {
        $valores = $this->variaveis();
        $conteudo = preg_replace_callback('/\{\{\s*([^{}]{1,40}?)\s*\}\}/u', function (array $m) use ($valores) {
            $chave = Str::ascii(mb_strtolower($m[1]));
            return array_key_exists($chave, $valores) ? e($valores[$chave]) : $m[0];
        }, (string) $this->conteudo);

        return \App\Support\HtmlSeguro::limpar($conteudo);
    }
}
