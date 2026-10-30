<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Expediente do município (TR 4.13.9–4.13.11): dias úteis, horário de funcionamento,
 * feriados e pontos facultativos (tabelas expediente e expediente_feriados).
 *
 * Prazo em dias úteis: vence no fim do expediente do N-ésimo dia útil seguinte. O que
 * chega depois do fim do expediente (ou em dia sem expediente) conta a partir do
 * próximo dia útil. As horas do expediente são de Brasília; o banco fica em UTC.
 */
final class Expediente
{
    public const FUSO = 'America/Sao_Paulo';

    private static ?array $config = null;
    /** @var array<string, string>|null data (Y-m-d) => tipo */
    private static ?array $folgas = null;

    /** @return array{dias_semana: list<int>, hora_inicio: string, hora_fim: string} */
    public static function config(): array
    {
        if (self::$config === null) {
            $c = DB::table('expediente')->orderBy('id')->first();
            self::$config = [
                'dias_semana' => $c ? array_map('intval', json_decode($c->dias_semana, true) ?: []) : [1, 2, 3, 4, 5],
                'hora_inicio' => substr((string) ($c->hora_inicio ?? '08:00'), 0, 5),
                'hora_fim'    => substr((string) ($c->hora_fim ?? '17:00'), 0, 5),
            ];
        }

        return self::$config;
    }

    public static function ehDiaUtil(CarbonInterface $dia): bool
    {
        self::$folgas ??= DB::table('expediente_feriados')->pluck('tipo', 'data')
            ->mapWithKeys(fn ($tipo, $data) => [substr((string) $data, 0, 10) => $tipo])->all();
        $local = CarbonImmutable::instance($dia)->setTimezone(self::FUSO);

        return in_array($local->dayOfWeekIso, self::config()['dias_semana'], true)
            && ! isset(self::$folgas[$local->format('Y-m-d')]);
    }

    /** Fim do expediente do N-ésimo dia útil a contar de $inicio (em UTC). */
    public static function somarDiasUteis(CarbonInterface $inicio, int $dias): CarbonImmutable
    {
        $cfg = self::config();
        [$h, $m] = array_map('intval', explode(':', $cfg['hora_fim']));
        $dia = CarbonImmutable::instance($inicio)->setTimezone(self::FUSO);
        $contados = 0;
        $guarda = 0;
        while ($contados < max(1, $dias) && $guarda++ < 3700) {
            $dia = $dia->addDay()->startOfDay();
            if (self::ehDiaUtil($dia)) {
                $contados++;
            }
        }

        return $dia->setTime($h, $m)->setTimezone('UTC');
    }

    /**
     * Prazo de uma etapa do assunto: em dias úteis (horas / 24, arredondado para cima)
     * quando o assunto pede, senão em horas corridas como antes.
     */
    public static function prazo(?\App\Models\Processo\TipoProcesso $tipo, ?int $horas, ?CarbonInterface $inicio = null): ?CarbonInterface
    {
        if (! $horas) {
            return null;
        }
        $inicio ??= now();

        return $tipo?->prazo_dias_uteis
            ? self::somarDiasUteis($inicio, (int) ceil($horas / 24))
            : $inicio->copy()->addHours($horas);
    }

    /** Feriados nacionais do ano (fixos e móveis pela Páscoa). @return list<array{data: string, nome: string, tipo: string}> */
    public static function feriadosNacionais(int $ano): array
    {
        // Páscoa pelo algoritmo de Meeus/Jones/Butcher (não depende da extensão calendar)
        $a = $ano % 19; $b = intdiv($ano, 100); $c = $ano % 100; $d = intdiv($b, 4); $e = $b % 4;
        $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3); $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7; $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $pascoa = CarbonImmutable::create($ano, intdiv($h + $l - 7 * $m + 114, 31), (($h + $l - 7 * $m + 114) % 31) + 1);

        $lista = [
            ["{$ano}-01-01", 'Confraternização Universal', 'feriado'],
            [$pascoa->subDays(48)->format('Y-m-d'), 'Carnaval (segunda-feira)', 'ponto_facultativo'],
            [$pascoa->subDays(47)->format('Y-m-d'), 'Carnaval', 'ponto_facultativo'],
            [$pascoa->subDays(2)->format('Y-m-d'), 'Sexta-feira Santa', 'feriado'],
            ["{$ano}-04-21", 'Tiradentes', 'feriado'],
            ["{$ano}-05-01", 'Dia do Trabalho', 'feriado'],
            [$pascoa->addDays(60)->format('Y-m-d'), 'Corpus Christi', 'ponto_facultativo'],
            ["{$ano}-09-07", 'Independência do Brasil', 'feriado'],
            ["{$ano}-10-12", 'Nossa Senhora Aparecida', 'feriado'],
            ["{$ano}-11-02", 'Finados', 'feriado'],
            ["{$ano}-11-15", 'Proclamação da República', 'feriado'],
            ["{$ano}-11-20", 'Dia Nacional de Zumbi e da Consciência Negra', 'feriado'],
            ["{$ano}-12-25", 'Natal', 'feriado'],
        ];

        return array_map(fn ($f) => ['data' => $f[0], 'nome' => $f[1], 'tipo' => $f[2]], $lista);
    }

    /** Depois de mudar a configuração na mesma requisição. */
    public static function esquecer(): void
    {
        self::$config = null;
        self::$folgas = null;
    }
}
