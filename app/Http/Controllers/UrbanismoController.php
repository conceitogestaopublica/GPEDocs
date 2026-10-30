<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Geo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use InvalidArgumentException;

/**
 * Consulta prévia de viabilidade (TR 4.50): no GED, zonas (parâmetros e usos), camadas e lotes;
 * para todos (mapa público e API do portal), as zonas, as camadas, os lotes da área visível,
 * a busca por inscrição ou logradouro e a ficha do lote com os índices e usos da zona.
 */
class UrbanismoController extends Controller
{
    public const PARAMETROS = ['to_max' => 'Taxa de ocupação máxima (%)', 'ca_basico' => 'Coeficiente de aproveitamento básico',
        'ca_max' => 'Coeficiente de aproveitamento máximo', 'tp_min' => 'Taxa de permeabilidade mínima (%)', 'gabarito' => 'Gabarito (pavimentos)',
        'altura_max' => 'Altura máxima (m)', 'recuo_frontal' => 'Recuo frontal (m)', 'recuo_lateral' => 'Recuo lateral (m)', 'recuo_fundos' => 'Recuo de fundos (m)',
        'lote_minimo' => 'Lote mínimo (m²)', 'testada_minima' => 'Testada mínima (m)'];

    public const TIPOS_CAMADA = ['viario' => 'Sistema viário', 'bairros' => 'Bairros', 'ambiental' => 'Ambiental (APP/APA)', 'equipamentos' => 'Equipamentos públicos', 'outra' => 'Outra'];

    // ── Gestão no GED ────────────────────────────────────────────────

    public function index()
    {
        $this->exigir();

        return Inertia::render('Configuracao/Urbanismo/Index', [
            'zonas' => DB::table('urb_zonas')->orderBy('sigla')->get()->map(fn ($z) => [
                'id' => $z->id, 'nome' => $z->nome, 'sigla' => $z->sigla, 'cor' => $z->cor, 'ativo' => (bool) $z->ativo, 'observacao' => $z->observacao,
                'tem_geometria' => (bool) $z->geojson, 'parametros' => json_decode((string) $z->parametros, true) ?: [], 'usos' => json_decode((string) $z->usos, true) ?: [],
                'lotes' => DB::table('urb_lotes')->where('zona_id', $z->id)->count()]),
            'camadas' => DB::table('urb_camadas')->orderBy('ordem')->get(['id', 'nome', 'tipo', 'cor', 'visivel_padrao', 'ordem', 'ativo'])
                ->map(fn ($c) => (array) $c + ['feicoes' => count(json_decode((string) DB::table('urb_camadas')->where('id', $c->id)->value('geojson'), true)['features'] ?? [])]),
            'lotes' => ['total' => DB::table('urb_lotes')->count(), 'sem_zona' => DB::table('urb_lotes')->whereNull('zona_id')->count()],
            'config' => DB::table('urb_config')->first(), 'parametros' => self::PARAMETROS, 'tipos_camada' => self::TIPOS_CAMADA,
            'url_publica' => url('/mapa-urbano'),
        ]);
    }

