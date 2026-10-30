<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Rotina favorita de um usuário numa UG (Ctrl+K → estrela, e a estrela do cabeçalho).
 * Não confundir com os documentos favoritos (ged_favoritos).
 *
 * O contexto é sempre o par usuário × UG da sessão; sem UG na sessão (super admin sem UG),
 * vale SEM_UG.
 */
class UsuarioFavorito extends Model
{
    public const SEM_UG = 0;

    protected $table = 'usuario_favorito';

    protected $fillable = ['user_id', 'ug_id', 'href', 'label', 'icon', 'ordem'];

    public function scopeDoContexto(Builder $query, int $userId, ?int $ugId): Builder
    {
        return $query->where('user_id', $userId)->where('ug_id', $ugId ?? self::SEM_UG);
    }

    /** Lista para a tela (prop `favoritos`): ordem manual, depois alfabética. */
    public static function listar(int $userId, ?int $ugId): array
    {
        return static::doContexto($userId, $ugId)
            ->orderBy('ordem')->orderBy('label')
            ->get(['href', 'label', 'icon'])
            ->toArray();
    }

    /**
     * Favorita (ou atualiza rótulo/ícone). Upsert atômico no banco: dois cliques seguidos não
     * batem na chave única, e o created_at do favorito original é preservado.
     */
    public static function favoritar(int $userId, ?int $ugId, string $href, string $label, ?string $icon): void
    {
        $agora = now();
        static::query()->upsert(
            [[
                'user_id' => $userId, 'ug_id' => $ugId ?? self::SEM_UG, 'href' => $href,
                'label' => $label, 'icon' => $icon, 'created_at' => $agora, 'updated_at' => $agora,
            ]],
            ['user_id', 'ug_id', 'href'],
            ['label', 'icon', 'updated_at'],
        );
    }

    public static function desfavoritar(int $userId, ?int $ugId, string $href): void
    {
        static::doContexto($userId, $ugId)->where('href', $href)->delete();
    }
}
