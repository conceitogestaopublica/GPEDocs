<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Notificacao;
use App\Support\ExportacaoAcervo;
use Illuminate\Support\Facades\DB;

/** Exportação completa do acervo em segundo plano, com aviso a quem pediu (TR 4.26.15). */
class ExportarAcervo extends TenantAwareJob
{
    use \Illuminate\Foundation\Bus\Dispatchable;

    public int $timeout = 7200;

    public int $tries = 1;

    public function __construct(public int $exportacaoId)
    {
        parent::__construct();
    }

    protected function handleTenant(): void
    {
        $ok = true;
        try {
            ExportacaoAcervo::gerar($this->exportacaoId);
        } catch (\Throwable) {
            $ok = false;
        }
        if ($uid = DB::table('port_exportacoes')->where('id', $this->exportacaoId)->value('solicitado_por')) {
            Notificacao::create(['usuario_id' => $uid, 'tipo' => 'exportacao', 'titulo' => $ok ? 'Exportação completa do acervo pronta' : 'Exportação do acervo falhou',
                'mensagem' => $ok ? 'Baixe em Configurações → Backup e portabilidade.' : 'Veja o motivo em Configurações → Backup e portabilidade.', 'lida' => false]);
        }
    }
}
