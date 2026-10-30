<?php

declare(strict_types=1);

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use App\Models\Processo\Prazo;
use App\Models\Processo\Tramitacao;
use App\Support\Expediente;
use App\Support\Setores;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Calendário mensal de prazos (TR 4.13.3): os prazos lançados à mão que o usuário
 * enxerga e o prazo das etapas que estão com ele ou com um setor dele, com os
 * feriados do expediente. Filtro: tudo, só meus, só do setor.
 */
class CalendarioPrazosController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $mes = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('mes'))
            ? CarbonImmutable::createFromFormat('!Y-m', $request->input('mes'), Expediente::FUSO)
            : CarbonImmutable::now(Expediente::FUSO)->startOfMonth();
        $de = $mes->startOfMonth()->utc();
        $ate = $mes->endOfMonth()->utc();
        $filtro = in_array($request->input('filtro'), ['meus', 'setor'], true) ? $request->input('filtro') : 'todos';
        $setores = Setores::doUsuario($user);

        $manuais = Prazo::visiveisPara($user)
            ->whereHas('processo')   // escopo de UG do processo
            ->with('processo:id,numero_protocolo,assunto')
            ->whereBetween('vence_em', [$de, $ate])
            ->when($filtro === 'meus', fn ($q) => $q->where('escopo', 'pessoal'))
            ->when($filtro === 'setor', fn ($q) => $q->where('escopo', 'setor'))
            ->get()
            ->map(fn (Prazo $p) => $this->item($p->vence_em, $p->titulo, $p->processo, 'prazo', $p->situacao()));

        $etapas = Tramitacao::query()
            ->whereHas('processo')
            ->with('processo:id,numero_protocolo,assunto')
            ->where('modo', 'tramite')->whereIn('status', ['pendente', 'recebido'])
            ->whereBetween('prazo', [$de, $ate])
            ->where(function ($q) use ($user, $setores, $filtro) {
                if ($filtro !== 'setor') {
                    $q->orWhere('destinatario_id', $user->id);
                }
                if ($filtro !== 'meus' && $setores) {
                    $q->orWhere(fn ($q) => $q->whereNull('destinatario_id')->whereIn('destino_unidade_id', $setores));
                }
            })
            ->get()
            ->map(fn (Tramitacao $t) => $this->item($t->prazo, "Etapa em {$t->setor_destino}", $t->processo, 'etapa',
                $t->prazo->isPast() ? 'vencido' : ($t->prazo->lte(now()->addHours(Prazo::HORAS_VENCENDO)) ? 'vencendo' : 'com_folga')));

        return Inertia::render('GED/Flow/CalendarioPrazos', [
            'mes'      => $mes->format('Y-m'),
            'filtro'   => $filtro,
            'itens'    => $manuais->concat($etapas)->sortBy('quando')->values(),
            'feriados' => DB::table('expediente_feriados')
                ->whereBetween('data', [$mes->startOfMonth()->format('Y-m-d'), $mes->endOfMonth()->format('Y-m-d')])
                ->get(['data', 'nome', 'tipo'])->map(fn ($f) => ['data' => substr((string) $f->data, 0, 10), 'nome' => $f->nome, 'tipo' => $f->tipo]),
            'dias_uteis' => Expediente::config()['dias_semana'],
        ]);
    }

    private function item(\DateTimeInterface $quando, string $titulo, $processo, string $tipo, string $situacao): array
    {
        $local = CarbonImmutable::instance($quando)->setTimezone(Expediente::FUSO);

        return [
            'quando'   => $local->toIso8601String(),
            'dia'      => $local->format('Y-m-d'),
            'hora'     => $local->format('H:i'),
            'titulo'   => $titulo,
            'tipo'     => $tipo,
            'situacao' => $situacao,
            'processo' => ['id' => $processo->id, 'numero' => $processo->numero_protocolo, 'assunto' => $processo->assunto],
        ];
    }
}
