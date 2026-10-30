<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Processo\Processo;
use App\Models\Processo\ProcessoAnexo;
use App\Support\Relatorio;
use App\Support\RequerimentoServidor;
use App\Tenant\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * App offline da fiscalização (TR 4.52). O GED configura os formulários de checklist; o fiscal usa a
 * tela /fiscal no celular (instalável, funciona sem internet e guarda tudo no aparelho) e sincroniza
 * quando quiser. Ao sincronizar uma vistoria ligada a um processo em trâmite, ela vira uma diligência
 * do processo com as fotos e o relatório em PDF juntados como anexos (4.52.17).
 */
class AppFiscalizacaoController extends Controller
{
    public const TIPOS_CAMPO = ['texto' => 'Texto', 'texto_longo' => 'Texto longo', 'numero' => 'Número', 'data' => 'Data',
        'lista' => 'Lista de opções', 'sim_nao' => 'Sim / não'];

    public const RESPOSTAS = ['atende' => 'Atende', 'nao_atende' => 'Não atende', 'nao_se_aplica' => 'Não se aplica'];

    // ---- Configuração (GED) ----

    public function index()
    {
        $this->exigir('fiscalizacao.formularios');
        $qtd = DB::table('fis_vistorias')->selectRaw('formulario_id, count(*) as n')->groupBy('formulario_id')->pluck('n', 'formulario_id');

        return Inertia::render('Configuracao/Fiscalizacao/Index', [
            'formularios' => DB::table('fis_formularios')->orderBy('nome')->get()->map(fn ($f) => [
                'id' => $f->id, 'nome' => $f->nome, 'descricao' => $f->descricao, 'ativo' => (bool) $f->ativo,
                'objetos' => json_decode((string) $f->objetos, true) ?: [], 'campos' => json_decode((string) $f->campos, true) ?: [],
                'itens' => json_decode((string) $f->itens, true) ?: [], 'vistorias' => (int) ($qtd[$f->id] ?? 0)]),
            'vistorias' => $this->listaVistorias(DB::table('fis_vistorias as v'))->limit(300)->get()->map(fn ($v) => $this->resumo($v)),
            'tiposCampo' => self::TIPOS_CAMPO,
        ]);
    }

    public function salvarFormulario(Request $request, ?int $id = null)
    {
        $this->exigir('fiscalizacao.formularios');
        $d = $request->validate([
            'nome' => ['required', 'string', 'max:150'], 'descricao' => ['nullable', 'string', 'max:2000'], 'ativo' => ['boolean'],
            'objetos' => ['array', 'max:200'], 'objetos.*' => ['string', 'max:250'],
            'campos' => ['array', 'max:50'], 'campos.*.rotulo' => ['required', 'string', 'max:150'],
            'campos.*.tipo' => ['required', 'in:' . implode(',', array_keys(self::TIPOS_CAMPO))],
            'campos.*.opcoes' => ['array', 'max:50'], 'campos.*.opcoes.*' => ['string', 'max:150'], 'campos.*.obrigatorio' => ['boolean'],
            'itens' => ['required', 'array', 'min:1', 'max:200'], 'itens.*.texto' => ['required', 'string', 'max:500'], 'itens.*.grupo' => ['nullable', 'string', 'max:150'],
        ]);
        // chave estável por campo/item: a vistoria antiga continua legível depois que o formulário muda
        $chave = fn (array $x) => ! empty($x['chave']) && preg_match('/^[a-z0-9]{8}$/', (string) $x['chave']) ? $x['chave'] : Str::lower(Str::random(8));
        $campos = collect($request->input('campos', []))->map(fn ($c) => ['chave' => $chave($c), 'rotulo' => trim($c['rotulo']), 'tipo' => $c['tipo'],
            'opcoes' => $c['tipo'] === 'lista' ? array_values(array_filter(array_map('trim', (array) ($c['opcoes'] ?? [])))) : [],
            'obrigatorio' => (bool) ($c['obrigatorio'] ?? false)])->values()->all();
        $itens = collect($request->input('itens', []))->map(fn ($i) => ['chave' => $chave($i), 'grupo' => trim((string) ($i['grupo'] ?? '')) ?: null,
            'texto' => trim($i['texto'])])->values()->all();
        $linha = ['nome' => $d['nome'], 'descricao' => $d['descricao'] ?? null, 'ativo' => $request->boolean('ativo', true),
            'objetos' => json_encode(array_values(array_filter(array_map('trim', $d['objetos'] ?? []))), JSON_UNESCAPED_UNICODE),
            'campos' => json_encode($campos, JSON_UNESCAPED_UNICODE), 'itens' => json_encode($itens, JSON_UNESCAPED_UNICODE), 'updated_at' => now()];
        $id ? DB::table('fis_formularios')->where('id', $id)->update($linha) : DB::table('fis_formularios')->insert($linha + ['created_at' => now()]);

        return back()->with('success', 'Formulário salvo. O celular do fiscal recebe a versão nova na próxima vez que estiver com internet.');
    }

