<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Histórico do cadastro do servidor (TR 4.7.22). Retrato legível do cadastro
 * antes e depois de cada alteração; grava só os campos que mudaram.
 */
final class HistoricoUsuario
{
    private const SEXO = ['F' => 'Feminino', 'M' => 'Masculino', 'O' => 'Outro'];

    /** @return array<string, string|null> rótulo => valor legível */
    public static function retrato(User $u): array
    {
        $u->loadMissing(['unidade:id,nome', 'setoresSecundarios:ug_organograma.id,nome', 'ugs:ugs.id,codigo', 'roles:id,nome']);

        return [
            'Nome'              => $u->name,
            'E-mail'            => $u->email,
            'E-mail secundário' => $u->email_secundario,
            'CPF'               => DocumentoPessoa::mascararCpf($u->cpf),
            'Celular'           => $u->telefone,
            'Matrícula'         => $u->matricula,
            'Cargo / função'    => $u->cargo,
            'Sexo'              => self::SEXO[$u->sexo] ?? null,
            'Nascimento'        => $u->data_nascimento?->format('d/m/Y'),
            'Tipo'              => $u->tipo === 'externo' ? 'Externo' : 'Interno',
            'Setor'             => $u->unidade?->nome,
            'Setores secundários' => $u->setoresSecundarios->pluck('nome')->sort()->implode(', ') ?: null,
            'UGs'               => $u->ugs->pluck('codigo')->sort()->implode(', ') ?: null,
            'Perfis'            => $u->roles->pluck('nome')->sort()->implode(', ') ?: null,
            'Super admin'       => $u->super_admin ? 'Sim' : 'Não',
            'Acesso a toda a UG' => $u->acesso_geral_ug ? 'Sim' : 'Não',
        ];
    }

    /** Compara dois retratos e grava o que mudou. */
    public static function comparar(User $u, array $antes, array $depois): void
    {
        foreach ($depois as $campo => $valor) {
            if (($antes[$campo] ?? null) !== $valor) {
                self::registrar($u, $campo, $antes[$campo] ?? null, $valor);
            }
        }
    }

    /** Registro avulso (senha alterada, suspensão, foto…). Falhar aqui não impede a alteração. */
    public static function registrar(User $u, string $campo, ?string $antes, ?string $depois): void
    {
        try {
            DB::table('ged_usuario_historico')->insert([
                'user_id'      => $u->id,
                'campo'        => mb_substr($campo, 0, 60),
                'antes'        => $antes,
                'depois'       => $depois,
                'alterado_por' => auth()->id(),
                'ip'           => request()->ip(),
                'created_at'   => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Histórico do usuário não gravado', ['user' => $u->id, 'erro' => $e->getMessage()]);
        }
    }

    /** @return array<int, array<string, mixed>> mais recentes primeiro */
    public static function de(User $u, int $limite = 200): array
    {
        return DB::table('ged_usuario_historico as h')
            ->leftJoin('users as p', 'p.id', '=', 'h.alterado_por')
            ->where('h.user_id', $u->id)
            ->orderByDesc('h.id')->limit($limite)
            ->get(['h.campo', 'h.antes', 'h.depois', 'h.ip', 'h.created_at', 'p.name as por'])
            ->map(fn ($h) => [
                'campo'  => $h->campo,
                'antes'  => $h->antes,
                'depois' => $h->depois,
                'por'    => $h->por ?? 'Sistema',
                'ip'     => $h->ip,
                'quando' => \Illuminate\Support\Carbon::parse($h->created_at, 'UTC')->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            ])->all();
    }
}
