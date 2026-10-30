<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Diario\Edicao;
use App\Models\Diario\Materia;
use App\Models\Diario\Responsavel;
use App\Models\Processo\Ato;
use App\Models\SolicitacaoAssinatura;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Regras do Diário Oficial (TR 4.43): quem monta e quem publica, envio do ato assinado ao diário,
 * edição ordinária (primeira do dia) ou extraordinária, montagem automática da ordinária com os
 * atos pendentes e a ordem das matérias na edição.
 */
final class DiarioOficial
{
    public static function podeGerir(?User $u): bool
    {
        return $u !== null && ($u->super_admin || $u->can('diario.gerir') || self::responsavel($u));
    }

    /** Responsável ativo publica sem nova autenticação (TR 4.43.5). */
    public static function podePublicar(?User $u): bool
    {
        return $u !== null && ($u->super_admin || self::responsavel($u));
    }

    private static function responsavel(User $u): bool
    {
        return Responsavel::where('user_id', $u->id)->where('ativo', true)->exists();
    }

    /** O ato só vai ao diário assinado por todos (TR 4.43.6). */
    public static function atoAssinado(Ato $ato): bool
    {
        return $ato->status === 'publicado'
            || ($ato->documento_id && SolicitacaoAssinatura::where('documento_id', $ato->documento_id)->latest('id')->value('status') === 'concluida');
    }

    /** Envia o ato ao diário, na edição escolhida ou sem edição (TR 4.43.7). */
    public static function enviar(Ato $ato, ?int $estruturaId, ?int $edicaoId, User $u): Materia
    {
        if (! self::atoAssinado($ato)) {
            throw ValidationException::withMessages(['ato' => 'O ato só vai ao Diário Oficial depois de assinado por todos.']);
        }
        if (Materia::withoutGlobalScopes()->where('ato_id', $ato->id)->exists()) {
            throw ValidationException::withMessages(['ato' => 'Este ato já foi enviado ao Diário Oficial.']);
        }
        if ($edicaoId && Edicao::whereKey($edicaoId)->value('status') !== 'pendente') {
            throw ValidationException::withMessages(['edicao_id' => 'Só dá para incluir em edição ainda não publicada.']);
        }

        return Materia::create(['ug_id' => $ato->ug_id, 'ato_id' => $ato->id, 'estrutura_id' => $estruturaId, 'edicao_id' => $edicaoId,
            'ordem' => $edicaoId ? ((int) Materia::where('edicao_id', $edicaoId)->max('ordem')) + 1 : 0, 'enviada_por' => $u->id]);
    }

    /** Ordinária se ainda não há edição no dia; senão extraordinária (TR 4.43.24). */
    public static function tipoParaData(int $ugId, CarbonInterface|string $data, ?int $ignorar = null): string
    {
        $dia = is_string($data) ? $data : $data->format('Y-m-d');

        return Edicao::withoutGlobalScopes()->where('ug_id', $ugId)->whereDate('data', $dia)
            ->when($ignorar, fn ($q) => $q->where('id', '<>', $ignorar))->exists() ? 'extraordinaria' : 'ordinaria';
    }

    public static function criarEdicao(int $ugId, string $data, ?User $u, bool $automatica = false, ?string $tipo = null): Edicao
    {
        return DB::transaction(function () use ($ugId, $data, $u, $automatica, $tipo) {
            DB::select('select pg_advisory_xact_lock(?)', [crc32("dof-{$ugId}-{$data}")]);
            $regra = self::tipoParaData($ugId, $data);

            return Edicao::create(['ug_id' => $ugId, 'data' => $data, 'tipo' => $regra === 'ordinaria' ? ($tipo ?? 'ordinaria') : 'extraordinaria',
                'status' => 'pendente', 'automatica' => $automatica, 'criada_por' => $u?->id, 'qr_code_token' => (string) Str::uuid()]);
        });
    }

