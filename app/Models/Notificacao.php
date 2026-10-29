<?php

declare(strict_types=1);

namespace App\Models;

use App\Jobs\AvisarServidorPorEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Throwable;

class Notificacao extends Model
{
    protected $table = 'ged_notificacoes';

    protected $fillable = [
        'usuario_id',
        'tipo',
        'titulo',
        'mensagem',
        'referencia_tipo',
        'referencia_id',
        'lida',
        'categoria',
        'alcance',
        'urgente',
        'lida_em',
    ];

    protected function casts(): array
    {
        return [
            'lida' => 'boolean',
            'urgente' => 'boolean',
            'lida_em' => 'datetime',
        ];
    }

    /**
     * Toda notificação interna pode ir também por e-mail, conforme as
     * preferências do servidor (TR 4.7.19) — decidido no job, pela fila,
     * depois que a transação de quem criou a notificação confirmar.
     */
    protected static function booted(): void
    {
        // Categoria da página de notificações sai do tipo (TR 4.11.3)
        static::creating(function (Notificacao $n) {
            $n->categoria ??= \App\Support\Notificacoes::categoriaDe((string) $n->tipo);
        });
        static::created(function (Notificacao $n) {
            try {
                $dominio = app()->runningInConsole() ? null : request()->getSchemeAndHttpHost();
                AvisarServidorPorEmail::dispatch($n->id, $dominio)->afterCommit();
                // SMS conforme a abrangência que o servidor escolheu para o canal (TR 4.11.4)
                $u = $n->usuario;
                if ($u && $u->telefone && ! $u->suspenso_em && \App\Support\Notificacoes::quer($u, $n, 'sms')) {
                    \App\Support\Sms::enfileirar($u->telefone, "{$n->titulo}: {$n->mensagem}", 'notificacao', $n->id, $u->id);
                }
            } catch (Throwable $e) {
                // Sem prefeitura ativa (comando de console) ou fila fora: o aviso interno basta
                Log::info('Aviso por e-mail não enfileirado', ['notificacao' => $n->id, 'motivo' => $e->getMessage()]);
            }
        });
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** Caminho da tela a que a notificação se refere (tela de notificações e e-mail). */
    public function destino(): ?string
    {
        if (! $this->referencia_id) {
            return null;
        }

        return match ($this->referencia_tipo) {
            'documento'  => "/documentos/{$this->referencia_id}",
            'processo'   => "/processos/{$this->referencia_id}",
            'memorando'  => "/memorandos/{$this->referencia_id}",
            'oficio'     => "/oficios/{$this->referencia_id}",
            'circular'   => "/circulares/{$this->referencia_id}",
            default      => null,
        };
    }
}
