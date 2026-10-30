<?php

declare(strict_types=1);

namespace App\Http\Controllers\Configuracao;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SomenteLeitura;
use App\Jobs\ExportarAcervo;
use App\Support\Backup;
use App\Support\ExportacaoAcervo;
use App\Support\IntegridadeAcervo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Backup e portabilidade (TR 4.26.13–17): backups (gerar, verificar, baixar), integridade do acervo,
 * exportação completa com dicionário de dados e o modo somente leitura do ambiente pós-contratual.
 */
class PortabilidadeController extends Controller
{
    public function index()
    {
        $h = fn ($v) => $v ? Carbon::parse($v, 'UTC')->toIso8601String() : null;
        $cfg = Backup::config();

        return Inertia::render('Configuracao/Portabilidade/Index', [
            'backups' => DB::table('port_backups')->orderByDesc('id')->limit(60)->get()->map(fn ($b) => ['id' => $b->id, 'tipo' => $b->tipo, 'status' => $b->status,
                'arquivos' => $b->arquivos, 'tamanho' => $b->tamanho, 'sha256' => $b->sha256, 'erro' => $b->erro, 'destino' => $b->destino, 'base_id' => $b->base_id,
                'inicio_em' => $h($b->inicio_em), 'fim_em' => $h($b->fim_em), 'verificado_em' => $h($b->verificado_em), 'verificado_ok' => $b->verificado_ok]),
            'verificacoes' => DB::table('port_verificacoes')->orderByDesc('id')->limit(10)->get()->map(fn ($v) => ['id' => $v->id, 'conferidos' => $v->conferidos,
                'ok' => $v->ok, 'divergentes' => $v->divergentes, 'ausentes' => $v->ausentes, 'problemas' => json_decode((string) $v->problemas, true) ?: [],
                'inicio_em' => $h($v->inicio_em), 'fim_em' => $h($v->fim_em)]),
            'exportacoes' => DB::table('port_exportacoes as e')->leftJoin('users as u', 'u.id', '=', 'e.solicitado_por')->orderByDesc('e.id')->limit(10)
                ->get(['e.*', 'u.name as usuario'])->map(fn ($e) => ['id' => $e->id, 'status' => $e->status, 'tamanho' => $e->tamanho, 'tabelas' => $e->tabelas,
                    'arquivos' => $e->arquivos, 'sha256' => $e->sha256, 'erro' => $e->erro, 'usuario' => $e->usuario, 'em' => $h($e->created_at)]),
            'config' => ['somente_leitura' => (bool) $cfg->somente_leitura, 'somente_leitura_motivo' => $cfg->somente_leitura_motivo,
                'retencao_dias' => (int) $cfg->retencao_dias, 'forcado_pelo_servidor' => (bool) config('app.somente_leitura')],
            'destino' => ['driver' => config('filesystems.disks.backup.driver'), 'bucket' => config('filesystems.disks.backup.bucket')],
            'tabelas' => count(ExportacaoAcervo::tabelas()),
        ]);
    }

    public function backup(Request $request)
    {
        $tipo = $request->input('tipo') === 'completo' ? 'completo' : 'incremental';
        $b = Backup::executar($tipo);

        return back()->with($b->status === 'pronto' ? 'success' : 'error', $b->status === 'pronto'
            ? "Backup {$tipo} gerado: {$b->arquivos} arquivo(s) + banco." : "O backup falhou: {$b->erro}");
    }

    public function verificar(int $id)
    {
        $b = DB::table('port_backups')->where('id', $id)->where('status', 'pronto')->first();
        abort_unless($b, 404);

        $ok = Backup::verificar($b);

        return back()->with($ok ? 'success' : 'error', $ok ? "Backup {$id} íntegro: todos os hashes conferem e o banco abre."
            : 'Backup com problema: ' . DB::table('port_backups')->where('id', $id)->value('erro'));
    }

    public function baixarBackup(int $id)
    {
        $b = DB::table('port_backups')->where('id', $id)->where('status', 'pronto')->first();
        abort_unless($b && Storage::disk('backup')->exists($b->arquivo), 404);

        return Storage::disk('backup')->download($b->arquivo, basename($b->arquivo));
    }

    public function acervo()
    {
        $v = IntegridadeAcervo::verificar();

        return back()->with($v->divergentes || $v->ausentes ? 'warning' : 'success',
            "Acervo conferido: {$v->conferidos} arquivo(s), {$v->ok} íntegro(s), {$v->divergentes} com hash diferente, {$v->ausentes} ausente(s).");
    }

    public function exportar()
    {
        if (DB::table('port_exportacoes')->whereIn('status', ['na_fila', 'gerando'])->exists()) {
            return back()->with('warning', 'Já há uma exportação em andamento.');
        }
        $id = DB::table('port_exportacoes')->insertGetId(['status' => 'na_fila', 'solicitado_por' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);
        ExportarAcervo::dispatch($id);

        return back()->with('success', 'Exportação completa na fila. Você recebe um aviso quando estiver pronta para baixar.');
    }

    public function baixarExportacao(int $id)
    {
        $e = DB::table('port_exportacoes')->where('id', $id)->where('status', 'pronto')->first();
        abort_unless($e && Storage::disk('backup')->exists($e->arquivo), 404);

        return Storage::disk('backup')->download($e->arquivo, basename($e->arquivo));
    }

    /** Dicionário de dados avulso, para consulta. */
    public function dicionario()
    {
        [, $html] = ExportacaoAcervo::dicionario(ExportacaoAcervo::tabelas());

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function salvarConfig(Request $request)
    {
        $d = $request->validate(['somente_leitura' => ['boolean'], 'somente_leitura_motivo' => ['nullable', 'string', 'max:300'], 'retencao_dias' => ['required', 'integer', 'between:7,3650']]);
        $linha = ['somente_leitura' => $request->boolean('somente_leitura'), 'somente_leitura_motivo' => $d['somente_leitura_motivo'] ?? null,
            'retencao_dias' => $d['retencao_dias'], 'updated_at' => now()];
        $id = DB::table('port_config')->value('id');
        $id ? DB::table('port_config')->where('id', $id)->update($linha) : DB::table('port_config')->insert($linha + ['created_at' => now()]);
        SomenteLeitura::esquecer();

        return back()->with('success', $linha['somente_leitura'] ? 'Ambiente em modo somente leitura.' : 'Configuração salva.');
    }
}
