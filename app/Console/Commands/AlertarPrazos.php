<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\AvisarServidorPorEmail;
use App\Models\Notificacao;
use App\Models\Processo\Prazo;
use App\Models\Tenant;
use App\Support\Expediente;
use App\Support\Setores;
use App\Tenant\TenantContext;
use Illuminate\Console\Command;

/**
 * Alerta individual de vencimento dos prazos lançados à mão (TR 4.32.17): quando
 * faltam "alerta_dias" dias, avisa no sistema quem enxerga o prazo — o criador
 * (pessoal), quem atende o setor (setor) ou o criador e quem está com o processo
 * (todos) — e, se o prazo pediu, também por e-mail. Um aviso por prazo.
 * Agendado diariamente (routes/console.php).
 */
class AlertarPrazos extends Command
{
    protected $signature = 'processos:alertar-prazos {--tenant=ALL : id do município ou ALL}';

    protected $description = 'Avisa os prazos de processo que estão para vencer (alerta individual de cada prazo)';

    public function handle(TenantContext $ctx): int
    {
        $tenants = Tenant::active()->get()
            ->filter(fn (Tenant $t) => $this->option('tenant') === 'ALL' || (string) $t->id === (string) $this->option('tenant'));

        foreach ($tenants as $tenant) {
            $ctx->set($tenant);
            Expediente::esquecer();
            Setores::esquecer();
            $this->info("{$tenant->id}: " . $this->alertar($tenant) . ' aviso(s)');
        }

        return self::SUCCESS;
    }

    private function alertar(Tenant $tenant): int
    {
        $avisos = 0;
        $prazos = Prazo::with(['processo' => fn ($q) => $q->withoutGlobalScopes()])
            ->whereNull('concluido_em')->whereNull('alertado_em')->whereNotNull('alerta_dias')
            ->where('vence_em', '>', now())
            ->get()
            ->filter(fn (Prazo $p) => $p->processo && now()->gte($p->vence_em->copy()->subDays($p->alerta_dias)));

        foreach ($prazos as $prazo) {
            $quando = $prazo->vence_em->copy()->setTimezone(Expediente::FUSO)->format('d/m/Y H:i');
            foreach ($this->destinatarios($prazo) as $uid) {
                $n = Notificacao::withoutEvents(fn () => Notificacao::create([
                    'usuario_id'      => $uid,
                    'tipo'            => 'prazo',
                    'titulo'          => 'Prazo vencendo',
                    'mensagem'        => "\"{$prazo->titulo}\" do processo {$prazo->processo->numero_protocolo} vence em {$quando}.",
                    'referencia_tipo' => 'processo',
                    'referencia_id'   => $prazo->processo_id,
                    'lida'            => false,
                ]));
                // Por e-mail só quando o prazo pediu (o aviso é do prazo, não da preferência geral)
                if ($prazo->alerta_email) {
                    AvisarServidorPorEmail::dispatch($n->id, null, forcar: true);   // no console o link usa app.url
                }
                $avisos++;
            }
            $prazo->forceFill(['alertado_em' => now()])->saveQuietly();
        }

        return $avisos;
    }

    /** @return list<int> */
    private function destinatarios(Prazo $prazo): array
    {
        $ids = match ($prazo->escopo) {
            'pessoal' => [$prazo->criado_por],
            'setor'   => Setores::servidores((int) $prazo->unidade_id),
            default   => [$prazo->criado_por, ...\App\Services\AcaoNoProcesso::etapasAtivas($prazo->processo)
                ->flatMap(fn ($t) => $t->destinatario_id ? [$t->destinatario_id] : Setores::servidores((int) $t->destino_unidade_id))->all()],
        };

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }
}