    public function salvarZona(Request $request, ?int $id = null)
    {
        $this->exigir();
        $d = $request->validate(['nome' => ['required', 'string', 'max:150'], 'sigla' => ['required', 'string', 'max:20'], 'cor' => ['required', 'regex:/^#[0-9a-f]{6}$/i'],
            'parametros' => ['nullable', 'array'], 'parametros.*' => ['nullable', 'numeric'], 'usos' => ['nullable', 'array'],
            'usos.permitidos' => ['nullable', 'string', 'max:3000'], 'usos.permissiveis' => ['nullable', 'string', 'max:3000'], 'usos.proibidos' => ['nullable', 'string', 'max:3000'],
            'observacao' => ['nullable', 'string', 'max:3000'], 'ativo' => ['nullable', 'boolean'], 'arquivo' => ['nullable', 'file', 'max:20480'], 'geojson' => ['nullable', 'string']]);
        $geo = $this->lerGeojson($request);
        if ($geo !== null) {
            try {
                Geo::geometria($geo);
            } catch (InvalidArgumentException $e) {
                return back()->withErrors(['arquivo' => 'Geometria da zona: ' . $e->getMessage()]);
            }
        }
        $lista = fn (?string $s) => array_values(array_filter(array_map('trim', preg_split('/[\n;]+/', (string) $s))));
        $dados = ['nome' => $d['nome'], 'sigla' => mb_strtoupper($d['sigla']), 'cor' => $d['cor'], 'observacao' => $d['observacao'] ?? null,
            'parametros' => json_encode(array_map('floatval', array_filter($d['parametros'] ?? [], fn ($v) => $v !== null && $v !== ''))),
            'usos' => json_encode(['permitidos' => $lista($d['usos']['permitidos'] ?? null), 'permissiveis' => $lista($d['usos']['permissiveis'] ?? null),
                'proibidos' => $lista($d['usos']['proibidos'] ?? null)], JSON_UNESCAPED_UNICODE),
            'ativo' => $request->boolean('ativo', true), 'updated_at' => now()] + ($geo !== null ? ['geojson' => $geo] : []);
        $id ? DB::table('urb_zonas')->where('id', $id)->update($dados) : DB::table('urb_zonas')->insert($dados + ['created_at' => now()]);
        if ($geo !== null) {
            self::recalcularZonas();
        }

        return back()->with('success', 'Zona salva.');
    }

    public function excluirZona(int $id)
    {
        $this->exigir();
        DB::table('urb_zonas')->where('id', $id)->delete();

        return back()->with('success', 'Zona excluída; os lotes dela ficaram sem zona.');
    }

    public function salvarCamada(Request $request, ?int $id = null)
    {
        $this->exigir();
        $d = $request->validate(['nome' => ['required', 'string', 'max:150'], 'tipo' => ['required', 'in:' . implode(',', array_keys(self::TIPOS_CAMADA))],
            'cor' => ['required', 'regex:/^#[0-9a-f]{6}$/i'], 'visivel_padrao' => ['nullable', 'boolean'], 'ordem' => ['nullable', 'integer'],
            'ativo' => ['nullable', 'boolean'], 'arquivo' => ['nullable', 'file', 'max:51200'], 'geojson' => ['nullable', 'string']]);
        $geo = $this->lerGeojson($request);
        if (! $id && $geo === null) {
            return back()->withErrors(['arquivo' => 'Envie o arquivo GeoJSON da camada.']);
        }
        if ($geo !== null && ! in_array(json_decode($geo, true)['type'] ?? null, ['FeatureCollection', 'Feature'], true)) {
            return back()->withErrors(['arquivo' => 'A camada precisa ser um GeoJSON (FeatureCollection).']);
        }
        $dados = ['nome' => $d['nome'], 'tipo' => $d['tipo'], 'cor' => $d['cor'], 'visivel_padrao' => $request->boolean('visivel_padrao'),
            'ordem' => (int) ($d['ordem'] ?? 0), 'ativo' => $request->boolean('ativo', true), 'updated_at' => now()] + ($geo !== null ? ['geojson' => $geo] : []);
        $id ? DB::table('urb_camadas')->where('id', $id)->update($dados) : DB::table('urb_camadas')->insert($dados + ['created_at' => now()]);

        return back()->with('success', 'Camada salva.');
    }

    public function excluirCamada(int $id)
    {
        $this->exigir();
        DB::table('urb_camadas')->where('id', $id)->delete();

        return back()->with('success', 'Camada excluída.');
    }

