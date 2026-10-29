<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Middleware\BloquearUsuarioSuspenso;
use App\Models\UgOrganograma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Organograma dinâmico (TR 4.12): a árvore sai dos setores e dos servidores ligados a
 * eles, sem limite de níveis. Desativar um setor exige realocar antes quem está nele.
 */
final class Organograma
{
    /** Setores da UG em árvore, cada um com os contatos e o responsável. */
    public static function arvore(int $ugId, bool $soAtivos = false): array
    {
        $nos = UgOrganograma::query()->with('responsavel:id,name,cargo')
            ->where('ug_id', $ugId)->when($soAtivos, fn ($q) => $q->where('ativo', true))
            ->orderBy('codigo')->orderBy('nome')
            ->get(['id', 'parent_id', 'nivel', 'codigo', 'sigla', 'nome', 'ativo', 'telefone', 'ramal', 'email', 'responsavel_id']);

        $porPai = $nos->groupBy(fn ($n) => $n->parent_id ?? 0);
        $montar = function (int $pai) use (&$montar, $porPai): array {
            return ($porPai[$pai] ?? collect())->map(fn (UgOrganograma $n) => [
                'id' => $n->id, 'nivel' => $n->nivel, 'codigo' => $n->codigo, 'sigla' => $n->sigla, 'nome' => $n->nome,
                'ativo' => $n->ativo, 'telefone' => $n->telefone, 'ramal' => $n->ramal, 'email' => $n->email,
                'responsavel' => $n->responsavel ? ['nome' => $n->responsavel->name, 'cargo' => $n->responsavel->cargo] : null,
                'filhos' => $montar($n->id),
            ])->values()->all();
        };

        // Setor cujo pai ficou de fora (pai inativo na versão pública) não aparece solto
        return $montar(0);
    }

    /**
     * Servidores por setor (principal e secundários): nome, cargo, cadastro, último
     * acesso e se está online agora (TR 4.12.3, 4.12.4). @return array<int, list<array>>
     */
    public static function servidoresPorSetor(int $ugId): array
    {
        $limiteOnline = now()->subMinutes(BloquearUsuarioSuspenso::MINUTOS_ONLINE);
        $usuarios = User::query()->where('tipo', 'interno')->whereNull('suspenso_em')
            ->where(fn ($q) => $q->where('ug_id', $ugId)->orWhereIn('unidade_id', UgOrganograma::where('ug_id', $ugId)->select('id')))
            ->get(['id', 'name', 'email', 'cargo', 'telefone', 'unidade_id', 'created_at', 'ultimo_acesso_em', 'ultima_atividade_em', 'foto_path'])
            ->keyBy('id');

        $linha = fn (User $u, bool $principal) => [
            'id' => $u->id, 'nome' => $u->name, 'cargo' => $u->cargo, 'email' => $u->email, 'foto_url' => $u->foto_url,
            'cadastro' => $u->created_at?->setTimezone('America/Sao_Paulo')->format('d/m/Y'),
            'ultimo_acesso' => $u->ultima_atividade_em?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            'online' => (bool) $u->ultima_atividade_em?->gte($limiteOnline),
            'principal' => $principal,
        ];

        $porSetor = [];
        foreach ($usuarios as $u) {
            if ($u->unidade_id) {
                $porSetor[$u->unidade_id][] = $linha($u, true);
            }
        }
        $secundarios = DB::table('user_setores')->whereIn('user_id', $usuarios->keys())->get(['user_id', 'unidade_id']);
        foreach ($secundarios as $s) {
            if ((int) $s->unidade_id !== (int) $usuarios[$s->user_id]->unidade_id) {
                $porSetor[$s->unidade_id][] = $linha($usuarios[$s->user_id], false);
            }
        }
        foreach ($porSetor as &$lista) {
            usort($lista, fn ($a, $b) => [$b['online'], $a['nome']] <=> [$a['online'], $b['nome']]);
        }

        return $porSetor;
    }

