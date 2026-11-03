<?php

declare(strict_types=1);

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use App\Models\Processo\Processo;
use App\Services\AcaoNoProcesso;
use App\Support\RequerimentoServidor;
use App\Support\VinculosProcesso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sobrestamento, apensação/desapensação e anexação de processos (regras em App\Support\VinculosProcesso).
 * Quem pode: quem está na etapa ativa da frente principal (AcaoNoProcesso) — nos DOIS processos, ao vincular.
 */
class VinculoProcessoController extends Controller
{
    public function sobrestar(Request $request, int $id)
    {
        $d = $request->validate(['motivo' => ['required', 'string', 'max:1000'], 'ate' => ['nullable', 'date', 'after:today'],
            'condicao' => ['nullable', 'string', 'max:300']]);

        return $this->executar($id, 'sobrestar', fn (Processo $p) => VinculosProcesso::sobrestar($p, Auth::user(), $d['motivo'], $d['ate'] ?? null, $d['condicao'] ?? null),
            'Processo sobrestado: ninguém tramita e os prazos ficam parados até a retomada.');
    }

    public function retomar(Request $request, int $id)
    {
        $d = $request->validate(['observacao' => ['nullable', 'string', 'max:1000']]);

        return $this->executar($id, 'retomar', fn (Processo $p) => VinculosProcesso::retomar($p, Auth::user(), $d['observacao'] ?? ''),
            'Processo retomado: os prazos das etapas em aberto foram estendidos pelo tempo em que ficou sobrestado.');
    }

    /** Apensar ou anexar outro processo a este (o principal). */
    public function vincular(Request $request, int $id)
    {
        $d = $request->validate(['tipo' => ['required', 'in:apenso,anexo'], 'processo_id' => ['required', 'integer'], 'motivo' => ['required', 'string', 'max:1000']]);
        $outro = $this->processoComAcesso((int) $d['processo_id']);
        abort_unless(AcaoNoProcesso::avaliar($outro, Auth::user())['vincular'], 403,
            "Você não está na etapa ativa do processo {$outro->numero_protocolo}: só quem está com os dois processos pode vinculá-los.");

        return $this->executar($id, 'vincular', function (Processo $p) use ($d, $outro) {
            $d['tipo'] === 'apenso'
                ? VinculosProcesso::apensar($p, $outro, Auth::user(), $d['motivo'])
                : VinculosProcesso::anexar($p, $outro, Auth::user(), $d['motivo']);
        }, $d['tipo'] === 'apenso' ? "Processo {$outro->numero_protocolo} apensado: tramita junto com este até ser desapensado."
            : "Processo {$outro->numero_protocolo} anexado: as peças passaram a este processo e ele foi encerrado.");
    }

    public function desapensar(Request $request, int $id, int $apenso)
    {
        $d = $request->validate(['motivo' => ['required', 'string', 'max:1000']]);
        $outro = Processo::findOrFail($apenso);

        return $this->executar($id, 'desapensar', fn (Processo $p) => VinculosProcesso::desapensar($p, $outro, Auth::user(), $d['motivo']),
            "Processo {$outro->numero_protocolo} desapensado: voltou a tramitar no setor em que este processo está.");
    }

    /** Processos da UG para apensar ou anexar (por número ou assunto). */
    public function buscar(Request $request, int $id)
    {
        $b = trim((string) $request->input('q'));
        if (mb_strlen($b) < 2) {
            return response()->json([]);
        }
        $q = Processo::query()->where('id', '<>', $id)->whereNotIn('status', ['concluido', 'cancelado', 'aguardando_assinatura'])
            ->whereNull('sobrestado_em')->whereNull('apensado_a_id')->whereNull('anexado_a_id')
            ->where(fn ($w) => $w->where('numero_protocolo', 'ilike', "%{$b}%")->orWhere('assunto', 'ilike', "%{$b}%"));
        RequerimentoServidor::restringir($q, Auth::user());

        return response()->json($q->orderByDesc('id')->limit(15)->get(['id', 'numero_protocolo', 'assunto', 'status'])
            ->map(fn (Processo $p) => $p->only(['id', 'numero_protocolo', 'assunto', 'status']) + ['pode' => AcaoNoProcesso::avaliar($p, Auth::user())['vincular']]));
    }

    private function executar(int $id, string $acao, callable $fn, string $sucesso)
    {
        $p = $this->processoComAcesso($id);
        $acoes = AcaoNoProcesso::avaliar($p, Auth::user());
        $permitido = ['sobrestar' => $acoes['sobrestar'], 'retomar' => $acoes['retomar'], 'vincular' => $acoes['vincular'],
            'desapensar' => $acoes['vincular'] || $acoes['despachar']][$acao];
        abort_unless($permitido, 403, 'Só quem está na etapa ativa do processo pode fazer isso.');
        try {
            DB::transaction(fn () => $fn(Processo::lockForUpdate()->findOrFail($p->id)));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $sucesso);
    }

    private function processoComAcesso(int $id): Processo
    {
        $q = Processo::whereKey($id);
        RequerimentoServidor::restringir($q, Auth::user());

        return $q->firstOrFail();
    }
}