    /**
     * Importa os lotes de um GeoJSON (FeatureCollection de polígonos); "campos" diz de qual
     * propriedade sai cada dado. Substituir apaga os lotes atuais antes.
     */
    public function importarLotes(Request $request)
    {
        $this->exigir();
        $d = $request->validate(['arquivo' => ['required', 'file', 'max:102400'], 'substituir' => ['nullable', 'boolean'],
            'campos' => ['required', 'array'], 'campos.inscricao' => ['required', 'string', 'max:60'], 'campos.*' => ['nullable', 'string', 'max:60']]);
        $fc = json_decode((string) file_get_contents($request->file('arquivo')->getRealPath()), true);
        if (($fc['type'] ?? '') !== 'FeatureCollection') {
            return back()->withErrors(['arquivo' => 'O arquivo precisa ser um GeoJSON FeatureCollection de lotes.']);
        }
        $c = $d['campos'];
        $zonas = self::zonasComGeometria();
        $ok = 0;
        $ignorados = 0;
        DB::transaction(function () use ($fc, $c, $zonas, $request, &$ok, &$ignorados) {
            if ($request->boolean('substituir')) {
                DB::table('urb_lotes')->delete();
            }
            foreach ($fc['features'] ?? [] as $f) {
                $pr = $f['properties'] ?? [];
                $inscricao = trim((string) ($pr[$c['inscricao']] ?? ''));
                try {
                    $geo = Geo::geometria($f['geometry'] ?? []);
                    [$lat, $lng] = Geo::centro($geo);
                } catch (InvalidArgumentException) {
                    $geo = null;
                }
                if ($inscricao === '' || ! $geo) {
                    $ignorados++;
                    continue;
                }
                $pega = fn (string $k) => ! empty($c[$k]) && isset($pr[$c[$k]]) ? mb_substr((string) $pr[$c[$k]], 0, 200) : null;
                DB::table('urb_lotes')->updateOrInsert(['inscricao' => $inscricao], [
                    'logradouro' => $pega('logradouro'), 'numero' => $pega('numero'), 'bairro' => $pega('bairro'), 'quadra' => $pega('quadra'), 'lote' => $pega('lote'),
                    'area' => round(is_numeric($pr[$c['area'] ?? ''] ?? null) ? (float) $pr[$c['area']] : Geo::area($geo), 2),
                    'geojson' => json_encode($geo), 'centro_lat' => $lat, 'centro_lng' => $lng, 'zona_id' => self::zonaDoPonto($zonas, $lat, $lng),
                    'dados' => json_encode($pr, JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'created_at' => now()]);
                $ok++;
            }
        });

        return back()->with('success', "{$ok} lote(s) importado(s)" . ($ignorados ? ", {$ignorados} ignorado(s) sem inscrição ou geometria" : '') . '.');
    }

    public function salvarConfig(Request $request)
    {
        $this->exigir();
        $d = $request->validate(['centro_lat' => ['nullable', 'numeric', 'between:-90,90'], 'centro_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'zoom' => ['nullable', 'integer', 'between:3,19'], 'duvida_url' => ['nullable', 'string', 'max:500'], 'aviso' => ['nullable', 'string', 'max:3000']]);
        $id = DB::table('urb_config')->value('id');
        $id ? DB::table('urb_config')->where('id', $id)->update($d + ['updated_at' => now()]) : DB::table('urb_config')->insert($d + ['created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Configuração do mapa salva.');
    }

    // ── Público (mapa e API do portal) ───────────────────────────────

    public function mapa()
    {
        return Inertia::render('Publico/MapaUrbano', ['base' => '/mapa-urbano', 'orgao' => \App\Models\Ug::withoutGlobalScopes()->where('ativo', true)->orderByRaw('portal_slug is null')->orderBy('id')->value('nome')]);
    }

    public function dados()
    {
        return response()->json(self::dadosMapa());
    }

    public function lotes(Request $request)
    {
        return response()->json(self::lotesDaArea((string) $request->query('bbox')));
    }

    public function lote(Request $request)
    {
        $ficha = self::fichaLote($request->integer('id') ?: null, $request->query('inscricao'));

        return response()->json($ficha ?? ['erro' => 'Lote não encontrado.'], $ficha ? 200 : 404);
    }

    public function busca(Request $request)
    {
        return response()->json(self::buscar((string) $request->query('q')));
    }

    /** @return array<string, mixed> */
    public static function dadosMapa(): array
    {
        $cfg = DB::table('urb_config')->first();
        $zonas = DB::table('urb_zonas')->where('ativo', true)->whereNotNull('geojson')->orderBy('sigla')->get();
        // Centro configurado; sem ele, o meio dos lotes; sem lotes, o Brasil
        $media = $cfg?->centro_lat ? null : DB::table('urb_lotes')->selectRaw('avg(centro_lat) lat, avg(centro_lng) lng')->first();
        $centro = $cfg?->centro_lat ? [$cfg->centro_lat, $cfg->centro_lng] : ($media?->lat ? [$media->lat, $media->lng] : [-15.8, -47.9]);

        return [
            'centro' => array_map('floatval', $centro), 'zoom' => (int) ($cfg->zoom ?? 15), 'aviso' => $cfg->aviso ?? null, 'tem_duvida' => (bool) ($cfg->duvida_url ?? null),
            'parametros' => self::PARAMETROS,
            'zonas' => $zonas->map(fn ($z) => ['id' => $z->id, 'nome' => $z->nome, 'sigla' => $z->sigla, 'cor' => $z->cor,
                'geometria' => Geo::geometria($z->geojson), 'parametros' => json_decode((string) $z->parametros, true) ?: []])->values(),
            'camadas' => DB::table('urb_camadas')->where('ativo', true)->orderBy('ordem')->get()->map(fn ($c) => ['id' => $c->id, 'nome' => $c->nome,
                'tipo' => self::TIPOS_CAMADA[$c->tipo] ?? $c->tipo, 'cor' => $c->cor, 'visivel' => (bool) $c->visivel_padrao, 'geojson' => json_decode((string) $c->geojson, true)])->values(),
        ];
    }

    /** Lotes dentro do retângulo visível "sul,oeste,norte,leste" (até 3.000). */
    public static function lotesDaArea(string $bbox): array
    {
        $b = array_map('floatval', explode(',', $bbox));
        if (count($b) !== 4) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }
        [$s, $w, $n, $e] = $b;
        $lotes = DB::table('urb_lotes as l')->leftJoin('urb_zonas as z', 'z.id', '=', 'l.zona_id')
            ->whereBetween('l.centro_lat', [$s, $n])->whereBetween('l.centro_lng', [$w, $e])->limit(3000)
            ->get(['l.id', 'l.inscricao', 'l.logradouro', 'l.numero', 'l.geojson', 'z.sigla', 'z.cor']);

        return ['type' => 'FeatureCollection', 'features' => $lotes->map(fn ($l) => ['type' => 'Feature', 'geometry' => json_decode($l->geojson, true),
            'properties' => ['id' => $l->id, 'inscricao' => $l->inscricao, 'endereco' => trim("{$l->logradouro} {$l->numero}"), 'zona' => $l->sigla, 'cor' => $l->cor]])->all()];
    }

    /** Ficha do lote: dados, zona, índices e usos, e o link de dúvida (TR 4.50.4, 4.50.8, 4.50.9). */
    public static function fichaLote(?int $id, ?string $inscricao): ?array
    {
        $l = DB::table('urb_lotes')->when($id, fn ($q) => $q->where('id', $id), fn ($q) => $q->where('inscricao', (string) $inscricao))->first();
        if (! $l) {
            return null;
        }
        $z = $l->zona_id ? DB::table('urb_zonas')->where('id', $l->zona_id)->first() : null;
        $cfg = DB::table('urb_config')->first();
        $p = $z ? (json_decode((string) $z->parametros, true) ?: []) : [];
        $area = (float) $l->area;

        return [
            'lote' => ['id' => $l->id, 'inscricao' => $l->inscricao, 'logradouro' => $l->logradouro, 'numero' => $l->numero, 'bairro' => $l->bairro,
                'quadra' => $l->quadra, 'lote' => $l->lote, 'area' => $area, 'centro' => [(float) $l->centro_lat, (float) $l->centro_lng]],
            'zona' => $z ? ['nome' => $z->nome, 'sigla' => $z->sigla, 'cor' => $z->cor, 'observacao' => $z->observacao,
                'parametros' => collect(self::PARAMETROS)->filter(fn ($r, $k) => isset($p[$k]))->map(fn ($r, $k) => ['rotulo' => $r, 'valor' => $p[$k]])->values(),
                'usos' => json_decode((string) $z->usos, true) ?: []] : null,
            // O que cabe no lote pelos índices da zona
            'potencial' => $z && $area > 0 ? array_filter([
                'area_ocupavel' => isset($p['to_max']) ? round($area * $p['to_max'] / 100, 2) : null,
                'area_construivel_basica' => isset($p['ca_basico']) ? round($area * $p['ca_basico'], 2) : null,
                'area_construivel_maxima' => isset($p['ca_max']) ? round($area * $p['ca_max'], 2) : null,
                'area_permeavel_minima' => isset($p['tp_min']) ? round($area * $p['tp_min'] / 100, 2) : null,
            ], fn ($v) => $v !== null) : [],
            'duvida_url' => $cfg?->duvida_url ? str_replace('{inscricao}', rawurlencode($l->inscricao), $cfg->duvida_url) : null,
        ];
    }

    public static function buscar(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        return DB::table('urb_lotes')->where(fn ($w) => $w->where('inscricao', 'ilike', "%{$q}%")->orWhereRaw("coalesce(logradouro,'') || ' ' || coalesce(numero,'') ilike ?", ["%{$q}%"]))
            ->orderBy('inscricao')->limit(15)->get(['id', 'inscricao', 'logradouro', 'numero', 'bairro', 'centro_lat', 'centro_lng'])
            ->map(fn ($l) => ['id' => $l->id, 'inscricao' => $l->inscricao, 'endereco' => trim("{$l->logradouro} {$l->numero}" . ($l->bairro ? " — {$l->bairro}" : '')),
                'centro' => [(float) $l->centro_lat, (float) $l->centro_lng]])->all();
    }

    /** Zona de cada lote pelo ponto central (depois de mudar a geometria de uma zona). */
    public static function recalcularZonas(): int
    {
        $zonas = self::zonasComGeometria();
        $n = 0;
        foreach (DB::table('urb_lotes')->get(['id', 'centro_lat', 'centro_lng']) as $l) {
            DB::table('urb_lotes')->where('id', $l->id)->update(['zona_id' => self::zonaDoPonto($zonas, (float) $l->centro_lat, (float) $l->centro_lng)]);
            $n++;
        }

        return $n;
    }

    private static function zonasComGeometria(): array
    {
        return DB::table('urb_zonas')->where('ativo', true)->whereNotNull('geojson')->get(['id', 'geojson'])
            ->map(fn ($z) => ['id' => $z->id, 'geo' => Geo::geometria($z->geojson)])->all();
    }

    private static function zonaDoPonto(array $zonas, float $lat, float $lng): ?int
    {
        foreach ($zonas as $z) {
            if (Geo::contem($z['geo'], $lat, $lng)) {
                return $z['id'];
            }
        }

        return null;
    }

    private function lerGeojson(Request $request): ?string
    {
        $txt = $request->file('arquivo') ? (string) file_get_contents($request->file('arquivo')->getRealPath()) : (string) $request->input('geojson');
        if (trim($txt) === '') {
            return null;
        }
        json_decode($txt);

        return json_last_error() === JSON_ERROR_NONE ? $txt : throw \Illuminate\Validation\ValidationException::withMessages(['arquivo' => 'O arquivo não é um JSON válido.']);
    }

    private function exigir(): void
    {
        abort_unless(Auth::user()->super_admin || Auth::user()->can('urbanismo.gerir'), 403);
    }
}
