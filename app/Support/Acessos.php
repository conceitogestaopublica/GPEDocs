<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Acessos a processo, memorando, ofício e circular (TR 4.14.1–4.14.4). Uma linha por
 * pessoa e documento (proc_acessos): primeiro e último acesso, quantas vezes, de onde.
 * Falhar aqui nunca impede abrir o documento.
 */
final class Acessos
{
    public const TIPOS = ['processo', 'memorando', 'oficio', 'circular'];

    public static function registrar(string $tipo, int $itemId, ?User $usuario, ?string $externoNome = null): void
    {
        if (! in_array($tipo, self::TIPOS, true) || (! $usuario && ! $externoNome)) {
            return;
        }

        try {
            $chave = ['tipo' => $tipo, 'item_id' => $itemId];
            $chave += $usuario ? ['usuario_id' => $usuario->id] : ['usuario_id' => null, 'externo_nome' => mb_substr($externoNome, 0, 255)];
            $agora = now();
            $origem = ['ip' => request()->ip(), 'user_agent' => mb_substr((string) request()->userAgent(), 0, 500)];

            $atualizou = DB::table('proc_acessos')->where($chave)
                ->update(['ultimo_em' => $agora, 'vezes' => DB::raw('vezes + 1')] + $origem);
            if (! $atualizou) {
                DB::table('proc_acessos')->insert($chave + ['primeiro_em' => $agora, 'ultimo_em' => $agora, 'vezes' => 1] + $origem);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Lista "Quem acessou", do acesso mais recente para o mais antigo. */
    public static function de(string $tipo, int $itemId): array
    {
        return DB::table('proc_acessos as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.usuario_id')
            ->leftJoin('ug_organograma as o', 'o.id', '=', 'u.unidade_id')
            ->where('a.tipo', $tipo)->where('a.item_id', $itemId)
            ->orderByDesc('a.ultimo_em')
            ->get(['a.usuario_id', 'u.name', 'o.nome as setor', 'a.externo_nome', 'a.primeiro_em', 'a.ultimo_em', 'a.vezes', 'a.user_agent'])
            ->map(fn ($a) => [
                'nome'        => $a->name ?? $a->externo_nome,
                'setor'       => $a->setor,
                'externo'     => $a->usuario_id === null,
                'primeiro_em' => \Illuminate\Support\Carbon::parse($a->primeiro_em)->toIso8601String(),
                'ultimo_em'   => \Illuminate\Support\Carbon::parse($a->ultimo_em)->toIso8601String(),
                'vezes'       => (int) $a->vezes,
                // Computador ou celular e o aparelho (TR 4.11.14)
                'dispositivo' => $a->user_agent ? Dispositivo::descrever($a->user_agent) : null,
            ])->all();
    }
}
