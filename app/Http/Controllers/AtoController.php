<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\Processo\Ato;
use App\Models\Processo\AtoTipo;
use App\Models\SolicitacaoAssinatura;
use App\Models\User;
use App\Support\Atos;
use App\Support\DocumentoRedigido;
use App\Support\HtmlSeguro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Atos oficiais (TR 4.42): tipos (numeração, modelo .docx, quem elabora, quem assina), elaboração,
 * PDF/A com QR, assinatura de servidores e externos, publicação e verificação pública.
 */
class AtoController extends Controller
{
    public function index(Request $request)
    {
        $f = array_filter(['tipo' => $request->integer('tipo') ?: null, 'status' => $request->input('status'), 'ano' => $request->integer('ano') ?: null,
            'busca' => trim((string) $request->input('busca'))]);
        $lista = Ato::with(['tipo:id,nome,sigla', 'autor:id,name'])
            ->when($f['tipo'] ?? null, fn ($q, $v) => $q->where('tipo_id', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['ano'] ?? null, fn ($q, $v) => $q->where('ano', $v))
            ->when($f['busca'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('ementa', 'ilike', "%{$v}%")->orWhere('conteudo', 'ilike', "%{$v}%")
                ->orWhereRaw("numero::text || '/' || ano::text like ?", ["%{$v}%"])))
            ->orderByDesc('ano')->orderByDesc('numero')->orderByDesc('id')->paginate(20)->withQueryString()
            ->through(fn (Ato $a) => self::resumo($a));

        return Inertia::render('GED/Atos/Index', [
            'atos' => $lista, 'filtros' => $f, 'status' => Ato::STATUS,
            'tipos' => AtoTipo::where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'sigla', 'numeracao', 'setores']),
            'pode_elaborar' => Atos::podeElaborar(Auth::user()), 'pode_tipos' => Auth::user()->super_admin || Auth::user()->can('ato.tipos'),
        ]);
    }

    // ── Tipos de ato ──────────────────────────────────────────────────

    public function tipos()
    {
        $this->exigir('ato.tipos');

        return Inertia::render('GED/Atos/Tipos', [
            'tipos' => AtoTipo::orderByDesc('ativo')->orderBy('nome')->get()->map(fn (AtoTipo $t) => $t->only(['id', 'nome', 'sigla', 'numeracao', 'modelo_nome', 'setores', 'signatarios', 'publico', 'ativo'])
                + ['marcadores' => $this->marcadoresDoModelo($t)]),
            'numeracoes' => AtoTipo::NUMERACOES, 'marcadores' => Atos::MARCADORES,
            'unidades' => \App\Models\UgOrganograma::where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
            'usuarios' => User::where('tipo', 'interno')->whereNull('suspenso_em')->orderBy('name')->get(['id', 'name', 'cargo']),
        ]);
    }

    public function salvarTipo(Request $request, ?int $id = null)
    {
        $this->exigir('ato.tipos');
        $d = $request->validate([
            'nome' => ['required', 'string', 'max:100'], 'sigla' => ['required', 'string', 'max:20'],
            'numeracao' => ['required', Rule::in(array_keys(AtoTipo::NUMERACOES))],
            'setores' => ['nullable', 'array'], 'setores.*' => ['integer'], 'signatarios' => ['nullable', 'array'], 'signatarios.*' => ['integer'],
            'publico' => ['nullable', 'boolean'], 'ativo' => ['nullable', 'boolean'],
            'modelo' => ['nullable', 'file', 'extensions:docx', 'max:10240'], 'remover_modelo' => ['nullable', 'boolean'],
        ]);
        $tipo = $id ? AtoTipo::findOrFail($id) : new AtoTipo();
        $tipo->fill(['nome' => $d['nome'], 'sigla' => mb_strtoupper($d['sigla']), 'numeracao' => $d['numeracao'],
            'setores' => array_values(array_map('intval', $d['setores'] ?? [])), 'signatarios' => array_values(array_map('intval', $d['signatarios'] ?? [])),
            'publico' => $request->boolean('publico', true), 'ativo' => $request->boolean('ativo', true)]);
        if ($request->boolean('remover_modelo')) {
            $tipo->modelo_path = null;
            $tipo->modelo_nome = null;
        }
        if ($arq = $request->file('modelo')) {
            $tipo->modelo_path = $arq->store(\App\Tenant\TenantStorage::pasta('atos/modelos'), 'documentos');
            $tipo->modelo_nome = $arq->getClientOriginalName();
        }
        $tipo->save();

        return back()->with('success', 'Tipo de ato salvo.');
    }

    // ── Ato ───────────────────────────────────────────────────────────

    public function create(Request $request)
    {
        abort_unless(Atos::podeElaborar(Auth::user()), 403, 'Você não está autorizado a elaborar atos.');

        return Inertia::render('GED/Atos/Form', $this->dadosForm(null, $request->integer('processo_id') ?: null));
    }

    public function edit(int $id)
    {
        $ato = Ato::findOrFail($id);
        abort_unless($ato->status === 'rascunho' && Atos::podeElaborar(Auth::user(), $ato->tipo), 403);

        return Inertia::render('GED/Atos/Form', $this->dadosForm($ato, $ato->processo_id));
    }

    public function store(Request $request)
    {
        $d = $this->validar($request);
        $tipo = AtoTipo::findOrFail($d['tipo_id']);
        abort_unless(Atos::podeElaborar(Auth::user(), $tipo), 403, 'Seu setor não elabora este tipo de ato.');
        $ato = Ato::create($d + ['ug_id' => (int) session('ug_id', Auth::user()->ug_id), 'autor_id' => Auth::id(), 'unidade_id' => Auth::user()->unidade_id,
            'status' => 'rascunho', 'qr_code_token' => (string) Str::uuid()]);

        return redirect("/atos/{$ato->id}")->with('success', 'Rascunho do ato salvo. Gere o PDF/A quando o texto estiver pronto.');
    }

    public function update(Request $request, int $id)
    {
        $ato = Ato::findOrFail($id);
        abort_unless($ato->status === 'rascunho' && Atos::podeElaborar(Auth::user(), $ato->tipo), 403);
        $ato->update($this->validar($request, $ato) + ['pdf_path' => null, 'pdfa' => false]);

        return redirect("/atos/{$ato->id}")->with('success', 'Rascunho atualizado.');
    }

    public function show(int $id)
    {
        $ato = Ato::with(['tipo', 'autor:id,name', 'processo:id,numero_protocolo'])->findOrFail($id);
        $sol = $ato->documento_id ? SolicitacaoAssinatura::where('documento_id', $ato->documento_id)->latest('id')->first() : null;
        $assinaturas = $sol ? DB::table('ged_assinaturas as a')->leftJoin('users as u', 'u.id', '=', 'a.signatario_id')
            ->where('a.solicitacao_id', $sol->id)->orderBy('a.ordem')
            ->get(['a.status', 'a.assinado_em', 'u.name', 'a.nome_signatario', 'a.email_signatario'])
            ->map(fn ($a) => ['nome' => $a->name ?? $a->nome_signatario ?? $a->email_signatario, 'status' => $a->status, 'em' => $a->assinado_em]) : collect();
        $podeElaborar = Atos::podeElaborar(Auth::user(), $ato->tipo);

        return Inertia::render('GED/Atos/Show', [
            'ato' => self::resumo($ato) + [
                'conteudo' => $ato->conteudo, 'campos' => $ato->campos ?? [], 'modelo' => $ato->tipo?->modelo_nome, 'pdfa' => $ato->pdfa,
                'tem_pdf' => (bool) $ato->pdf_path, 'documento_id' => $ato->documento_id, 'verificar_url' => Atos::urlVerificacao($ato),
                'processo' => $ato->processo ? ['id' => $ato->processo->id, 'numero' => $ato->processo->numero_protocolo] : null,
                'assinatura' => $sol ? ['status' => $sol->status, 'assinaturas' => $assinaturas] : null,
            ],
            'pode' => [
                'editar' => $podeElaborar && $ato->status === 'rascunho',
                'gerar' => $podeElaborar && $ato->status === 'rascunho',
                'assinar' => $podeElaborar && $ato->status === 'rascunho' && $ato->pdf_path,
                'publicar' => $podeElaborar && $ato->status === 'assinatura' && $sol?->status === 'concluida',
                'revogar' => $podeElaborar && $ato->status === 'publicado',
            ],
            'signatarios_padrao' => User::whereIn('id', $ato->tipo?->signatarios ?? [])->orderBy('name')->get(['id', 'name']),
            'usuarios' => User::where('tipo', 'interno')->whereNull('suspenso_em')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Numera (se automático) e gera o PDF/A com QR (TR 4.42.3, 4.42.7, 4.42.8). */
    public function gerar(int $id)
    {
        $ato = Ato::with('tipo')->findOrFail($id);
        abort_unless($ato->status === 'rascunho' && Atos::podeElaborar(Auth::user(), $ato->tipo), 403);
        DB::transaction(fn () => Atos::numerar($ato));
        try {
            Atos::gerarPdf($ato->fresh(['tipo', 'autor', 'processo', 'ug']));
        } catch (\RuntimeException $e) {
            report($e);

            return back()->with('error', 'Não foi possível gerar o PDF/A. Confira o modelo .docx do tipo de ato.');
        }

        return back()->with('success', "PDF/A de {$ato->fresh('tipo')->identificacao()} gerado.");
    }

    /** Leva o PDF/A à assinatura dos servidores (roteamento do tipo) e dos externos (TR 4.42.4, 4.42.5). */
    public function assinar(Request $request, int $id)
    {
        $ato = Ato::with('tipo')->findOrFail($id);
        abort_unless($ato->status === 'rascunho' && $ato->pdf_path && Atos::podeElaborar(Auth::user(), $ato->tipo), 403);
        $d = $request->validate([
            'servidores' => ['nullable', 'array'], 'servidores.*' => ['integer', 'exists:users,id'],
            'externos' => ['nullable', 'array', 'max:10'], 'externos.*.nome' => ['required', 'string', 'max:200'],
            'externos.*.cpf' => ['required', 'string', 'max:14', function ($a, $v, $falha) {
                if (! \App\Services\ConviteAssinaturaService::cpfValido(preg_replace('/\D/', '', (string) $v))) {
                    $falha('CPF de coassinante inválido.');
                }
            }],
            'externos.*.email' => ['required', 'email', 'max:255'], 'externos.*.telefone' => ['nullable', 'string', 'max:20'],
        ]);
        $servidores = array_values(array_unique(array_map('intval', $d['servidores'] ?? [])));
        $externos = array_map(fn ($e) => ['nome' => $e['nome'], 'cpf' => preg_replace('/\D/', '', $e['cpf']), 'email' => $e['email'], 'telefone' => $e['telefone'] ?? null], $d['externos'] ?? []);
        if (! $servidores && ! $externos) {
            return back()->withErrors(['servidores' => 'Escolha quem assina o ato.']);
        }
        $nome = $ato->identificacao();
        $docId = DocumentoRedigido::pedirAssinaturaDoPdf(Storage::disk('documentos')->get($ato->pdf_path), $nome, $ato->ementa, $ato->tipo->nome,
            "{$nome} | {$ato->ementa} | " . \App\Support\Variaveis::textoPuro($ato->conteudo), $servidores, $externos, Auth::user());
        $ato->update(['documento_id' => $docId, 'status' => 'assinatura']);

        return back()->with('success', "{$nome} enviado para assinatura.");
    }

    public function publicar(int $id)
    {
        $ato = Ato::with('tipo')->findOrFail($id);
        $concluida = $ato->documento_id && SolicitacaoAssinatura::where('documento_id', $ato->documento_id)->latest('id')->value('status') === 'concluida';
        abort_unless($ato->status === 'assinatura' && $concluida && Atos::podeElaborar(Auth::user(), $ato->tipo), 422, 'O ato só é publicado depois de todas as assinaturas.');
        $ato->update(['status' => 'publicado', 'publicado_em' => now()]);

        return back()->with('success', "{$ato->identificacao()} publicado.");
    }

    public function revogar(int $id)
    {
        $ato = Ato::with('tipo')->findOrFail($id);
        abort_unless($ato->status === 'publicado' && Atos::podeElaborar(Auth::user(), $ato->tipo), 403);
        $ato->update(['status' => 'revogado']);

        return back()->with('success', "{$ato->identificacao()} marcado como revogado.");
    }

    public function pdf(int $id)
    {
        return self::respostaPdf(Ato::with('tipo')->findOrFail($id));
    }

    /** Verificação pública pelo QR (TR 4.42.8). */
    public function verificar(string $token)
    {
        $ato = Str::isUuid($token) ? Ato::withoutGlobalScopes()->with(['tipo', 'autor:id,name', 'ug:id,nome'])->where('qr_code_token', $token)->first() : null;
        \App\Support\Verificacoes::registrar(request(), $token, 'web', (bool) $ato, 'ato', $ato?->id);
        if (! $ato) {
            return Inertia::render('GED/VerificarComunicacao', ['valido' => false, 'tipo' => 'Ato oficial']);
        }

        return Inertia::render('GED/VerificarComunicacao', ['valido' => true, 'tipo' => $ato->tipo?->nome ?? 'Ato oficial', 'comunicacao' => array_filter([
            'numero' => $ato->identificacao(), 'assunto' => $ato->ementa, 'remetente' => $ato->autor?->name, 'orgao' => $ato->ug?->nome,
            'emitido_em' => $ato->data_ato?->format('d/m/Y'), 'situacao' => Ato::STATUS[$ato->status] ?? $ato->status,
        ])]);
    }

    /** O PDF que vale: o assinado (versão atual no GED) ou o PDF/A gerado. */
    public static function respostaPdf(Ato $ato)
    {
        $path = $ato->documento_id ? Documento::withoutGlobalScopes()->find($ato->documento_id)?->versaoAtual?->arquivo_path : null;
        $path ??= $ato->pdf_path;
        abort_unless($path && Storage::disk('documentos')->exists($path), 404, 'O PDF do ato ainda não foi gerado.');

        return response(Storage::disk('documentos')->get($path), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . Str::slug($ato->identificacao()) . '.pdf"']);
    }

    public static function resumo(Ato $a): array
    {
        return [
            'id' => $a->id, 'identificacao' => $a->identificacao(), 'tipo' => $a->tipo?->nome, 'tipo_id' => $a->tipo_id, 'numero' => $a->numero, 'ano' => $a->ano,
            'ementa' => $a->ementa, 'data_ato' => $a->data_ato?->format('Y-m-d'), 'status' => $a->status, 'status_rotulo' => Ato::STATUS[$a->status] ?? $a->status,
            'autor' => $a->autor?->name, 'publicado_em' => $a->publicado_em?->toIso8601String(),
        ];
    }

    private function dadosForm(?Ato $ato, ?int $processoId): array
    {
        $tipos = AtoTipo::where('ativo', true)->orderBy('nome')->get()->filter(fn ($t) => Atos::podeElaborar(Auth::user(), $t))->values();

        return [
            'ato' => $ato ? self::resumo($ato) + ['conteudo' => $ato->conteudo, 'campos' => $ato->campos ?? []] : null,
            'tipos' => $tipos->map(fn (AtoTipo $t) => ['id' => $t->id, 'nome' => $t->nome, 'numeracao' => $t->numeracao, 'modelo' => $t->modelo_nome,
                'extras' => array_values(array_diff($this->marcadoresDoModelo($t), array_keys(Atos::MARCADORES)))]),
            'processo' => $processoId ? \App\Models\Processo\Processo::find($processoId, ['id', 'numero_protocolo', 'assunto']) : null,
        ];
    }

    /** @return list<string> marcadores ${...} do modelo .docx do tipo */
    private function marcadoresDoModelo(AtoTipo $t): array
    {
        if (! $t->modelo_path || ! Storage::disk('documentos')->exists($t->modelo_path)) {
            return [];
        }
        $tmp = tempnam(sys_get_temp_dir(), 'mod');
        file_put_contents($tmp, Storage::disk('documentos')->get($t->modelo_path));
        try {
            return array_values(array_unique((new TemplateProcessor($tmp))->getVariables()));
        } catch (\Throwable) {
            return [];
        } finally {
            @unlink($tmp);
        }
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Ato $ato = null): array
    {
        $tipo = AtoTipo::find($request->integer('tipo_id') ?: $ato?->tipo_id);
        $d = $request->validate([
            'tipo_id' => ['required', 'integer', 'exists:proc_ato_tipos,id'],
            'numero' => [$tipo?->numeracao === 'manual' ? 'required' : 'nullable', 'integer', 'min:1'],
            'data_ato' => ['required', 'date'], 'ementa' => ['required', 'string', 'max:1000'],
            'conteudo' => [$tipo?->modelo_path ? 'nullable' : 'required', 'string', 'max:500000'],
            'campos' => ['nullable', 'array'], 'campos.*' => ['nullable', 'string', 'max:2000'],
            'processo_id' => ['nullable', 'integer', 'exists:proc_processos,id'],
        ], ['numero.required' => 'Este tipo de ato tem numeração manual: informe o número.']);
        $ano = (int) substr($d['data_ato'], 0, 4);
        if ($tipo?->numeracao === 'manual' && Ato::where('tipo_id', $tipo->id)->where('ano', $ano)->where('numero', $d['numero'])
            ->when($ato, fn ($q) => $q->where('id', '<>', $ato->id))->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['numero' => "Já existe {$tipo->nome} nº {$d['numero']}/{$ano}."]);
        }

        return ['tipo_id' => $d['tipo_id'], 'numero' => $tipo?->numeracao === 'manual' ? (int) $d['numero'] : ($ato?->numero), 'ano' => $ano,
            'data_ato' => $d['data_ato'], 'ementa' => $d['ementa'], 'conteudo' => isset($d['conteudo']) ? HtmlSeguro::limpar($d['conteudo']) : null,
            'campos' => array_filter($d['campos'] ?? [], fn ($v) => $v !== null && $v !== ''), 'processo_id' => $d['processo_id'] ?? null];
    }

    private function exigir(string $perm): void
    {
        abort_unless(Auth::user()->super_admin || Auth::user()->can($perm), 403);
    }
}
