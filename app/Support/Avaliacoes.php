<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Processo\Processo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Avaliação por estrelas depois da conclusão (TR 4.32.25–27, 4.33.24) e localização da
 * demanda (4.32.21, 4.33.9). Uma avaliação por rodada: reabrir o processo abre a próxima.
 */
final class Avaliacoes
{
    /** Conclusões que o cidadão avalia (cancelado não é atendimento). */
    private const AVALIAVEIS = ['concluido', 'arquivado'];

    public static function pode(Processo $p): bool
    {
        return in_array($p->status, self::AVALIAVEIS, true)
            && ($p->tipoProcesso?->avaliacao ?? true)
            && ! DB::table('proc_avaliacoes')->where('processo_id', $p->id)->where('rodada', (int) $p->reaberturas)->exists();
    }

    public static function registrar(Processo $p, int $nota, ?string $comentario, string $origem, ?int $cidadaoId = null, ?int $userId = null): void
    {
        if ($nota < 1 || $nota > 5) {
            throw ValidationException::withMessages(['nota' => 'Escolha de 1 a 5 estrelas.']);
        }
        if (! self::pode($p)) {
            throw ValidationException::withMessages(['nota' => 'Este atendimento já foi avaliado ou ainda não terminou.']);
        }
        DB::table('proc_avaliacoes')->insert([
            'processo_id' => $p->id, 'rodada' => (int) $p->reaberturas, 'nota' => $nota,
            'comentario' => trim((string) $comentario) ?: null, 'origem' => $origem,
            'cidadao_id' => $cidadaoId, 'registrado_por' => $userId, 'created_at' => now(),
        ]);
        DB::table('proc_historico')->insert([
            'processo_id' => $p->id, 'usuario_id' => $userId, 'acao' => 'avaliacao',
            'detalhes' => json_encode(['nota' => $nota, 'origem' => $origem, 'rodada' => (int) $p->reaberturas], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }

    /** @return list<array> avaliações do processo, da mais nova para a mais antiga */
    public static function doProcesso(int $processoId): array
    {
        return DB::table('proc_avaliacoes as a')->leftJoin('users as u', 'u.id', '=', 'a.registrado_por')
            ->where('a.processo_id', $processoId)->orderByDesc('a.rodada')
            ->get(['a.rodada', 'a.nota', 'a.comentario', 'a.origem', 'a.created_at', 'u.name as registrado_por'])
            ->map(fn ($a) => (array) $a)->all();
    }

    /**
     * Indicadores (TR 4.32.26): média geral e por assunto, no período.
     *
     * @return array{media: ?float, total: int, por_nota: array<int,int>, por_assunto: list<array>}
     */
    public static function indicadores(?string $de = null, ?string $ate = null): array
    {
        $base = fn () => DB::table('proc_avaliacoes as a')->join('proc_processos as p', 'p.id', '=', 'a.processo_id')
            ->whereNull('p.deleted_at')
            ->when($de, fn ($q) => $q->where('a.created_at', '>=', $de))
            ->when($ate, fn ($q) => $q->where('a.created_at', '<=', $ate . ' 23:59:59'))
            ->when(session('ug_id'), fn ($q, $ug) => $q->where('p.ug_id', $ug));

        $geral = $base()->selectRaw('avg(a.nota) as media, count(*) as total')->first();
        $porNota = $base()->groupBy('a.nota')->selectRaw('a.nota, count(*) as n')->pluck('n', 'nota')->all();

        return [
            'media'       => $geral->total ? round((float) $geral->media, 2) : null,
            'total'       => (int) $geral->total,
            'por_nota'    => array_map(fn ($n) => (int) ($porNota[$n] ?? 0), [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5]),
            'por_assunto' => $base()->join('proc_tipos_processo as t', 't.id', '=', 'p.tipo_processo_id')
                ->groupBy('t.nome')->selectRaw('t.nome as assunto, round(avg(a.nota)::numeric, 2) as media, count(*) as total')
                ->orderByDesc('total')->limit(15)->get()->map(fn ($r) => ['assunto' => $r->assunto, 'media' => (float) $r->media, 'total' => (int) $r->total])->all(),
        ];
    }

    /**
     * Confere a localização mandada para o processo conforme o assunto pede.
     *
     * @return array{latitude: ?float, longitude: ?float, local_endereco: ?string}
     */
    public static function localizacao(?array $l, string $exigencia): array
    {
        $lat = isset($l['latitude']) && $l['latitude'] !== '' ? (float) $l['latitude'] : null;
        $lng = isset($l['longitude']) && $l['longitude'] !== '' ? (float) $l['longitude'] : null;
        $end = trim((string) ($l['endereco'] ?? '')) ?: null;

        if (($lat === null) !== ($lng === null) || ($lat !== null && (abs($lat) > 90 || abs($lng) > 180))) {
            throw ValidationException::withMessages(['localizacao' => 'Coordenada inválida. Marque o ponto no mapa de novo.']);
        }
        if ($exigencia === 'obrigatoria' && $lat === null) {
            throw ValidationException::withMessages(['localizacao' => 'Marque no mapa onde fica a demanda.']);
        }
        if ($exigencia === 'nao') {
            return ['latitude' => null, 'longitude' => null, 'local_endereco' => null];
        }

        return ['latitude' => $lat, 'longitude' => $lng, 'local_endereco' => $end ? mb_substr($end, 0, 500) : null];
    }
}
