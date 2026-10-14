<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Grau de sigilo do documento (ged_documentos.classificacao):
 *
 *   público / interno  quem tem documento.visualizar na UG (a rota já confere)
 *   confidencial       autor, signatários, super admin e quem tem documento.sigiloso
 *   restrito           autor, signatários e super admin
 *
 * Escopo global: o documento que o usuário não alcança some das listas e da busca e
 * responde 404 ao ser aberto. Sem usuário autenticado (API de integração, console,
 * jobs, verificação pública) não filtra — cada um desses caminhos tem o próprio controle.
 * Para liberar uma consulta: Documento::withoutGlobalScope('sigilo').
 */
trait RespeitaSigilo
{
    public static function bootRespeitaSigilo(): void
    {
        static::addGlobalScope('sigilo', function (Builder $query) {
            $user = Auth::user();
            if ($user instanceof User) {
                static::restringirPorSigilo($query->getQuery(), $user, $query->getModel()->getTable());
            }
        });
    }

    /**
     * Aplica a regra a uma consulta Eloquent ou DB::table sobre ged_documentos.
     * Use também nas consultas por DB::table, que não passam pelo escopo global.
     *
     * @param \Illuminate\Database\Query\Builder $query
     */
    public static function restringirPorSigilo($query, User $user, string $tabela = 'ged_documentos'): void
    {
        if ($user->super_admin) {
            return;
        }

        $query->where(function ($q) use ($user, $tabela) {
            $q->whereNull("{$tabela}.classificacao")
              ->orWhereIn("{$tabela}.classificacao", ['publico', 'interno'])
              ->orWhere("{$tabela}.autor_id", $user->id)
              ->orWhereExists(fn ($s) => $s->select(DB::raw(1))
                  ->from('ged_assinaturas')
                  ->whereColumn('ged_assinaturas.documento_id', "{$tabela}.id")
                  ->where('ged_assinaturas.signatario_id', $user->id));

            if ($user->temPermissao('documento.sigiloso')) {
                $q->orWhere("{$tabela}.classificacao", 'confidencial');
            }
        });
    }

    public function ehSigiloso(): bool
    {
        return in_array($this->classificacao, ['confidencial', 'restrito'], true);
    }
}
