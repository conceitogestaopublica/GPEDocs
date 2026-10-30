<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\RelatorioPronto;
use App\Models\Notificacao;
use App\Support\RastreioEmail;
use App\Support\RelatorioEventos;
use App\Tenant\TenantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Gera o CSV do relatório em segundo plano e avisa quem pediu (TR 4.25.5): notificação no sistema e,
 * se veio de um agendamento com e-mail, o arquivo por e-mail (4.25.15). Fica 7 dias para baixar (4.25.6).
 */
class GerarRelatorio extends TenantAwareJob
{
    use \Illuminate\Foundation\Bus\Dispatchable;

    public int $timeout = 900;

    public int $tries = 2;

    public function __construct(public int $relatorioId)
    {
        parent::__construct();
    }

    protected function handleTenant(): void
    {
        $pegou = DB::table('ged_relatorios')->where('id', $this->relatorioId)
            ->where(fn ($q) => $q->where('status', 'na_fila')->orWhere(fn ($q) => $q->where('status', 'gerando')->where('updated_at', '<', now()->subMinutes(30))))
            ->update(['status' => 'gerando', 'updated_at' => now()]);
        if (! $pegou) {
            return;
        }
        $r = DB::table('ged_relatorios')->find($this->relatorioId);
        $filtros = json_decode((string) $r->filtros, true);
        $titulo = RelatorioEventos::TIPOS[$r->tipo] ?? 'Relatório';

        try {
            $tmp = tmpfile();
            $linhas = RelatorioEventos::csv($r->tipo, $filtros, $tmp);
            rewind($tmp);
            $path = TenantStorage::pasta('relatorios') . "/{$r->tipo}-{$r->id}.csv";
            Storage::disk('documentos')->put($path, $tmp);
            fclose($tmp);
            DB::table('ged_relatorios')->where('id', $r->id)->update(['status' => 'pronto', 'arquivo_path' => $path, 'linhas' => $linhas,
                'expira_em' => now()->addDays(7), 'updated_at' => now()]);
            $this->avisar($r, "{$titulo} pronto", "{$titulo} (" . RelatorioEventos::resumoFiltros($filtros) . ") está pronto para baixar: {$linhas} linha(s).");
            $this->enviarEmail($r, $titulo, $filtros, $path, $linhas);
        } catch (Throwable $e) {
            Log::warning('Relatório não gerado', ['relatorio' => $r->id, 'erro' => $e->getMessage()]);
            DB::table('ged_relatorios')->where('id', $r->id)->update(['status' => 'erro', 'erro' => mb_substr($e->getMessage(), 0, 1000), 'updated_at' => now()]);
            $this->avisar($r, "{$titulo} não gerado", "{$titulo} não pôde ser gerado. Tente de novo ou reduza o período.");
        }
    }

    private function avisar(object $r, string $titulo, string $mensagem): void
    {
        Notificacao::create(['usuario_id' => $r->solicitado_por, 'tipo' => 'exportacao', 'titulo' => $titulo, 'mensagem' => $mensagem,
            'referencia_tipo' => 'relatorio', 'referencia_id' => $r->id, 'lida' => false]);
    }

    private function enviarEmail(object $r, string $titulo, array $filtros, string $path, int $linhas): void
    {
        if (! $r->agendamento_id) {
            return;
        }
        $ag = DB::table('ged_relatorios_agendados')->find($r->agendamento_id);
        $email = DB::table('users')->where('id', $r->solicitado_por)->value('email');
        if (! $ag?->enviar_email || ! $email) {
            return;
        }
        $tamanho = Storage::disk('documentos')->size($path);
        RastreioEmail::enviar(new RelatorioPronto($ag->nome, $titulo, RelatorioEventos::resumoFiltros($filtros), $linhas, url("/relatorios/gerados/{$r->id}"),
            $tamanho <= 8 * 1024 * 1024 ? Storage::disk('documentos')->get($path) : null, "{$r->tipo}-{$r->id}.csv",
            \App\Models\Ug::withoutGlobalScopes()->find(DB::table('users')->where('id', $r->solicitado_por)->value('ug_id'))?->nome ?? config('app.name')),
            $email, 'servidor', "Relatório agendado: {$ag->nome}", 'relatorio', $r->id, null);
    }
}
