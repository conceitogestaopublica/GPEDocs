<?php

declare(strict_types=1);

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use App\Mail\RespostaInscricaoPss;
use App\Models\Processo\Pss;
use App\Models\Processo\PssInscricao;
use App\Models\Processo\TipoProcesso;
use App\Support\RastreioEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Processo seletivo simplificado no GED (TR 4.41): editais (período, cargos, requisitos e o
 * assunto da ficha), inscrições recebidas pelo portal, resposta ao candidato e CSV.
 */
class PssController extends Controller
{
    public function index()
    {
        return Inertia::render('GED/Flow/Pss', [
            'editais' => Pss::with('tipoProcesso:id,nome')->withCount('inscricoes')->orderByDesc('inscricoes_inicio')->get()
                ->map(fn (Pss $p) => self::edital($p) + ['inscricoes' => $p->inscricoes_count]),
            'assuntos' => TipoProcesso::where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function salvar(Request $request, ?int $id = null)
    {
        $d = $request->validate([
            'titulo' => ['required', 'string', 'max:200'], 'edital_numero' => ['nullable', 'string', 'max:40'],
            'descricao' => ['nullable', 'string', 'max:10000'], 'requisitos' => ['nullable', 'string', 'max:10000'],
            'cargos' => ['nullable', 'array', 'max:50'], 'cargos.*.nome' => ['required', 'string', 'max:150'], 'cargos.*.vagas' => ['nullable', 'integer', 'min:0'],
            'tipo_processo_id' => ['nullable', 'integer', 'exists:proc_tipos_processo,id'],
            'inscricoes_inicio' => ['required', 'date'], 'inscricoes_fim' => ['required', 'date', 'after:inscricoes_inicio'],
            'publicado' => ['nullable', 'boolean'],
        ]);
        $d['publicado'] = $request->boolean('publicado');
        $d['inscricoes_inicio'] = \Carbon\Carbon::parse($d['inscricoes_inicio'], \App\Support\Expediente::FUSO)->utc();
        $d['inscricoes_fim'] = \Carbon\Carbon::parse($d['inscricoes_fim'], \App\Support\Expediente::FUSO)->utc();
        if ($id) {
            Pss::findOrFail($id)->update($d);
        } else {
            Pss::create($d + ['ug_id' => (int) session('ug_id', Auth::user()->ug_id), 'criado_por' => Auth::id()]);
        }

        return back()->with('success', 'Processo seletivo salvo.');
    }

    public function show(Request $request, int $id)
    {
        $pss = Pss::with('tipoProcesso')->findOrFail($id);
        $f = array_filter(['situacao' => $request->input('situacao'), 'cargo' => $request->input('cargo'), 'busca' => trim((string) $request->input('busca'))]);
        $campos = collect($pss->tipoProcesso?->schema_formulario ?? [])->pluck('label', 'campo')->all();

        return Inertia::render('GED/Flow/PssShow', [
            'pss' => self::edital($pss), 'filtros' => $f, 'situacoes' => PssInscricao::SITUACOES, 'campos' => $campos,
            'resumo' => PssInscricao::where('pss_id', $pss->id)->selectRaw('situacao, count(*) as qtd')->groupBy('situacao')->pluck('qtd', 'situacao'),
            'inscricoes' => $this->consulta($pss, $f)->with('anexos:id,inscricao_id,nome')->orderBy('id')->paginate(30)->withQueryString()
                ->through(fn (PssInscricao $i) => [
                    'id' => $i->id, 'numero' => $i->numero, 'cargo' => $i->cargo, 'nome' => $i->nome, 'cpf' => \App\Support\DocumentoPessoa::mascararCpf($i->cpf),
                    'email' => $i->email, 'telefone' => $i->telefone, 'dados' => $i->dados ?? [], 'situacao' => $i->situacao, 'resposta' => $i->resposta,
                    'inscrito_em' => $i->created_at?->toIso8601String(), 'anexos' => $i->anexos->map(fn ($a) => ['id' => $a->id, 'nome' => $a->nome])->values(),
                ]),
        ]);
    }

    /** Situação e resposta ao candidato, por e-mail e no portal (TR 4.41.6). */
    public function responder(Request $request, int $id, int $inscricao)
    {
        $pss = Pss::findOrFail($id);
        $i = PssInscricao::where('pss_id', $pss->id)->findOrFail($inscricao);
        $d = $request->validate(['situacao' => ['required', 'in:' . implode(',', array_keys(PssInscricao::SITUACOES))], 'resposta' => ['nullable', 'string', 'max:5000']]);
        $i->update($d + ['respondida_em' => now(), 'respondida_por' => Auth::id()]);
        if ($i->email && filter_var($i->email, FILTER_VALIDATE_EMAIL)) {
            RastreioEmail::enviar(new RespostaInscricaoPss($i->fresh('pss')), $i->email, 'cidadao', "Inscrição {$i->numero}", 'pss_inscricao', $i->id, Auth::id());
        }

        return back()->with('success', "Inscrição {$i->numero}: " . PssInscricao::SITUACOES[$i->situacao] . '.');
    }

    public function anexo(int $id, int $anexo)
    {
        $a = \App\Models\Processo\PssAnexo::whereHas('inscricao', fn ($q) => $q->where('pss_id', $id))->findOrFail($anexo);

        return Storage::disk('documentos')->download($a->arquivo_path, $a->nome);
    }

    /** Inscrições em CSV com os campos da ficha (TR 4.41.7). */
    public function csv(Request $request, int $id)
    {
        $pss = Pss::with('tipoProcesso')->findOrFail($id);
        $campos = collect($pss->tipoProcesso?->schema_formulario ?? [])->filter(fn ($c) => ! empty($c['campo']))->pluck('label', 'campo')->all();
        $linhas = $this->consulta($pss, array_filter(['situacao' => $request->input('situacao'), 'cargo' => $request->input('cargo')]))->withCount('anexos')->orderBy('id')->get();

        return response()->streamDownload(function () use ($linhas, $campos) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Inscrição', 'Cargo', 'Nome', 'CPF', 'E-mail', 'Telefone', ...array_values($campos), 'Anexos', 'Situação', 'Resposta', 'Inscrito em'], ';');
            foreach ($linhas as $i) {
                $valores = array_map(function ($c) use ($i) {
                    $v = $i->dados[$c] ?? '';

                    return is_array($v) ? implode(', ', array_filter($v)) : (is_bool($v) ? ($v ? 'Sim' : 'Não') : (string) $v);
                }, array_keys($campos));
                fputcsv($out, [$i->numero, $i->cargo, $i->nome, $i->cpf, $i->email, $i->telefone, ...$valores, $i->anexos_count,
                    PssInscricao::SITUACOES[$i->situacao] ?? $i->situacao, $i->resposta, $i->created_at?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')], ';');
            }
            fclose($out);
        }, 'inscricoes-pss-' . $pss->id . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function consulta(Pss $pss, array $f)
    {
        return PssInscricao::where('pss_id', $pss->id)
            ->when($f['situacao'] ?? null, fn ($q, $v) => $q->where('situacao', $v))
            ->when($f['cargo'] ?? null, fn ($q, $v) => $q->where('cargo', $v))
            ->when($f['busca'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('nome', 'ilike', "%{$v}%")->orWhere('numero', 'ilike', "%{$v}%")
                ->orWhere('cpf', preg_replace('/\D/', '', $v) ?: '-')));
    }

    public static function edital(Pss $p): array
    {
        return [
            'id' => $p->id, 'titulo' => $p->titulo, 'edital_numero' => $p->edital_numero, 'descricao' => $p->descricao, 'requisitos' => $p->requisitos,
            'cargos' => $p->cargos ?? [], 'tipo_processo_id' => $p->tipo_processo_id, 'assunto' => $p->tipoProcesso?->nome,
            'inscricoes_inicio' => $p->inscricoes_inicio?->toIso8601String(), 'inscricoes_fim' => $p->inscricoes_fim?->toIso8601String(),
            'publicado' => $p->publicado, 'situacao' => $p->situacao(),
        ];
    }
}