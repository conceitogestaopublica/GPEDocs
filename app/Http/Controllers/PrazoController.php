<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Processo\Prazo;
use App\Models\Processo\Processo;
use App\Models\Processo\ProcessoHistorico;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Prazos e atividades lançados à mão no processo (TR 4.13.1, 4.13.2, 4.13.6–4.13.8,
 * 4.32.16, 4.32.17, 4.38.16). Qualquer setor que vê o processo inclui, altera, marca
 * como realizado ou exclui — tudo no histórico do processo. A data/hora digitada é
 * de Brasília; o banco guarda em UTC.
 */
class PrazoController extends Controller
{
    public function store(Request $request, int $processoId)
    {
        $processo = Processo::findOrFail($processoId);
        $dados = $this->validar($request);

        $prazo = DB::transaction(function () use ($request, $processo, $dados) {
            $prazo = Prazo::create($dados + ['processo_id' => $processo->id, 'criado_por' => Auth::id()]);
            $this->historico($request, $processo->id, 'prazo_criado', $prazo, ['vence_em' => $this->br($prazo->vence_em)]);

            return $prazo;
        });

        return back()->with('success', "Prazo \"{$prazo->titulo}\" incluído.");
    }

    public function update(Request $request, int $id)
    {
        $prazo = $this->visivel($id);
        abort_if($prazo->concluido_em, 422, 'Prazo já realizado não é alterado.');
        $dados = $this->validar($request);

        DB::transaction(function () use ($request, $prazo, $dados) {
            $antes = ['titulo' => $prazo->titulo, 'vence_em' => $this->br($prazo->vence_em), 'escopo' => $prazo->escopo];
            // Mudou o vencimento ou o aviso: volta a avisar
            $prazo->update($dados + ['alertado_em' => null]);
            $this->historico($request, $prazo->processo_id, 'prazo_alterado', $prazo, ['antes' => $antes,
                'depois' => ['titulo' => $prazo->titulo, 'vence_em' => $this->br($prazo->vence_em), 'escopo' => $prazo->escopo]]);
        });

        return back()->with('success', 'Prazo alterado.');
    }

    public function concluir(Request $request, int $id)
    {
        $prazo = $this->visivel($id);
        $marcou = Prazo::whereKey($prazo->id)->whereNull('concluido_em')
            ->update(['concluido_em' => now(), 'concluido_por' => Auth::id()]);
        if ($marcou) {
            $this->historico($request, $prazo->processo_id, 'prazo_realizado', $prazo, ['no_prazo' => $prazo->vence_em->isFuture()]);
        }

        return back()->with('success', 'Prazo marcado como realizado.');
    }

    public function destroy(Request $request, int $id)
    {
        $prazo = $this->visivel($id);
        DB::transaction(function () use ($request, $prazo) {
            $prazo->delete();   // fica guardado (softDeletes)
            $this->historico($request, $prazo->processo_id, 'prazo_excluido', $prazo, ['vence_em' => $this->br($prazo->vence_em)]);
        });

        return back()->with('success', 'Prazo excluído.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request): array
    {
        $d = $request->validate([
            'titulo'       => ['required', 'string', 'max:150'],
            'descricao'    => ['nullable', 'string', 'max:2000'],
            'vence_em'     => ['required_without:dias_uteis', 'nullable', 'date'],
            // Ou conta N dias úteis a partir de agora, no fechamento do expediente (TR 4.40.10)
            'dias_uteis'   => ['nullable', 'integer', 'between:1,365'],
            'escopo'       => ['required', 'in:todos,setor,pessoal'],
            'unidade_id'   => ['required_if:escopo,setor', 'nullable', 'integer', 'exists:ug_organograma,id'],
            'alerta_dias'  => ['nullable', 'integer', 'between:0,365'],
            'alerta_email' => ['nullable', 'boolean'],
        ], ['unidade_id.required_if' => 'Escolha o setor do prazo.']);

        return [
            'titulo'       => trim($d['titulo']),
            'descricao'    => $d['descricao'] ?? null,
            'vence_em'     => ! empty($d['dias_uteis'])
                ? Carbon::instance(\App\Support\Expediente::somarDiasUteis(now(), (int) $d['dias_uteis']))->utc()
                : Carbon::parse($d['vence_em'], \App\Support\Expediente::FUSO)->utc(),
            'escopo'       => $d['escopo'],
            'unidade_id'   => $d['escopo'] === 'setor' ? (int) $d['unidade_id'] : null,
            'alerta_dias'  => isset($d['alerta_dias']) && $d['alerta_dias'] !== '' ? (int) $d['alerta_dias'] : null,
            'alerta_email' => (bool) ($d['alerta_email'] ?? false),
        ];
    }

    private function visivel(int $id): Prazo
    {
        $prazo = Prazo::visiveisPara(Auth::user())->findOrFail($id);
        // O processo tem escopo de UG: o de outra UG não aparece
        abort_unless(Processo::whereKey($prazo->processo_id)->exists(), 404);

        return $prazo;
    }

    private function historico(Request $request, int $processoId, string $acao, Prazo $prazo, array $extra = []): void
    {
        ProcessoHistorico::create([
            'processo_id' => $processoId,
            'usuario_id'  => Auth::id(),
            'acao'        => $acao,
            'detalhes'    => ['prazo_id' => $prazo->id, 'titulo' => $prazo->titulo] + $extra,
            'ip'          => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);
    }

    private function br(?\DateTimeInterface $d): ?string
    {
        return $d ? Carbon::instance($d)->setTimezone(\App\Support\Expediente::FUSO)->format('d/m/Y H:i') : null;
    }
}
