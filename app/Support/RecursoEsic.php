<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Notificacao;
use App\Models\Processo\Processo;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Recurso do requerente no e-SIC (Lei nº 12.527/2011, art. 15 e 16): contra a negativa de acesso ou a
 * resposta incompleta, em até 10 dias da ciência da resposta; a autoridade hierarquicamente superior decide
 * em 5 dias. Não provida (ou provida em parte), cabe recurso de 2ª instância, também em 10 dias da decisão.
 * O requerente é avisado de cada etapa (pela solicitação do portal ou por e-mail).
 */
final class RecursoEsic
{
    public const DIAS_INTERPOR = 10;

    public const DIAS_DECIDIR = 5;

    private const FUSO = 'America/Sao_Paulo';

    public const DECISOES = ['provido' => 'Provido', 'parcialmente_provido' => 'Provido em parte', 'nao_provido' => 'Não provido'];

    public const ORIGENS = ['portal' => 'Portal / app', 'balcao' => 'Balcão', 'email' => 'E-mail', 'telefone' => 'Telefone', 'presencial' => 'Presencial'];

    /**
     * Recurso que cabe agora: ['instancia' => 1|2, 'ate' => Carbon] ou ['motivo' => por que não cabe].
     */
    public static function cabivel(Processo $p): array
    {
        if (Ouvidoria::modalidade($p->loadMissing('tipoProcesso')) !== 'esic') {
            return ['motivo' => 'Só pedido de acesso à informação (e-SIC) tem recurso.'];
        }
        $recursos = DB::table('esic_recursos')->where('processo_id', $p->id)->orderBy('instancia')->get();
        $ultimo = $recursos->last();
        if ($ultimo && ! $ultimo->decidido_em) {
            return ['motivo' => "Já há recurso de {$ultimo->instancia}ª instância aguardando decisão."];
        }
        if (! $ultimo) {
            $resposta = $p->decisao_final_em ?? $p->concluido_em;
            if (! in_array($p->status, ['concluido', 'arquivado'], true) || ! $resposta) {
                return ['motivo' => 'O recurso cabe depois da resposta ao pedido.'];
            }
            $ate = Carbon::parse($resposta)->setTimezone(self::FUSO)->addDays(self::DIAS_INTERPOR)->endOfDay();

            return now()->lte($ate) ? ['instancia' => 1, 'ate' => $ate] : ['motivo' => 'Passou o prazo de 10 dias da resposta para recorrer.'];
        }
        if ((int) $ultimo->instancia >= 2) {
            return ['motivo' => 'Os recursos já foram decididos nas duas instâncias.'];
        }
        if ($ultimo->decisao === 'provido') {
            return ['motivo' => 'O recurso de 1ª instância foi provido.'];
        }
        $ate = Carbon::parse($ultimo->decidido_em)->setTimezone(self::FUSO)->addDays(self::DIAS_INTERPOR)->endOfDay();

        return now()->lte($ate) ? ['instancia' => 2, 'ate' => $ate] : ['motivo' => 'Passou o prazo de 10 dias da decisão para recorrer à 2ª instância.'];
    }

    /** Registra o recurso (pelo portal ou pelo servidor que o recebeu) e avisa quem decide. */
    public static function interpor(Processo $p, string $motivo, string $origem, ?int $cidadaoId, ?User $registrador): object
    {
        $cabe = self::cabivel($p);
        if (! isset($cabe['instancia'])) {
            throw new RuntimeException($cabe['motivo']);
        }
        $id = DB::table('esic_recursos')->insertGetId(['processo_id' => $p->id, 'instancia' => $cabe['instancia'], 'motivo' => $motivo,
            'origem' => $origem, 'cidadao_id' => $cidadaoId, 'registrado_por' => $registrador?->id, 'interposto_em' => now(),
            'decidir_ate' => now(self::FUSO)->addDays(self::DIAS_DECIDIR)->endOfDay()->utc(), 'created_at' => now(), 'updated_at' => now()]);
        $r = DB::table('esic_recursos')->find($id);
        self::historico($p, $registrador, 'recurso_esic', ['instancia' => $r->instancia, 'origem' => $origem, 'motivo' => $motivo]);
        $ate = Carbon::parse($r->decidir_ate)->setTimezone('America/Sao_Paulo')->format('d/m/Y');
        Ouvidoria::avisarPortal($p, "Recurso de {$r->instancia}ª instância recebido. A decisão sai até {$ate}.", null);
        foreach (self::autoridades() as $uid) {
            Notificacao::create(['usuario_id' => $uid, 'tipo' => 'processo', 'titulo' => "Recurso e-SIC ({$r->instancia}ª instância): {$p->numero_protocolo}",
                'mensagem' => "Decidir até {$ate}. Motivo: " . mb_substr($motivo, 0, 200), 'referencia_tipo' => 'processo', 'referencia_id' => $p->id, 'lida' => false]);
        }

        return $r;
    }

