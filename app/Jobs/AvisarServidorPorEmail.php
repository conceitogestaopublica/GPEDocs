<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\AvisoServidor;
use App\Models\Notificacao;
use App\Support\PreferenciasUsuario;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Manda por e-mail uma notificação interna, se o servidor escolheu receber
 * esse tipo de aviso (Meu perfil › Preferências). Vai para o e-mail
 * principal e, havendo, para o secundário (TR 4.7.18, 4.7.19).
 */
class AvisarServidorPorEmail extends TenantAwareJob
{
    use \Illuminate\Foundation\Bus\Dispatchable;

    public int $tries = 2;

    /** @param  string|null  $dominio  endereço da prefeitura no disparo (o link do e-mail volta para ela) */
    /** @param  bool  $forcar  manda mesmo sem a preferência (o alerta do prazo pediu e-mail — TR 4.32.17) */
    public function __construct(public int $notificacaoId, public ?string $dominio = null, public bool $forcar = false)
    {
        parent::__construct();
    }

    protected function handleTenant(): void
    {
        $n = Notificacao::with('usuario')->find($this->notificacaoId);
        $user = $n?->usuario;
        if (! $user || $user->suspenso_em || $user->tipo !== 'interno'
            || (! $this->forcar && ! PreferenciasUsuario::querEmail($user, (string) $n->tipo))) {
            return;
        }

        $destinos = array_values(array_unique(array_filter([$user->email, $user->email_secundario],
            fn ($e) => $e && filter_var($e, FILTER_VALIDATE_EMAIL) && ! str_ends_with($e, '@servidor.local'))));
        if (! $destinos) {
            return;
        }

        $link = $n->destino() ? rtrim((string) ($this->dominio ?: config('app.url')), '/') . $n->destino() : null;
        try {
            Mail::to($destinos[0])->cc(array_slice($destinos, 1))->send(new AvisoServidor($n, $link));
        } catch (Throwable $e) {
            Log::warning('Aviso por e-mail ao servidor falhou', ['notificacao' => $n->id, 'erro' => $e->getMessage()]);
        }
    }
}
