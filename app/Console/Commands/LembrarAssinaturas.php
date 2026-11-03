<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\AvisarServidorPorEmail;
use App\Models\Assinatura;
use App\Models\Notificacao;
use App\Models\SolicitacaoAssinatura;
use App\Models\Tenant;
use App\Services\ConviteAssinaturaService;
use App\Services\FluxoAssinaturaService;
use App\Services\PacoteAssinaturaService;
use App\Support\Expediente;
use App\Tenant\TenantContext;
use Illuminate\Console\Command;

/**
 * Lembrete automático de assinatura pendente: a cada N dias (auth.lembrete_assinatura_dias) e na véspera
 * do prazo da solicitação, lembra quem está na vez de assinar — interno por notificação e e-mail, externo
 * pelo convite de novo — e avisa o solicitante quando o prazo vence sem todas as assinaturas.
 * Agendado diariamente (routes/console.php).
 */
class LembrarAssinaturas extends Command
{
    protected $signature = 'assinaturas:lembrar {--tenant=ALL : id do município ou ALL}';

    protected $description = 'Lembra os signatários de assinaturas pendentes e avisa o solicitante do prazo vencido';

    public function handle(TenantContext $ctx, FluxoAssinaturaService $fluxo, ConviteAssinaturaService $convites): int
    {
        $tenants = Tenant::active()->get()
            ->filter(fn (Tenant $t) => $this->option('tenant') === 'ALL' || (string) $t->id === (string) $this->option('tenant'));
        foreach ($tenants as $tenant) {
            $ctx->set($tenant);
            [$lembretes, $vencidos] = [$this->lembrar($fluxo, $convites), $this->avisarVencidos()];
            $this->info("{$tenant->id}: {$lembretes} lembrete(s), {$vencidos} prazo(s) vencido(s) avisado(s)");
        }

        return self::SUCCESS;
    }

    private function lembrar(FluxoAssinaturaService $fluxo, ConviteAssinaturaService $convites): int
    {
        $periodo = (int) config('auth.lembrete_assinatura_dias', 3);
        $amanha = now(Expediente::FUSO)->addDay()->toDateString();
        $n = 0;
        $pendentes = Assinatura::with(['solicitacao', 'documento'])->where('status', 'pendente')
            ->whereHas('solicitacao', fn ($q) => $q->whereIn('status', ['pendente', 'em_andamento']))->get();
        foreach ($pendentes as $a) {
            if (! $fluxo->ehAVez($a)) {
                continue;   // em ordem: só quem já pode assinar
            }
            $prazo = $a->solicitacao->prazo;
            $vespera = $prazo && $prazo->copy()->setTimezone(Expediente::FUSO)->toDateString() === $amanha && ! $a->lembrete_vespera_em;
            $ultimo = $a->lembrado_em ?? $a->created_at;
            $periodico = $periodo > 0 && $ultimo && $ultimo->lte(now()->subDays($periodo));
            if (! $vespera && ! $periodico) {
                continue;
            }
            $nome = $a->documento?->nome ?? 'documento';
            $quando = $prazo ? ' O prazo é ' . $prazo->copy()->setTimezone(Expediente::FUSO)->format('d/m/Y') . '.' : '';
            if ($a->externo) {
                $a->pacoteConvite ? app(PacoteAssinaturaService::class)->enviar($a->pacoteConvite) : $convites->enviar($a);
            } elseif ($a->signatario_id) {
                $notif = Notificacao::create([
                    'usuario_id' => $a->signatario_id, 'tipo' => 'assinatura_pendente',
                    'titulo' => $vespera ? 'Assinatura pendente: o prazo termina amanhã' : 'Lembrete: assinatura pendente',
                    'mensagem' => "O documento \"{$nome}\" aguarda a sua assinatura.{$quando}",
                    'referencia_tipo' => 'documento', 'referencia_id' => $a->documento_id, 'lida' => false,
                ]);
                AvisarServidorPorEmail::dispatch($notif->id, null, forcar: true);
            }
            $a->forceFill(array_filter(['lembrado_em' => now(), 'lembrete_vespera_em' => $vespera ? now() : null]))->saveQuietly();
            $n++;
        }

        return $n;
    }

    /** Prazo vencido sem todas as assinaturas: avisa quem pediu, uma vez. */
    private function avisarVencidos(): int
    {
        $n = 0;
        $vencidas = SolicitacaoAssinatura::with(['documento', 'assinaturas'])->whereIn('status', ['pendente', 'em_andamento'])
            ->whereNotNull('prazo')->where('prazo', '<', now())->whereNull('prazo_vencido_avisado_em')->get();
        foreach ($vencidas as $s) {
            $faltam = $s->assinaturas->where('status', 'pendente')
                ->map(fn ($a) => $a->externo ? ($a->nome_signatario ?: $a->email_signatario) : ($a->signatario?->name ?? $a->email_signatario))
                ->filter()->implode(', ');
            if ($s->solicitante_id) {
                $notif = Notificacao::create([
                    'usuario_id' => $s->solicitante_id, 'tipo' => 'assinatura_prazo',
                    'titulo' => 'Prazo de assinatura vencido',
                    'mensagem' => "O prazo da assinatura de \"{$s->documento?->nome}\" venceu sem todas as assinaturas. Faltam: {$faltam}.",
                    'referencia_tipo' => 'documento', 'referencia_id' => $s->documento_id, 'lida' => false,
                ]);
                AvisarServidorPorEmail::dispatch($notif->id, null, forcar: true);
            }
            $s->forceFill(['prazo_vencido_avisado_em' => now()])->saveQuietly();
            $n++;
        }

        return $n;
    }
}