    public static function decidir(object $r, Processo $p, string $decisao, string $fundamentacao, User $u): void
    {
        if ($r->decidido_em) {
            throw new RuntimeException('Este recurso já foi decidido.');
        }
        DB::table('esic_recursos')->where('id', $r->id)->update(['decisao' => $decisao, 'fundamentacao' => $fundamentacao,
            'decidido_por' => $u->id, 'decidido_em' => now(), 'updated_at' => now()]);
        self::historico($p, $u, 'recurso_esic_decidido', ['instancia' => $r->instancia, 'decisao' => self::DECISOES[$decisao], 'fundamentacao' => $fundamentacao]);
        $texto = "Recurso de {$r->instancia}ª instância: " . mb_strtoupper(self::DECISOES[$decisao]) . ". {$fundamentacao}";
        if ((int) $r->instancia === 1 && $decisao !== 'provido') {
            $texto .= ' Se não concordar, você pode recorrer à 2ª instância em até ' . self::DIAS_INTERPOR . ' dias.';
        }
        Ouvidoria::avisarPortal($p, $texto, null);
        RespostaManifestante::enviarDecisao($p, $texto);
    }

    /** Para a tela do processo. */
    public static function doProcesso(Processo $p, User $u): ?array
    {
        if (Ouvidoria::modalidade($p->loadMissing('tipoProcesso')) !== 'esic') {
            return null;
        }
        $cabe = self::cabivel($p);
        $nome = fn ($id) => $id ? User::whereKey($id)->value('name') : null;

        return [
            'recursos' => DB::table('esic_recursos')->where('processo_id', $p->id)->orderBy('instancia')->get()->map(fn ($r) => [
                'id' => $r->id, 'instancia' => (int) $r->instancia, 'motivo' => $r->motivo, 'origem' => self::ORIGENS[$r->origem] ?? $r->origem,
                'interposto_em' => Carbon::parse($r->interposto_em)->toIso8601String(), 'decidir_ate' => Carbon::parse($r->decidir_ate)->toIso8601String(),
                'atrasado' => ! $r->decidido_em && now()->gt(Carbon::parse($r->decidir_ate)),
                'decisao' => $r->decisao ? self::DECISOES[$r->decisao] : null, 'decisao_codigo' => $r->decisao, 'fundamentacao' => $r->fundamentacao,
                'decidido_em' => $r->decidido_em ? Carbon::parse($r->decidido_em)->toIso8601String() : null,
                'decidido_por' => $nome($r->decidido_por), 'registrado_por' => $nome($r->registrado_por),
            ])->values(),
            'cabe' => isset($cabe['instancia']) ? ['instancia' => $cabe['instancia'], 'ate' => $cabe['ate']->toIso8601String()] : null,
            'motivo' => $cabe['motivo'] ?? null,
            'pode_registrar' => $u->super_admin || $u->can('esic.gerir'),
            'pode_decidir' => $u->super_admin || $u->can('esic.recursos'),
            'decisoes' => self::DECISOES, 'origens' => self::ORIGENS,
        ];
    }

    /** Para o portal (consulta da solicitação): recursos e se cabe recorrer agora. */
    public static function paraPortal(Processo $p): ?array
    {
        if (Ouvidoria::modalidade($p->loadMissing('tipoProcesso')) !== 'esic') {
            return null;
        }
        $cabe = self::cabivel($p);

        return [
            'recursos' => DB::table('esic_recursos')->where('processo_id', $p->id)->orderBy('instancia')->get(['instancia', 'motivo', 'interposto_em', 'decidir_ate', 'decisao', 'fundamentacao', 'decidido_em'])
                ->map(fn ($r) => (array) $r + ['decisao_rotulo' => $r->decisao ? self::DECISOES[$r->decisao] : null])->values(),
            'pode_recorrer' => isset($cabe['instancia']),
            'instancia' => $cabe['instancia'] ?? null,
            'recorrer_ate' => isset($cabe['ate']) ? $cabe['ate']->toIso8601String() : null,
            'motivo' => $cabe['motivo'] ?? null,
        ];
    }

    /** @return list<int> quem decide recursos (permissão esic.recursos) */
    private static function autoridades(): array
    {
        return DB::table('ged_user_roles as ur')->join('ged_role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')
            ->join('ged_permissions as pm', 'pm.id', '=', 'rp.permission_id')->join('users as u', 'u.id', '=', 'ur.user_id')
            ->where('pm.nome', 'esic.recursos')->whereNull('u.suspenso_em')->distinct()->pluck('ur.user_id')->map(fn ($v) => (int) $v)->all();
    }

    private static function historico(Processo $p, ?User $u, string $acao, array $detalhes): void
    {
        DB::table('proc_historico')->insert(['processo_id' => $p->id, 'usuario_id' => $u?->id, 'acao' => $acao,
            'detalhes' => json_encode($detalhes, JSON_UNESCAPED_UNICODE), 'ip' => request()->ip(), 'created_at' => now()]);
    }
}
