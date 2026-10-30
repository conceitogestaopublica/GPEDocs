<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\Sms;

/** Envio de um SMS pela fila, no banco do município que pediu (TR 4.11.1). */
class EnviarSms extends TenantAwareJob
{
    use \Illuminate\Foundation\Bus\Dispatchable;

    public int $tries = 2;

    public function __construct(
        public string $numero,
        public string $texto,
        public ?string $refTipo = null,
        public ?int $refId = null,
        public ?int $usuarioId = null,
    ) {
        parent::__construct();
    }

    protected function handleTenant(): void
    {
        Sms::enviar($this->numero, $this->texto, $this->refTipo, $this->refId, $this->usuarioId);
    }
}
