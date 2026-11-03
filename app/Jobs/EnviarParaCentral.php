<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\CentralDemandas\CentralDemandasClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * GED → Central de Demandas, sempre pela fila (nunca na requisição do servidor).
 *
 * - sincronizar: cria a demanda se ainda não existe (a Central não duplica o mesmo atendimento_id) e
 *   entrega as mensagens do servidor que ainda não foram — mensagem escrita antes da demanda existir
 *   espera aqui e segue depois, nenhuma se perde.
 * - avaliacao / retorno: garante a demanda e manda a nota ou o "Isso resolveu?".
 * Central fora do ar: lança exceção e a fila tenta de novo (1 min, 5 min, 15 min, 30 min, 1 h).
 */
class EnviarParaCentral extends TenantAwareJob
{
    use \Illuminate\Foundation\Bus\Dispatchable;

    public int $tries = 6;

    public array $backoff = [60, 300, 900, 1800, 3600];

    public int $timeout = 120;

    /** @param  string  $base  endereço do GED deste tenant (link do atendimento), capturado na requisição */
    public function __construct(public int $atendimentoId, public string $acao = 'sincronizar', public array $dados = [], public string $base = '')
    {
        parent::__construct();
    }

    protected function handleTenant(): void
    {
        $a = DB::table('sup_atendimentos')->find($this->atendimentoId);
        if (! $a || ! $a->via_central) {
            return;
        }
        $cliente = CentralDemandasClient::doTenant();
        // um envio por atendimento de cada vez: duas cópias do job não entregam a mesma mensagem duas vezes
        $chave = crc32("central-sup-{$a->id}");
        DB::select('select pg_advisory_lock(?)', [$chave]);
        try {
            $demanda = $this->garantirDemanda($cliente, $a);
            $this->entregarMensagens($cliente, $a, $demanda);
            match ($this->acao) {
                'avaliacao' => $cliente->post("demandas/{$demanda}/avaliacao", ['nota' => (int) $this->dados['nota'], 'comentario' => $this->dados['comentario'] ?? null]),
                'retorno' => $cliente->post("demandas/{$demanda}/retorno", ['resolveu' => (bool) $this->dados['resolveu'], 'comentario' => $this->dados['comentario'] ?? null]),
                default => null,
            };
            DB::table('sup_atendimentos')->where('id', $a->id)->update(['central_erro' => null]);
        } catch (Throwable $e) {
            DB::table('sup_atendimentos')->where('id', $a->id)->update(['central_erro' => mb_substr($e->getMessage(), 0, 500)]);
            throw $e;
        } finally {
            DB::select('select pg_advisory_unlock(?)', [$chave]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::warning('Atendimento não entregue à Central de Demandas', ['atendimento' => $this->atendimentoId, 'acao' => $this->acao, 'erro' => $e->getMessage()]);
    }

    /** demanda_id na Central; cria a demanda com a primeira mensagem (a descrição) se ainda não existe. */
    private function garantirDemanda(CentralDemandasClient $cliente, object $a): int
    {
        if ($a->central_id) {
            return (int) $a->central_id;
        }
        $primeira = DB::table('sup_mensagens')->where('atendimento_id', $a->id)->where('tipo', 'mensagem')->orderBy('id')->first();
        $u = DB::table('users')->find($a->solicitante_id);
        $ug = $a->ug_id ? DB::table('ugs')->find($a->ug_id) : null;
        $tenant = Tenant::find($this->tenant_id);
        $r = $cliente->post('demandas', [
            'origem' => array_filter([
                'sistema' => 'GED', 'municipio' => $tenant?->nome, 'tenant_id' => $this->tenant_id,
                'atendimento_id' => $a->id, 'atendimento_numero' => $a->numero,
                'url' => $this->base ? rtrim($this->base, '/') . "/suporte/atendimentos/{$a->id}" : null,
                'modulo' => $a->modulo, 'submodulo' => $a->submodulo,
            ], fn ($v) => $v !== null && $v !== ''),
            'solicitante' => array_filter([
                'id' => "ged:{$this->tenant_id}:{$a->solicitante_id}", 'nome' => $u?->name ?? 'Servidor', 'email' => $u?->email,
                'setor' => $u?->unidade_id ? DB::table('ug_organograma')->where('id', $u->unidade_id)->value('nome') : null,
                'ug' => $ug?->nome, 'ug_codigo' => $ug?->codigo,
            ], fn ($v) => $v !== null && $v !== ''),
            'titulo' => $a->titulo,
            'tela' => $a->tela ?: $a->url_tela,
            'mensagens' => $primeira ? [$this->mensagem($primeira, $u?->name)] : [],
            // o chat do suporte ainda não aceita arquivos; quando aceitar, cada anexo vai como {nome, url assinada por 90 dias, sha256}
            'anexos' => [],
        ]);
        $id = (int) ($r['demanda_id'] ?? 0);
        DB::table('sup_atendimentos')->where('id', $a->id)->update(['central_id' => $id ?: null, 'central_numero' => $r['numero'] ?? null,
            'central_enviado_em' => now()]);
        if ($primeira) {
            DB::table('sup_mensagens')->where('id', $primeira->id)->update(['central_enviada_em' => now()]);
        }
        if (! empty($r['numero'])) {
            \App\Support\Suporte::sistema($a->id, "Pedido entregue à equipe Conceito: demanda {$r['numero']}.");
        }

        return $id;
    }

    /** Mensagens de pessoas do GED ainda não entregues (nunca notas internas nem mensagens de sistema). */
    private function entregarMensagens(CentralDemandasClient $cliente, object $a, int $demanda): void
    {
        $pendentes = DB::table('sup_mensagens as m')->leftJoin('users as u', 'u.id', '=', 'm.autor_id')->where('m.atendimento_id', $a->id)
            ->where('m.tipo', 'mensagem')->whereNotNull('m.autor_id')->whereNull('m.central_enviada_em')->orderBy('m.id')
            ->get(['m.*', 'u.name as autor_nome']);
        foreach ($pendentes as $m) {
            $msg = $this->mensagem($m, $m->autor_nome);
            $cliente->post("demandas/{$demanda}/mensagens", ['autor' => $msg['autor'], 'texto' => $msg['texto'], 'em' => $msg['em']]);
            DB::table('sup_mensagens')->where('id', $m->id)->update(['central_enviada_em' => now()]);
        }
    }

    private function mensagem(object $m, ?string $autor): array
    {
        return ['autor' => $autor ?? 'Servidor', 'papel' => 'solicitante', 'texto' => $m->conteudo,
            'em' => Carbon::parse($m->created_at, 'UTC')->toIso8601ZuluString()];
    }
}
