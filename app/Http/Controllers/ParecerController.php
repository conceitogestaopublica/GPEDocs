<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Processo\Memorando;
use App\Models\Processo\Parecer;
use App\Models\Processo\ParecerAnexo;
use App\Models\Processo\Processo;
use App\Support\DocumentoRedigido;
use App\Support\HtmlSeguro;
use App\Support\Pareceres;
use App\Support\RequerimentoServidor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Pareceres (TR 4.39): lista, emissão por quem é autorizado, tela, PDF com QR, anexos,
 * assinatura e os setores autorizados a emitir.
 */
class ParecerController extends Controller
{
    public function index(Request $request)
    {
        $f = array_filter(['busca' => trim((string) $request->input('busca')), 'conclusao' => $request->input('conclusao'),
            'de' => $request->input('de'), 'ate' => $request->input('ate')]);
        $lista = Parecer::with(['remetente:id,name', 'processo:id,numero_protocolo'])
            ->when($f['busca'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('numero', 'ilike', "%{$v}%")->orWhere('assunto', 'ilike', "%{$v}%")
                ->orWhere('ementa', 'ilike', "%{$v}%")))
            ->when($f['conclusao'] ?? null, fn ($q, $v) => $q->where('conclusao', $v))
            ->when($f['de'] ?? null, fn ($q, $v) => $q->where('enviado_em', '>=', $v))
            ->when($f['ate'] ?? null, fn ($q, $v) => $q->where('enviado_em', '<=', $v . ' 23:59:59'))
            ->orderByDesc('id')->paginate(20)->withQueryString()
            ->through(fn (Parecer $p) => self::resumo($p));
        $admin = Auth::user()->super_admin || Auth::user()->can('admin.configuracoes');

        return Inertia::render('GED/Pareceres/Index', [
            'pareceres' => $lista, 'filtros' => $f, 'conclusoes' => Parecer::CONCLUSOES, 'pode_emitir' => Pareceres::podeEmitir(Auth::user()),
            'config' => $admin ? ['setores' => Pareceres::setoresAutorizados(),
                'unidades' => \App\Models\UgOrganograma::where('ativo', true)->orderBy('nome')->get(['id', 'nome'])] : null,
        ]);
    }

