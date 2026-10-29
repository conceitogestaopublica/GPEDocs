<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\UgOrganograma;
use App\Models\User;

/**
 * Em que setores o servidor atende (TR 4.9.1–4.9.3): o principal
 * (users.unidade_id), os secundários (user_setores) e, para quem é responsável
 * por um setor, toda a hierarquia abaixo dele (subsetores sem limite de níveis).
 * É a regra da caixa do setor e de quem pode agir numa etapa endereçada a setor.
 */
final class Setores
{
    /** @var array<int, list<int>> por usuário, dentro da requisição */
    private static array $cache = [];

    /** @return list<int> */
    public static function doUsuario(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return self::$cache[$user->id] ??= array_values(array_unique(array_map('intval', array_filter([
            $user->unidade_id,
            ...$user->setoresSecundarios()->pluck('ug_organograma.id')->all(),
            ...self::sob($user->id),
        ]))));
    }

    public static function atende(?User $user, ?int $unidadeId): bool
    {
        return $unidadeId !== null && in_array($unidadeId, self::doUsuario($user), true);
    }

    /** Quem atende o setor (principal ou secundário) — para avisar do que chega nele. @return list<int> */
    public static function servidores(int $unidadeId): array
    {
        return User::query()
            ->where('unidade_id', $unidadeId)
            ->orWhereIn('id', fn ($q) => $q->select('user_id')->from('user_setores')->where('unidade_id', $unidadeId))
            ->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** Setores de que o usuário é responsável e todos os subsetores deles. @return list<int> */
    public static function sob(?int $userId): array
    {
        if (! $userId) {
            return [];
        }
        $fila = UgOrganograma::where('responsavel_id', $userId)->pluck('id')->all();
        $todos = [];
        while ($fila) {
            $todos = array_merge($todos, $fila);
            $fila = array_values(array_diff(UgOrganograma::whereIn('parent_id', $fila)->pluck('id')->all(), $todos));
        }

        return array_values(array_unique(array_map('intval', $todos)));
    }

    /** Depois de mudar setores ou responsáveis na mesma requisição (testes, cadastro). */
    public static function esquecer(): void
    {
        self::$cache = [];
    }
}