    /**
     * Ordinária automática do dia (TR 4.43.8): se ainda não há edição na data e há atos pendentes,
     * cria a edição e põe neles, na ordem de caderno/seção/subseção e de envio.
     */
    public static function montarOrdinaria(int $ugId, string $data): ?Edicao
    {
        $pendentes = Materia::withoutGlobalScopes()->where('ug_id', $ugId)->whereNull('edicao_id')->count();
        if (! $pendentes || self::tipoParaData($ugId, $data) !== 'ordinaria') {
            return null;
        }
        $edicao = self::criarEdicao($ugId, $data, null, true);
        self::incluirPendentes($edicao);

        return $edicao;
    }

    /** Põe todos os atos sem edição na edição, ordenados pela estrutura. */
    public static function incluirPendentes(Edicao $e): int
    {
        $ids = Materia::withoutGlobalScopes()->where('ug_id', $e->ug_id)->whereNull('edicao_id')->pluck('id');
        Materia::withoutGlobalScopes()->whereIn('id', $ids)->update(['edicao_id' => $e->id]);
        self::reordenarPelaEstrutura($e);

        return $ids->count();
    }

    /** Ordem: caderno, seção e subseção (pela ordem cadastrada), depois a ordem atual. */
    public static function reordenarPelaEstrutura(Edicao $e): void
    {
        $ordemEstrutura = self::ordemDaEstrutura();
        $materias = Materia::withoutGlobalScopes()->where('edicao_id', $e->id)->orderBy('ordem')->orderBy('id')->get()
            ->sortBy(fn (Materia $m) => [$ordemEstrutura[$m->estrutura_id] ?? PHP_INT_MAX, $m->ordem, $m->id])->values();
        foreach ($materias as $i => $m) {
            $m->update(['ordem' => $i + 1]);
        }
    }

    /** @return array<int, int> estrutura_id → posição na árvore (caderno → seção → subseção) */
    public static function ordemDaEstrutura(): array
    {
        $todos = DB::table('dof_estrutura')->orderBy('ordem')->orderBy('nome')->get(['id', 'parent_id']);
        $filhos = $todos->groupBy(fn ($n) => (int) $n->parent_id);
        $saida = [];
        $percorrer = function (int $pai) use (&$percorrer, &$saida, $filhos) {
            foreach ($filhos[$pai] ?? [] as $n) {
                $saida[$n->id] = count($saida);
                $percorrer((int) $n->id);
            }
        };
        $percorrer(0);

        return $saida;
    }

    /** Sumário: matérias agrupadas pelo caminho da estrutura, na ordem da edição (TR 4.43.25). */
    public static function sumario(Edicao $e): array
    {
        $caminhos = self::caminhos();

        return $e->materias()->with(['ato.tipo'])->get()->map(fn (Materia $m) => [
            'id' => $m->id, 'ordem' => $m->ordem, 'estrutura_id' => $m->estrutura_id, 'grupo' => $caminhos[$m->estrutura_id] ?? 'Sem seção',
            'ato' => ['id' => $m->ato?->id, 'identificacao' => $m->ato?->identificacao(), 'ementa' => $m->ato?->ementa, 'tipo' => $m->ato?->tipo?->nome],
            'pagina' => $m->pagina_inicial,
        ])->all();
    }

    /** @return array<int, string> estrutura_id → "Caderno › Seção › Subseção" */
    public static function caminhos(): array
    {
        $nos = DB::table('dof_estrutura')->get(['id', 'parent_id', 'nome'])->keyBy('id');
        $saida = [];
        foreach ($nos as $id => $n) {
            $nomes = [$n->nome];
            $p = $n->parent_id;
            while ($p && isset($nos[$p])) {
                array_unshift($nomes, $nos[$p]->nome);
                $p = $nos[$p]->parent_id;
            }
            $saida[$id] = implode(' › ', $nomes);
        }

        return $saida;
    }
}
