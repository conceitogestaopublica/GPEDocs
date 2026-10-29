<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Processo\Memorando;
use Illuminate\Support\Carbon;

/**
 * Memorando (TR 4.45): despachos numerados — respostas e encaminhamentos numa linha do
 * tempo só (4.45.5, 4.45.15) — e quem está envolvido com a situação de cada um (4.45.13–14).
 */
final class Memorandos
{
    /** @return list<array{n: int, tipo: string, autor: ?string, de: ?string, para: ?string, html: string, em: ?string}> */
    public static function despachos(Memorando $m): array
    {
        $m->loadMissing(['respostas.usuario:id,name', 'tramitacoes.origemUsuario:id,name', 'tramitacoes.origemUnidade:id,nome',
            'tramitacoes.destinoUsuario:id,name', 'tramitacoes.destinoUnidade:id,nome']);

        $itens = collect();
        foreach ($m->respostas as $r) {
            $itens->push(['tipo' => 'Resposta', 'autor' => $r->usuario?->name, 'de' => null, 'para' => null,
                'html' => HtmlSeguro::limpar($r->conteudo), 'quando' => $r->created_at]);
        }
        foreach ($m->tramitacoes as $t) {
            $itens->push(['tipo' => 'Encaminhamento', 'autor' => $t->origemUsuario?->name, 'de' => $t->origemUnidade?->nome,
                'para' => $t->destinoUsuario?->name ?? $t->destinoUnidade?->nome,
                'html' => HtmlSeguro::limpar($t->parecer), 'quando' => $t->despachado_em ?? $t->created_at]);
        }

        return $itens->sortBy(fn ($i) => $i['quando']?->getTimestamp() ?? 0)->values()
            ->map(fn ($i, $k) => ['n' => $k + 1] + array_diff_key($i, ['quando' => 1]) + ['em' => $i['quando']?->toIso8601String()])
            ->all();
    }

    /**
     * Setores e pessoas por onde o memorando passou, com a situação de cada um: o destino
     * original (recebido ou não) e cada encaminhamento (aguardando, com o setor, despachou).
     *
     * @return list<array{nome: string, tipo: string, situacao: string, quando: ?string}>
     */
    public static function envolvidos(Memorando $m): array
    {
        $m->loadMissing(['destinatarios.usuario:id,name', 'destinatarios.unidade:id,nome', 'tramitacoes.destinoUsuario:id,name', 'tramitacoes.destinoUnidade:id,nome']);
        $linhas = [];
        $chave = fn ($usuario, $unidade) => $usuario ? "u{$usuario}" : "s{$unidade}";

        foreach ($m->destinatarios as $d) {
            $linhas[$chave($d->usuario_id, $d->unidade_id)] = [
                'nome' => $d->usuario?->name ?? $d->unidade?->nome ?? $d->setor_destino ?? '—', 'tipo' => $d->usuario_id ? 'pessoa' : 'setor',
                'situacao' => $d->lido ? 'Recebido' : 'Não recebido', 'quando' => self::iso($d->lido_em),
            ];
        }
        foreach ($m->tramitacoes->sortBy('id') as $t) {
            $situacao = match (true) {
                ! $t->em_uso         => 'Despachou adiante',
                ! $t->finalizado     => 'Aguardando recebimento',
                default              => 'Com o setor',
            };
            $linhas[$chave($t->destino_usuario_id, $t->destino_unidade_id)] = [
                'nome' => $t->destinoUsuario?->name ?? $t->destinoUnidade?->nome ?? '—', 'tipo' => $t->destino_usuario_id ? 'pessoa' : 'setor',
                'situacao' => $situacao, 'quando' => self::iso($t->recebido_em ?? $t->despachado_em),
            ];
        }

        return array_values($linhas);
    }

    private static function iso($d): ?string
    {
        return $d ? Carbon::parse($d)->toIso8601String() : null;
    }
}