    public function create(Request $request)
    {
        abort_unless(Pareceres::podeEmitir(Auth::user()), 403, 'Seu usuário ou setor não está autorizado a emitir pareceres.');
        $processo = $request->integer('processo_id') ? Processo::find($request->integer('processo_id'), ['id', 'numero_protocolo', 'assunto']) : null;
        $memorando = $request->integer('memorando_id') ? Memorando::find($request->integer('memorando_id'), ['id', 'numero', 'assunto']) : null;

        return Inertia::render('GED/Pareceres/Create', [
            'processo' => $processo, 'memorando' => $memorando, 'conclusoes' => Parecer::CONCLUSOES,
            'variaveis' => Parecer::variaveisParaEditor(),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        abort_unless(Pareceres::podeEmitir($user), 403);
        $d = $request->validate([
            'assunto'      => ['required', 'string', 'max:255'],
            'ementa'       => ['nullable', 'string', 'max:2000'],
            'conteudo'     => ['required', 'string', 'max:500000'],
            'conclusao'    => ['required', \Illuminate\Validation\Rule::in(array_keys(Parecer::CONCLUSOES))],
            'processo_id'  => ['nullable', 'integer', 'exists:proc_processos,id'],
            'memorando_id' => ['nullable', 'integer', 'exists:proc_memorandos,id'],
            'anexos'       => ['nullable', 'array', 'max:20'],
            'anexos.*'     => ['file', 'max:51200'],
        ]);
        if (trim(strip_tags($d['conteudo'])) === '') {
            return back()->withErrors(['conteudo' => 'Escreva o texto do parecer.']);
        }
        $ugId = (int) session('ug_id', $user->ug_id);
        $setor = $user->unidade_id ? \App\Models\UgOrganograma::find($user->unidade_id) : null;

        $parecer = DB::transaction(function () use ($d, $user, $ugId, $setor, $request) {
            $p = Parecer::create([
                'ug_id' => $ugId, 'numero' => Pareceres::proximoNumero($ugId), 'assunto' => $d['assunto'], 'ementa' => $d['ementa'] ?? null,
                'conteudo' => HtmlSeguro::limpar($d['conteudo']), 'conclusao' => $d['conclusao'], 'remetente_id' => $user->id,
                'setor_origem' => $setor?->nome, 'unidade_id' => $setor?->id, 'processo_id' => $d['processo_id'] ?? null,
                'memorando_id' => $d['memorando_id'] ?? null, 'status' => 'emitido', 'enviado_em' => now(), 'qr_code_token' => (string) Str::uuid(),
            ]);
            foreach ((array) $request->file('anexos', []) as $arq) {
                ParecerAnexo::create(['parecer_id' => $p->id, 'nome' => mb_substr($arq->getClientOriginalName(), 0, 255),
                    'arquivo_path' => $arq->store(\App\Tenant\TenantStorage::pasta('pareceres'), 'documentos'), 'tamanho' => $arq->getSize(),
                    'mime_type' => $arq->getMimeType() ?: 'application/octet-stream', 'hash_sha256' => hash_file('sha256', $arq->getRealPath()), 'enviado_por' => $user->id]);
            }
            if ($p->processo_id) {
                DB::table('proc_historico')->insert(['processo_id' => $p->processo_id, 'usuario_id' => $user->id, 'acao' => 'parecer_emitido',
                    'detalhes' => json_encode(['parecer' => $p->numero, 'conclusao' => Parecer::CONCLUSOES[$p->conclusao]], JSON_UNESCAPED_UNICODE),
                    'ip' => $request->ip(), 'created_at' => now()]);
            }

            return $p;
        });

        return redirect("/pareceres/{$parecer->id}")->with('success', "Parecer {$parecer->numero} emitido.");
    }

    public function show(int $id)
    {
        $p = $this->carregar($id);

        return Inertia::render('GED/Pareceres/Show', [
            'parecer' => self::resumo($p) + [
                'ementa' => $p->ementa, 'conteudo_html' => $p->conteudoFinal(), 'setor' => $p->setor_origem,
                'memorando' => $p->memorando ? ['id' => $p->memorando->id, 'numero' => $p->memorando->numero] : null,
                'anexos' => $p->anexos->map(fn ($a) => ['id' => $a->id, 'nome' => $a->nome, 'tamanho' => $a->tamanho])->values(),
                'documento_id' => $p->documento_id, 'verificar_url' => url("/pareceres/verificar/{$p->qr_code_token}"),
            ],
            'pode_assinar' => $p->remetente_id === Auth::id() && ! $p->documento_id,
        ]);
    }

    public function pdf(int $id)
    {
        $p = $this->carregar($id);

        return response(DocumentoRedigido::pdfBytes('parecer', $p), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="parecer-' . str_replace('/', '-', $p->numero) . '.pdf"']);
    }

    public function anexo(int $id, int $anexo)
    {
        $p = $this->carregar($id);
        $a = $p->anexos->firstWhere('id', $anexo) ?? abort(404);

        return Storage::disk('documentos')->download($a->arquivo_path, $a->nome);
    }

    /** Leva o PDF à assinatura do parecerista (e de quem mais ele indicar). */
    public function assinar(Request $request, int $id)
    {
        $p = $this->carregar($id);
        abort_unless($p->remetente_id === Auth::id(), 403);
        abort_if($p->documento_id, 422, 'Este parecer já foi enviado para assinatura.');
        $outros = array_map('intval', (array) $request->input('servidores', []));
        $docId = DocumentoRedigido::pedirAssinatura('parecer', $p, [Auth::id(), ...$outros], [], Auth::user());
        $p->update(['documento_id' => $docId]);

        return redirect("/documentos/{$docId}")->with('success', 'PDF do parecer enviado para assinatura.');
    }

    /** Setores autorizados a emitir (vazio = todos com a permissão). */
    public function setores(Request $request)
    {
        abort_unless(Auth::user()->super_admin || Auth::user()->can('admin.configuracoes'), 403);
        $ids = array_values(array_unique(array_map('intval', (array) $request->input('setores', []))));
        DB::transaction(function () use ($ids) {
            DB::table('proc_parecer_setores')->delete();
            DB::table('proc_parecer_setores')->insert(array_map(fn ($i) => ['unidade_id' => $i], $ids));
        });

        return back()->with('success', $ids ? count($ids) . ' setor(es) autorizado(s) a emitir pareceres.' : 'Qualquer setor com a permissão pode emitir pareceres.');
    }

    /** Pareceres de um processo, para a tela do processo. */
    public static function doProcesso(int $processoId): array
    {
        return Parecer::with('remetente:id,name')->where('processo_id', $processoId)->orderBy('id')->get()->map(fn (Parecer $p) => self::resumo($p))->all();
    }

    private function carregar(int $id): Parecer
    {
        $p = Parecer::with(['remetente:id,name', 'processo', 'memorando', 'anexos'])->findOrFail($id);
        // Parecer de requerimento pessoal de servidor segue o sigilo do processo (TR 4.36.1)
        abort_if($p->processo && ! RequerimentoServidor::podeVer(Auth::user(), $p->processo), 403);

        return $p;
    }

    private static function resumo(Parecer $p): array
    {
        return [
            'id' => $p->id, 'numero' => $p->numero, 'assunto' => $p->assunto, 'conclusao' => $p->conclusao,
            'conclusao_rotulo' => Parecer::CONCLUSOES[$p->conclusao] ?? $p->conclusao, 'parecerista' => $p->remetente?->name,
            'emitido_em' => $p->enviado_em?->toIso8601String(),
            'processo' => $p->processo ? ['id' => $p->processo->id, 'numero' => $p->processo->numero_protocolo] : null,
        ];
    }
}