    public function excluirFormulario(int $id)
    {
        $this->exigir('fiscalizacao.formularios');
        if (DB::table('fis_vistorias')->where('formulario_id', $id)->exists()) {
            DB::table('fis_formularios')->where('id', $id)->update(['ativo' => false, 'updated_at' => now()]);

            return back()->with('success', 'O formulário tem vistorias: foi desativado em vez de excluído.');
        }
        DB::table('fis_formularios')->where('id', $id)->delete();

        return back()->with('success', 'Formulário excluído.');
    }

    /** Relatório da vistoria em PDF (também juntado ao processo quando vinculada). */
    public function pdf(int $id)
    {
        $v = DB::table('fis_vistorias')->find($id);
        abort_unless($v, 404);
        abort_unless(Auth::user()->super_admin || Auth::id() === (int) $v->fiscal_id || Auth::user()->can('fiscalizacao.formularios'), 403);

        return $this->gerarPdf($v)->stream("vistoria-{$v->id}.pdf");
    }

    public function foto(int $id, int $indice)
    {
        $v = DB::table('fis_vistorias')->find($id);
        abort_unless($v, 404);
        abort_unless(Auth::user()->super_admin || Auth::id() === (int) $v->fiscal_id || Auth::user()->can('fiscalizacao.formularios'), 403);
        $f = (json_decode((string) $v->fotos, true) ?: [])[$indice] ?? null;
        abort_unless($f && Storage::disk('documentos')->exists($f['path']), 404);

        return response(Storage::disk('documentos')->get($f['path']), 200, ['Content-Type' => $f['mime'] ?? 'image/jpeg']);
    }

    // ---- App do fiscal (/fiscal) ----

    public function app()
    {
        $this->exigir('fiscalizacao.campo');

        return response()->view('fiscal', ['orgao' => Auth::user()->ugAtual()?->nome])->header('Cache-Control', 'no-cache');
    }

