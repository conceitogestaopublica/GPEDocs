<?php

declare(strict_types=1);

namespace App\Http\Controllers\Perfil;

use App\Http\Controllers\Controller;
use App\Models\UgOrganograma;
use App\Models\User;
use App\Services\Auth\EntradaService;
use App\Support\HistoricoUsuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Meu perfil › Minha equipe: o responsável por um setor (e pelos setores
 * abaixo dele no organograma) vê os servidores e reativa quem foi bloqueado
 * por inatividade (TR 4.7.29). Suspensão feita pelo administrador continua
 * só com o administrador.
 */
class EquipeController extends Controller
{
    public function index()
    {
        $setores = self::setoresSob(Auth::id());
        abort_if(empty($setores), 403);

        $servidores = User::query()->whereIn('unidade_id', $setores)->where('tipo', 'interno')
            ->with('unidade:id,nome')->orderByRaw('suspenso_em is null')->orderBy('name')
            ->get(['id', 'name', 'email', 'cargo', 'unidade_id', 'suspenso_em', 'suspenso_motivo', 'ultimo_acesso_em', 'foto_path'])
            ->map(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'cargo' => $u->cargo,
                'setor' => $u->unidade?->nome, 'foto_url' => $u->foto_url,
                'ultimo_acesso' => $u->ultimo_acesso_em?->setTimezone('America/Sao_Paulo')->format('d/m/Y'),
                'situacao' => ! $u->suspenso_em ? 'ativo' : (self::porInatividade($u) ? 'inatividade' : 'suspenso'),
                'motivo' => $u->suspenso_motivo,
            ]);

        return Inertia::render('GED/Perfil/Equipe', [
            'servidores' => $servidores,
            'dias'       => (int) config('auth.dias_inatividade', 90),
        ]);
    }

    public function reativar(int $id)
    {
        $u = User::findOrFail($id);
        abort_unless(in_array($u->unidade_id, self::setoresSob(Auth::id()), true), 403);
        abort_unless($u->suspenso_em && self::porInatividade($u), 403, 'Só o administrador reativa esta suspensão.');

        // Conta o reinício do prazo a partir de agora, senão bloquearia de novo na entrada
        DB::table('users')->where('id', $u->id)->update([
            'suspenso_em' => null, 'suspenso_por' => null, 'suspenso_motivo' => null, 'ultimo_acesso_em' => now(),
        ]);
        HistoricoUsuario::registrar($u, 'Situação', 'Bloqueado por inatividade', 'Ativo (reativado pelo superior)');

        return back()->with('success', "Acesso de {$u->name} reativado.");
    }

    /** O usuário responde por algum setor? (mostra a aba) */
    public static function ehResponsavel(?int $userId): bool
    {
        return $userId !== null && UgOrganograma::where('responsavel_id', $userId)->exists();
    }

    /**
     * Setores pelos quais o usuário responde e todos abaixo deles.
     *
     * @return array<int, int>
     */
    public static function setoresSob(?int $userId): array
    {
        $fila = UgOrganograma::where('responsavel_id', $userId)->pluck('id')->all();
        $todos = [];
        while ($fila) {
            $todos = array_merge($todos, $fila);
            $fila = array_values(array_diff(UgOrganograma::whereIn('parent_id', $fila)->pluck('id')->all(), $todos));
        }

        return array_values(array_unique(array_map('intval', $todos)));
    }

    private static function porInatividade(User $u): bool
    {
        return str_starts_with((string) $u->suspenso_motivo, EntradaService::MOTIVO_INATIVIDADE);
    }
}
