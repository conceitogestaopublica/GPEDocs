<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\Classificacao;
use Illuminate\Support\Facades\Log;

/** Classifica os itens antigos sem classificação, pelo assunto e pelo módulo (TR 4.27.10). */
class ClassificarRetroativo extends TenantAwareJob
{
    use \Illuminate\Foundation\Bus\Dispatchable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public ?string $modulo = null)
    {
        parent::__construct();
    }

    protected function handleTenant(): void
    {
        Log::info('Classificação retroativa', Classificacao::retroativa($this->modulo));
    }
}