    public function manifesto()
    {
        return response()->json([
            'name' => 'Fiscalização', 'short_name' => 'Fiscalização', 'lang' => 'pt-BR', 'start_url' => '/fiscal', 'scope' => '/fiscal',
            'display' => 'standalone', 'background_color' => '#f8fafc', 'theme_color' => '#0e7490',
            'icons' => [['src' => '/fiscal/icone', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any maskable']],
        ])->header('Content-Type', 'application/manifest+json');
    }

    public function icone()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><rect width="512" height="512" rx="96" fill="#0e7490"/>'
            . '<path d="M160 120h150l62 62v210H160z" fill="#fff"/><path d="M200 230l30 30 60-70M200 320h120" stroke="#0e7490" stroke-width="26" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>';

        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400']);
    }

    /** Service worker: guarda a tela e os arquivos dela para abrir sem internet. */
    public function sw()
    {
        return response()->view('fiscal-sw')->header('Content-Type', 'application/javascript')
            ->header('Service-Worker-Allowed', '/fiscal')->header('Cache-Control', 'no-cache');
    }

    /** O que o celular guarda para trabalhar offline: formulários, processos em trâmite e resultados. */
    public function sessao()
    {
        $this->exigir('fiscalizacao.campo');
        $u = Auth::user();
        $processos = Processo::with('tipoProcesso:id,nome,modalidade')
            ->whereHas('tipoProcesso', fn ($q) => $q->where('modalidade', 'fiscalizacao'))
            ->whereNotIn('status', ['concluido', 'arquivado', 'cancelado'])->orderByDesc('id')->limit(300)->get()
            ->filter(fn ($p) => RequerimentoServidor::podeVer($u, $p))
            ->map(fn ($p) => ['id' => $p->id, 'numero' => $p->numero_protocolo, 'assunto' => $p->assunto, 'endereco' => $p->local_endereco,
                'latitude' => $p->latitude ? (float) $p->latitude : null, 'longitude' => $p->longitude ? (float) $p->longitude : null])->values();

        return response()->json([
            'usuario' => ['id' => $u->id, 'nome' => $u->name], 'orgao' => $u->ugAtual()?->nome, 'csrf' => csrf_token(),
            'formularios' => DB::table('fis_formularios')->where('ativo', true)->orderBy('nome')->get()->map(fn ($f) => [
                'id' => $f->id, 'nome' => $f->nome, 'descricao' => $f->descricao, 'objetos' => json_decode((string) $f->objetos, true) ?: [],
                'campos' => json_decode((string) $f->campos, true) ?: [], 'itens' => json_decode((string) $f->itens, true) ?: [],
                'versao' => (string) $f->updated_at]),
            'processos' => $processos, 'resultados' => FiscalizacaoController::RESULTADOS, 'respostas' => self::RESPOSTAS,
            'atualizado_em' => now()->toIso8601String(),
        ]);
    }

    /** Vistorias já sincronizadas deste fiscal (4.52.16). */
    public function minhas()
    {
        $this->exigir('fiscalizacao.campo');

        return response()->json($this->listaVistorias(DB::table('fis_vistorias as v'))->where('v.fiscal_id', Auth::id())->limit(100)->get()
            ->map(fn ($v) => $this->resumo($v)));
    }

    /** Recebe uma vistoria feita offline. Reenvio do mesmo uuid devolve a já gravada (4.52.14). */
    public function sincronizar(Request $request)
    {
        $this->exigir('fiscalizacao.campo');
        $request->validate(['dados' => ['required', 'json'], 'fotos' => ['array', 'max:30'], 'fotos.*' => ['file', 'max:15360', 'mimes:jpg,jpeg,png,webp']]);
        $d = json_decode($request->input('dados'), true);
        validator($d, [
            'uuid' => ['required', 'uuid'], 'formulario_id' => ['required', 'integer'], 'processo_id' => ['nullable', 'integer'],
            'objeto' => ['nullable', 'string', 'max:250'], 'local_descricao' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'], 'precisao' => ['nullable', 'numeric'],
            'realizada_em' => ['required', 'date'], 'resultado' => ['nullable', 'in:' . implode(',', array_keys(FiscalizacaoController::RESULTADOS))],
            'campos' => ['nullable', 'array'], 'respostas' => ['nullable', 'array'], 'observacao' => ['nullable', 'string', 'max:10000'],
            'legendas' => ['nullable', 'array'], 'legendas.*' => ['nullable', 'string', 'max:300'],
        ])->validate();

        if ($ja = DB::table('fis_vistorias')->where('uuid', $d['uuid'])->first()) {
            return response()->json(['ok' => true, 'id' => $ja->id, 'repetida' => true]);
        }
        $form = DB::table('fis_formularios')->find($d['formulario_id']);
        abort_unless($form, 422, 'Formulário não encontrado: atualize o app.');
        $processo = null;
        if (! empty($d['processo_id'])) {
            $processo = Processo::with('tipoProcesso')->find($d['processo_id']);
            abort_unless($processo && RequerimentoServidor::podeVer(Auth::user(), $processo), 422, 'Processo não encontrado ou sem acesso.');
            abort_if(empty($d['resultado']), 422, 'Informe o resultado da vistoria para registrar a diligência no processo.');
        }
        $u = Auth::user();
        $quando = Carbon::parse($d['realizada_em'])->utc();
        $modelo = ['nome' => $form->nome, 'campos' => json_decode((string) $form->campos, true) ?: [], 'itens' => json_decode((string) $form->itens, true) ?: []];

        $id = DB::transaction(function () use ($request, $d, $form, $processo, $u, $quando, $modelo) {
            $fotos = [];
            foreach ((array) $request->file('fotos', []) as $i => $arq) {
                $fotos[] = ['path' => $arq->store(TenantStorage::pasta('fiscalizacao'), 'documentos'), 'nome' => $arq->getClientOriginalName() ?: "foto-{$i}.jpg",
                    'legenda' => $d['legendas'][$i] ?? null, 'mime' => $arq->getMimeType() ?: 'image/jpeg', 'tamanho' => $arq->getSize()];
            }
            $id = DB::table('fis_vistorias')->insertGetId([
                'uuid' => $d['uuid'], 'formulario_id' => $form->id, 'modelo' => json_encode($modelo, JSON_UNESCAPED_UNICODE), 'fiscal_id' => $u->id,
                'processo_id' => $processo?->id, 'objeto' => $d['objeto'] ?? null, 'local_descricao' => $d['local_descricao'] ?? null,
                'latitude' => $d['latitude'] ?? null, 'longitude' => $d['longitude'] ?? null, 'precisao' => $d['precisao'] ?? null,
                'realizada_em' => $quando, 'resultado' => $d['resultado'] ?? null,
                'campos' => json_encode($d['campos'] ?? [], JSON_UNESCAPED_UNICODE), 'respostas' => json_encode($d['respostas'] ?? [], JSON_UNESCAPED_UNICODE),
                'observacao' => $d['observacao'] ?? null, 'fotos' => json_encode($fotos, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($processo) {
                $this->virarDiligencia($id, $processo, $request);
            }

            return $id;
        });

        return response()->json(['ok' => true, 'id' => $id, 'processo' => $processo?->numero_protocolo]);
    }

    // ---- internos ----

    /** Diligência no processo com as fotos e o relatório da vistoria como anexos. */
    private function virarDiligencia(int $vistoriaId, Processo $p, Request $request): void
    {
        $v = DB::table('fis_vistorias')->find($vistoriaId);
        $quando = Carbon::parse($v->realizada_em, 'UTC');
        $data = $quando->copy()->setTimezone('America/Sao_Paulo')->format('d-m-Y');
        $ids = [];
        $pdf = $this->gerarPdf($v)->output();
        $path = TenantStorage::pasta('processos') . '/' . Str::uuid() . '.pdf';
        Storage::disk('documentos')->put($path, $pdf);
        $ids[] = ProcessoAnexo::create(['processo_id' => $p->id, 'tramitacao_id' => $p->etapa_atual_id, 'nome' => "Relatório de vistoria {$data}.pdf",
            'arquivo_path' => $path, 'tamanho' => strlen($pdf), 'mime_type' => 'application/pdf', 'hash_sha256' => hash('sha256', $pdf), 'enviado_por' => $v->fiscal_id])->id;
        foreach (json_decode((string) $v->fotos, true) ?: [] as $i => $f) {
            $novo = TenantStorage::pasta('processos') . '/' . Str::uuid() . '.' . (pathinfo($f['path'], PATHINFO_EXTENSION) ?: 'jpg');
            Storage::disk('documentos')->copy($f['path'], $novo);
            $ids[] = ProcessoAnexo::create(['processo_id' => $p->id, 'tramitacao_id' => $p->etapa_atual_id,
                'nome' => mb_substr("Vistoria {$data} — foto " . ($i + 1) . ($f['legenda'] ? " — {$f['legenda']}" : ''), 0, 250) . '.jpg',
                'arquivo_path' => $novo, 'tamanho' => $f['tamanho'], 'mime_type' => $f['mime'], 'hash_sha256' => hash('sha256', Storage::disk('documentos')->get($novo)),
                'enviado_por' => $v->fiscal_id])->id;
        }
        $modelo = json_decode((string) $v->modelo, true);
        $respostas = json_decode((string) $v->respostas, true) ?: [];
        $naoAtende = collect($modelo['itens'] ?? [])->filter(fn ($i) => ($respostas[$i['chave']]['resposta'] ?? null) === 'nao_atende')->pluck('texto');
        $constatacao = trim(implode("\n", array_filter([
            "Vistoria pelo app ({$modelo['nome']})" . ($v->objeto ? " — {$v->objeto}" : ''),
            $v->local_descricao ? "Local: {$v->local_descricao}" : null,
            $naoAtende->isNotEmpty() ? 'Não atende: ' . $naoAtende->implode('; ') : 'Todos os itens verificados atendem ou não se aplicam.',
            $v->observacao,
        ])));
        $dil = DB::table('proc_diligencias')->insertGetId(['processo_id' => $p->id, 'fiscal_id' => $v->fiscal_id, 'realizada_em' => $quando,
            'resultado' => $v->resultado, 'constatacao' => mb_substr($constatacao, 0, 5000), 'latitude' => $v->latitude, 'longitude' => $v->longitude,
            'anexos' => json_encode($ids), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('fis_vistorias')->where('id', $vistoriaId)->update(['diligencia_id' => $dil]);
        if (! $p->latitude && $v->latitude) {
            $p->forceFill(['latitude' => $v->latitude, 'longitude' => $v->longitude])->saveQuietly();
        }
        DB::table('proc_historico')->insert(['processo_id' => $p->id, 'usuario_id' => $v->fiscal_id, 'acao' => 'diligencia',
            'detalhes' => json_encode(['resultado' => FiscalizacaoController::RESULTADOS[$v->resultado] ?? $v->resultado, 'arquivos' => count($ids),
                'constatacao' => mb_substr($constatacao, 0, 300), 'origem' => 'app da fiscalização'], JSON_UNESCAPED_UNICODE),
            'ip' => $request->ip(), 'created_at' => now()]);
    }

    private function gerarPdf(object $v)
    {
        $fotos = collect(json_decode((string) $v->fotos, true) ?: [])->map(fn ($f) => $f + ['data' => Storage::disk('documentos')->exists($f['path'])
            ? 'data:' . ($f['mime'] ?? 'image/jpeg') . ';base64,' . base64_encode(Storage::disk('documentos')->get($f['path'])) : null]);

        return Relatorio::pdf('pdf.vistoria', [
            'v' => $v, 'modelo' => json_decode((string) $v->modelo, true), 'campos' => json_decode((string) $v->campos, true) ?: [],
            'respostas' => json_decode((string) $v->respostas, true) ?: [], 'fotos' => $fotos, 'rotulos' => self::RESPOSTAS,
            'resultado' => FiscalizacaoController::RESULTADOS[$v->resultado] ?? null,
            'fiscal' => DB::table('users')->where('id', $v->fiscal_id)->value('name'),
            'processo' => $v->processo_id ? Processo::withoutGlobalScopes()->find($v->processo_id, ['id', 'numero_protocolo', 'assunto', 'ug_id']) : null,
            'ug' => Auth::user()?->ugAtual(),
        ]);
    }

    private function listaVistorias($q)
    {
        return $q->join('users as u', 'u.id', '=', 'v.fiscal_id')->leftJoin('proc_processos as p', 'p.id', '=', 'v.processo_id')
            ->orderByDesc('v.realizada_em')
            ->select(['v.id', 'v.uuid', 'v.modelo', 'v.objeto', 'v.local_descricao', 'v.realizada_em', 'v.resultado', 'v.respostas', 'v.fotos', 'v.created_at',
                'v.processo_id', 'v.latitude', 'v.longitude', 'u.name as fiscal', 'p.numero_protocolo']);
    }

    private function resumo(object $v): array
    {
        $resp = collect(json_decode((string) $v->respostas, true) ?: []);

        return ['id' => $v->id, 'uuid' => $v->uuid, 'formulario' => json_decode((string) $v->modelo, true)['nome'] ?? '', 'objeto' => $v->objeto,
            'local' => $v->local_descricao, 'fiscal' => $v->fiscal, 'processo' => $v->numero_protocolo, 'processo_id' => $v->processo_id,
            'resultado' => FiscalizacaoController::RESULTADOS[$v->resultado] ?? null,
            'nao_atende' => $resp->where('resposta', 'nao_atende')->count(), 'fotos' => count(json_decode((string) $v->fotos, true) ?: []),
            'gps' => $v->latitude !== null,
            'realizada_em' => Carbon::parse($v->realizada_em, 'UTC')->toIso8601String(), 'sincronizada_em' => Carbon::parse($v->created_at, 'UTC')->toIso8601String(),
            'pdf_url' => "/fiscal/vistorias/{$v->id}/pdf"];
    }

    private function exigir(string $perm): void
    {
        abort_unless(Auth::user()->super_admin || Auth::user()->can($perm), 403);
    }
}