    /** O que impede ou pesa na desativação do setor. */
    public static function impedimentos(UgOrganograma $no): array
    {
        return [
            'principal'   => User::where('unidade_id', $no->id)->count(),
            'secundarios' => DB::table('user_setores')->where('unidade_id', $no->id)->count(),
            'subsetores'  => UgOrganograma::where('parent_id', $no->id)->where('ativo', true)->count(),
            'pendentes'   => DB::table('proc_inbox')->where('destino_unidade_id', $no->id)
                ->whereNotIn('status', ['concluido', 'cancelado', 'arquivado'])->count(),
        ];
    }

    /**
     * Desativa o setor (TR 4.12.9, 4.12.10). Com servidores nele, só realocando para
     * outro setor ativo da mesma UG: o principal passa a ser o destino e o vínculo
     * secundário é trocado pelo destino. Subsetores ativos precisam ser desativados antes.
     * Documentos e histórico continuam no setor (TR 4.9.15).
     */
    public static function desativar(UgOrganograma $no, ?int $destinoId): array
    {
        $imp = self::impedimentos($no);
        if ($imp['subsetores'] > 0) {
            throw ValidationException::withMessages(['destino_id' => "Desative antes os {$imp['subsetores']} subsetor(es) ativos de \"{$no->nome}\"."]);
        }
        $temServidores = $imp['principal'] + $imp['secundarios'] > 0;
        $destino = null;
        if ($temServidores) {
            $destino = $destinoId ? UgOrganograma::where('ug_id', $no->ug_id)->where('ativo', true)->whereKeyNot($no->id)->find($destinoId) : null;
            if (! $destino) {
                throw ValidationException::withMessages(['destino_id' => 'Escolha o setor ativo para onde vão os servidores deste setor.']);
            }
        }

        DB::transaction(function () use ($no, $destino) {
            if ($destino) {
                $movidos = User::where('unidade_id', $no->id)->pluck('id');
                User::where('unidade_id', $no->id)->update(['unidade_id' => $destino->id]);
                // Atualização em massa não dispara o aviso do modelo: avisa aqui (TR 4.11.3)
                foreach ($movidos as $uid) {
                    \App\Models\Notificacao::create(['usuario_id' => $uid, 'tipo' => 'troca_setor', 'titulo' => 'Seu setor principal mudou',
                        'mensagem' => "\"{$no->nome}\" foi desativado. Agora: {$destino->nome}", 'lida' => false]);
                }
                $comVinculo = DB::table('user_setores')->where('unidade_id', $no->id)->pluck('user_id');
                DB::table('user_setores')->where('unidade_id', $no->id)->delete();
                foreach ($comVinculo as $userId) {
                    $jaEsta = User::whereKey($userId)->where('unidade_id', $destino->id)->exists()
                        || DB::table('user_setores')->where(['user_id' => $userId, 'unidade_id' => $destino->id])->exists();
                    if (! $jaEsta) {
                        DB::table('user_setores')->insert(['user_id' => $userId, 'unidade_id' => $destino->id, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            }
            $no->update(['ativo' => false, 'dt_encerramento' => $no->dt_encerramento ?? now()->toDateString()]);
        });
        Setores::esquecer();

        return ['realocados' => $imp['principal'] + $imp['secundarios'], 'destino' => $destino?->nome, 'pendentes' => $imp['pendentes']];
    }

    /** Reativa (TR 4.12.11). O setor de cima precisa estar ativo. */
    public static function reativar(UgOrganograma $no): void
    {
        if ($no->parent_id && ! UgOrganograma::whereKey($no->parent_id)->where('ativo', true)->exists()) {
            throw ValidationException::withMessages(['destino_id' => 'Reative antes o setor de cima.']);
        }
        $no->update(['ativo' => true, 'dt_encerramento' => null]);
    }

    /** Mensagem de retorno da desativação. */
    public static function mensagem(UgOrganograma $no, array $r): string
    {
        $msg = "Setor \"{$no->nome}\" desativado.";
        if ($r['realocados']) {
            $msg .= " {$r['realocados']} vínculo(s) de servidor foram para \"{$r['destino']}\".";
        }
        if ($r['pendentes']) {
            $msg .= " Atenção: {$r['pendentes']} item(ns) em aberto continuam na caixa do setor e precisam ser encaminhados.";
        }

        return $msg;
    }
}
