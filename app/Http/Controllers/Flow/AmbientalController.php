<?php

declare(strict_types=1);

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use App\Models\Processo\Processo;
use App\Support\LicenciamentoAmbiental as Lic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use RuntimeException;

/**
 * Licenciamento ambiental no GED: painel das licenças (em análise, emitidas, vencendo, condicionantes),
 * configuração (serviço da Carta, atividades, documentos, prazos de aviso), enquadramento no balcão,
 * emissão e indeferimento, cumprimento de condicionantes e a verificação pública da licença.
 */
class AmbientalController extends Controller
{
    public function index(Request $request)
    {
        $this->exigir();
        $status = array_key_exists($request->input('status'), Lic::STATUS) ? $request->input('status') : null;
        $hoje = now('America/Sao_Paulo')->toDateString();
        $dias = (int) (DB::table('amb_config')->value('dias_aviso_vencimento') ?? 120);
        $q = DB::table('amb_licencas as l')->join('proc_processos as p', 'p.id', '=', 'l.processo_id')->leftJoin('amb_atividades as a', 'a.id', '=', 'l.atividade_id')
            ->when(session('ug_id'), fn ($w) => $w->where('l.ug_id', session('ug_id')))
            ->when($status, fn ($w) => $w->where('l.status', $status))
            ->when($request->boolean('vencendo'), fn ($w) => $w->where('l.status', 'emitida')->where('l.valida_ate', '<=', now('America/Sao_Paulo')->addDays($dias)->toDateString()))
            ->when($b = trim((string) $request->input('busca')), fn ($w) => $w->where(fn ($x) => $x->where('l.numero', 'ilike', "%{$b}%")
                ->orWhere('l.empreendedor_nome', 'ilike', "%{$b}%")->orWhere('l.empreendimento_nome', 'ilike', "%{$b}%")->orWhere('p.numero_protocolo', 'ilike', "%{$b}%")));

        return Inertia::render('GED/Flow/Ambiental', [
            'licencas' => $q->orderByRaw("case when l.status = 'em_analise' then 0 else 1 end")->orderBy('l.valida_ate')->orderByDesc('l.id')->limit(300)
                ->get(['l.id', 'l.numero', 'l.modalidade', 'l.status', 'l.classe', 'l.valida_ate', 'l.empreendedor_nome', 'l.empreendimento_nome', 'l.created_at',
                    'p.id as processo_id', 'p.numero_protocolo', 'a.codigo as atividade_codigo', 'a.descricao as atividade'])
                ->map(fn ($l) => (array) $l + ['modalidade_rotulo' => Lic::MODALIDADES[$l->modalidade], 'status_rotulo' => Lic::STATUS[$l->status] ?? $l->status,
                    'vencendo' => $l->status === 'emitida' && $l->valida_ate <= now('America/Sao_Paulo')->addDays($dias)->toDateString(),
                    'condicionantes_pendentes' => DB::table('amb_condicionantes')->where('licenca_id', $l->id)->whereNull('cumprida_em')->count()]),
            'contagem' => DB::table('amb_licencas')->when(session('ug_id'), fn ($w) => $w->where('ug_id', session('ug_id')))
                ->groupBy('status')->selectRaw('status, count(*) as n')->pluck('n', 'status'),
            'vencendo' => DB::table('amb_licencas')->where('status', 'emitida')->whereBetween('valida_ate', [$hoje, now('America/Sao_Paulo')->addDays($dias)->toDateString()])->count(),
            'filtros' => ['status' => $status, 'vencendo' => $request->boolean('vencendo'), 'busca' => $b ?? ''],
            'statusRotulos' => Lic::STATUS,
        ]);
    }

    public function configuracao()
    {
        $this->exigir();

        return Inertia::render('GED/Flow/AmbientalConfiguracao', [
            'config' => DB::table('amb_config')->first(),
            'servicos' => DB::table('portal_servicos')->whereNotNull('tipo_processo_id')->whereNotNull('setor_responsavel_id')
                ->when(session('ug_id'), fn ($w) => $w->where('ug_id', session('ug_id')))->orderBy('titulo')->get(['id', 'titulo']),
            'atividades' => DB::table('amb_atividades')->orderBy('codigo')->get(),
            'documentos' => DB::table('amb_documentos')->orderBy('ordem')->orderBy('id')->get()->map(fn ($d) => (array) $d + ['modalidades' => json_decode((string) $d->modalidades, true) ?: []]),
            'modalidades' => Lic::MODALIDADES,
        ]);
    }

