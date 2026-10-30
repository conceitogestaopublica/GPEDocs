<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Notificacao;
use App\Models\Processo\Circular;
use App\Models\Processo\CircularDestinatario;
use App\Models\UgOrganograma;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Circular (TR 4.46): quem recebe (toda a UG, setores do organograma com os subsetores, ou
 * pessoas), quem vê cada resposta (abertas: todos; restritas: o remetente e quem respondeu)
 * e quem é avisado de uma resposta nova (quem silenciou, não).
 */
final class Circulares
{
    public const RESPOSTAS = ['abertas' => 'Abertas — todos veem', 'restritas' => 'Restritas — só o remetente vê', 'nenhuma' => 'Meramente informativa (sem respostas)'];

    /** Servidores ativos da UG que recebem. @return Collection<int, int> */
    public static function destinatarios(string $tipo, array $unidades, array $usuarios, int $exceto): Collection
    {
        $base = User::query()->where('tipo', 'interno')->whereNull('suspenso_em')->where('id', '<>', $exceto);

        $ids = match ($tipo) {
            'usuarios' => $base->whereIn('id', array_map('intval', $usuarios))->pluck('id'),
            'setores'  => collect(self::comSubsetores($unidades))->flatMap(fn ($u) => Setores::servidores($u))
                ->unique()->filter(fn ($id) => $id !== $exceto)
                ->pipe(fn ($c) => User::query()->whereIn('id', $c)->where('tipo', 'interno')->whereNull('suspenso_em')->pluck('id')),
            default    => $base->where(fn ($q) => $q->where('ug_id', session('ug_id'))
                ->orWhereIn('unidade_id', UgOrganograma::where('ug_id', session('ug_id'))->select('id')))->pluck('id'),
        };

        return $ids->map(fn ($id) => (int) $id)->unique()->values();
    }

    /** Os setores e todos os subsetores ativos deles. @return list<int> */
    public static function comSubsetores(array $unidades): array
    {
        $todos = [];
        $fila = array_map('intval', $unidades);
        while ($fila) {
            $todos = array_merge($todos, $fila);
            $fila = array_values(array_diff(UgOrganograma::whereIn('parent_id', $fila)->where('ativo', true)->pluck('id')->all(), $todos));
        }

        return array_values(array_unique($todos));
    }

    /** Inclui destinatários (envio ou encaminhamento) e avisa. Devolve quantos entraram. */
    public static function incluir(Circular $c, Collection $ids, string $titulo): int
    {
        $ja = $c->destinatarios()->pluck('usuario_id')->map(fn ($id) => (int) $id)->all();
        $novos = $ids->reject(fn ($id) => in_array($id, $ja, true));
        foreach ($novos as $id) {
            CircularDestinatario::create(['circular_id' => $c->id, 'usuario_id' => $id, 'lido' => false]);
            Notificacao::create([
                'usuario_id' => $id, 'tipo' => 'circular_recebida', 'urgente' => (bool) $c->urgente,
                'alcance' => $c->destino_tipo === 'usuarios' ? 'pessoal' : 'setor',
                'titulo' => ($c->urgente ? 'URGENTE — ' : '') . $titulo,
                'mensagem' => "Circular {$c->numero} - {$c->assunto}",
                'referencia_tipo' => 'circular', 'referencia_id' => $c->id, 'lida' => false,
            ]);
        }

        return $novos->count();
    }

    public static function ehRemetente(Circular $c, ?int $userId): bool
    {
        return $userId !== null && ($c->remetente_id === $userId || $c->digitador_id === $userId);
    }

    /** Respostas que este servidor vê. */
    public static function respostasVisiveis(Circular $c, int $userId): Collection
    {
        $todas = $c->respostasLista()->with('usuario:id,name')->get();
        if ($c->respostas === 'abertas' || self::ehRemetente($c, $userId)) {
            return $todas;
        }

        return $todas->where('usuario_id', $userId)->values();
    }

    /** Resposta nova: abertas avisam todos (menos quem silenciou); restritas, só o remetente. */
    public static function avisarResposta(Circular $c, User $autor): void
    {
        $avisar = collect([$c->remetente_id]);
        if ($c->respostas === 'abertas') {
            $avisar = $avisar->merge($c->destinatarios()->where('silenciado', false)->whereNull('arquivado_em')->pluck('usuario_id'));
        }
        foreach ($avisar->map(fn ($id) => (int) $id)->unique()->reject(fn ($id) => $id === $autor->id) as $id) {
            Notificacao::create([
                'usuario_id' => $id, 'tipo' => 'circular_resposta', 'titulo' => "{$autor->name} respondeu a circular",
                'mensagem' => "Circular {$c->numero} - {$c->assunto}",
                'referencia_tipo' => 'circular', 'referencia_id' => $c->id, 'lida' => false,
            ]);
        }
    }
}