    public function salvarConfig(Request $request)
    {
        $this->exigir();
        $d = $request->validate(['servico_id' => ['nullable', 'integer', 'exists:portal_servicos,id'], 'dias_aviso_vencimento' => ['required', 'integer', 'between:1,365'],
            'dias_aviso_condicionante' => ['required', 'integer', 'between:1,180']]);
        $id = DB::table('amb_config')->value('id');
        $id ? DB::table('amb_config')->where('id', $id)->update($d + ['updated_at' => now()]) : DB::table('amb_config')->insert($d + ['created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Configuração do licenciamento salva.');
    }

    public function salvarAtividade(Request $request, ?int $id = null)
    {
        $this->exigir();
        $d = $request->validate(['codigo' => ['required', 'string', 'max:20', Rule::unique('amb_atividades', 'codigo')->ignore($id)], 'descricao' => ['required', 'string', 'max:300'],
            'potencial' => ['required', 'in:P,M,G'], 'unidade' => ['required', 'string', 'max:40'], 'porte_pequeno_ate' => ['required', 'numeric', 'min:0'],
            'porte_medio_ate' => ['required', 'numeric', 'gt:porte_pequeno_ate'], 'ativo' => ['boolean']],
            ['porte_medio_ate.gt' => 'O limite do porte médio tem de ser maior que o do pequeno.']);
        $d['ativo'] = $request->boolean('ativo', true);
        $id ? DB::table('amb_atividades')->where('id', $id)->update($d + ['updated_at' => now()]) : DB::table('amb_atividades')->insert($d + ['created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Atividade salva.');
    }

    public function salvarDocumento(Request $request, ?int $id = null)
    {
        $this->exigir();
        $d = $request->validate(['nome' => ['required', 'string', 'max:200'], 'modalidades' => ['required', 'array', 'min:1'], 'modalidades.*' => ['in:' . implode(',', array_keys(Lic::MODALIDADES))],
            'classe_minima' => ['required', 'integer', 'between:1,6'], 'obrigatorio' => ['boolean'], 'extensoes' => ['nullable', 'string', 'max:60'], 'ordem' => ['nullable', 'integer'], 'ativo' => ['boolean']]);
        $l = ['nome' => $d['nome'], 'modalidades' => json_encode(array_values($d['modalidades'])), 'classe_minima' => $d['classe_minima'], 'obrigatorio' => $request->boolean('obrigatorio', true),
            'extensoes' => $d['extensoes'] ?? null, 'ordem' => (int) ($d['ordem'] ?? 0), 'ativo' => $request->boolean('ativo', true), 'updated_at' => now()];
        $id ? DB::table('amb_documentos')->where('id', $id)->update($l) : DB::table('amb_documentos')->insert($l + ['created_at' => now()]);

        return back()->with('success', 'Documento salvo.');
    }

    /** Enquadramento (JSON) — para o balcão e a tela de configuração. */
    public function enquadrar(Request $request)
    {
        $d = $request->validate(['atividade_id' => ['required', 'integer'], 'porte_valor' => ['required', 'numeric', 'min:0'], 'modalidade' => ['required', 'string']]);
        try {
            return response()->json(Lic::enquadrar((int) $d['atividade_id'], (float) $d['porte_valor'], $d['modalidade']));
        } catch (RuntimeException $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }
    }

    /** Requerimento recebido no balcão: enquadra o processo já aberto no assunto do licenciamento. */
    public function registrar(Request $request, int $id)
    {
        $this->exigir();
        $d = $request->validate(['atividade_id' => ['required', 'integer'], 'porte_valor' => ['required', 'numeric', 'min:0'], 'modalidade' => ['required', 'string'],
            'empreendedor_nome' => ['required', 'string', 'max:200'], 'empreendedor_documento' => ['nullable', 'string', 'max:20'], 'empreendedor_email' => ['nullable', 'email', 'max:150'],
            'empreendimento_nome' => ['nullable', 'string', 'max:200'], 'empreendimento_endereco' => ['nullable', 'string', 'max:300']]);
        $p = Processo::findOrFail($id);
        try {
            $enq = Lic::enquadrar((int) $d['atividade_id'], (float) $d['porte_valor'], $d['modalidade']);
            Lic::registrar($p, $enq, (float) $d['porte_valor'], $d);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Requerimento enquadrado na classe {$enq['classe']}.");
    }

    public function emitir(Request $request, int $id)
    {
        $this->exigir();
        $d = $request->validate(['validade_anos' => ['required', 'integer', 'between:1,10'], 'condicionantes' => ['nullable', 'array', 'max:50'],
            'condicionantes.*.descricao' => ['nullable', 'string', 'max:2000'], 'condicionantes.*.prazo' => ['nullable', 'date']]);

        return $this->naLicenca($id, fn ($l) => Lic::emitir($l, Auth::user(), $d['condicionantes'] ?? [], (int) $d['validade_anos']),
            'Licença emitida. Assine o PDF da licença para torná-la oficial (está em Assinaturas pendentes).');
    }

    public function indeferir(Request $request, int $id)
    {
        $this->exigir();
        $d = $request->validate(['motivo' => ['required', 'string', 'max:5000']]);

        return $this->naLicenca($id, fn ($l) => Lic::indeferir($l, Auth::user(), $d['motivo']), 'Requerimento indeferido; o empreendedor foi avisado.');
    }

    public function cumprir(Request $request, int $id)
    {
        $this->exigir();
        $d = $request->validate(['cumprimento' => ['required', 'string', 'max:5000']]);
        $c = DB::table('amb_condicionantes')->where('id', $id)->first() ?? abort(404);
        $this->licencaDaUg((int) $c->licenca_id);
        Lic::cumprirCondicionante($c, Auth::user(), $d['cumprimento']);

        return back()->with('success', 'Cumprimento da condicionante registrado.');
    }

    /** Verificação pública da licença (QR do PDF), sem login. */
    public function verificar(string $codigo)
    {
        return Inertia::render('Ambiental/Verificar', ['codigo' => $codigo, 'licenca' => Lic::verificar($codigo)]);
    }

    private function naLicenca(int $id, callable $fn, string $sucesso)
    {
        $l = $this->licencaDaUg($id);
        try {
            DB::transaction(fn () => $fn(DB::table('amb_licencas')->where('id', $l->id)->lockForUpdate()->first()));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $sucesso);
    }

    private function licencaDaUg(int $id): object
    {
        $l = DB::table('amb_licencas')->where('id', $id)->first() ?? abort(404);
        Processo::findOrFail($l->processo_id);   // escopo de UG pelo processo

        return $l;
    }

    private function exigir(): void
    {
        abort_unless(Auth::user()->super_admin || Auth::user()->can('ambiental.gerir'), 403);
    }
}
