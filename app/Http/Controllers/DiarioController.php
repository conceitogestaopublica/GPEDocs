<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Diario\Edicao;
use App\Models\Diario\Estrutura;
use App\Models\Diario\Materia;
use App\Models\Diario\Responsavel;
use App\Models\Processo\Ato;
use App\Models\User;
use App\Support\Atos;
use App\Support\DiarioOficial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Diário Oficial no GED (TR 4.43): painel, estrutura (cadernos, seções, subseções), responsáveis,
 * atos recebidos e montagem das edições pendentes.
 */
class DiarioController extends Controller
{
    public function painel(Request $request)
    {
        $this->exigirGestor();
        $aba = in_array($request->input('aba'), ['pendentes', 'publicados'], true) ? $request->input('aba') : 'pendentes';
        $caminhos = DiarioOficial::caminhos();
        $recebidos = Materia::with(['ato.tipo', 'edicao', 'enviadaPor:id,name'])
            ->when($aba === 'pendentes', fn ($q) => $q->where(fn ($w) => $w->whereNull('edicao_id')->orWhereHas('edicao', fn ($e) => $e->where('status', 'pendente'))))
            ->when($aba === 'publicados', fn ($q) => $q->whereHas('edicao', fn ($e) => $e->where('status', 'publicada')))
            ->orderByDesc('id')->paginate(20)->withQueryString()
            ->through(fn (Materia $m) => [
                'id' => $m->id, 'ato' => ['id' => $m->ato?->id, 'identificacao' => $m->ato?->identificacao(), 'ementa' => $m->ato?->ementa],
                'grupo' => $caminhos[$m->estrutura_id] ?? 'Sem seção', 'estrutura_id' => $m->estrutura_id,
                'edicao' => $m->edicao ? ['id' => $m->edicao->id, 'titulo' => $m->edicao->titulo(), 'status' => $m->edicao->status] : null,
                'enviada_por' => $m->enviadaPor?->name, 'enviada_em' => $m->created_at?->toIso8601String(),
            ]);

        return Inertia::render('GED/Diario/Painel', [
            'aba' => $aba, 'recebidos' => $recebidos,
            'contagem' => [
                'atos_pendentes' => Materia::whereNull('edicao_id')->count(),
                'edicoes_pendentes' => Edicao::where('status', 'pendente')->count(),
                'edicoes_publicadas' => Edicao::where('status', 'publicada')->count(),
                'atos_publicados' => Materia::whereHas('edicao', fn ($e) => $e->where('status', 'publicada'))->count(),
                'responsaveis' => Responsavel::where('ativo', true)->count(),
            ],
            'pendentes' => Edicao::where('status', 'pendente')->withCount('materias')->orderBy('data')->get()
                ->map(fn (Edicao $e) => ['id' => $e->id, 'titulo' => $e->titulo(), 'data' => $e->data->format('Y-m-d'), 'tipo' => $e->tipo, 'atos' => $e->materias_count, 'automatica' => $e->automatica]),
            'ultimas' => Edicao::where('status', 'publicada')->withCount('materias')->with('publicadaPor:id,name')->orderByDesc('publicada_em')->limit(10)->get()
                ->map(fn (Edicao $e) => ['id' => $e->id, 'titulo' => $e->titulo(), 'tipo' => $e->tipo, 'data' => $e->data->format('Y-m-d'), 'atos' => $e->materias_count,
                    'paginas' => $e->paginas, 'publicada_por' => $e->publicadaPor?->name, 'publicada_em' => $e->publicada_em?->toIso8601String()]),
            'estrutura' => $this->estruturaPlana(),
            'pode_publicar' => DiarioOficial::podePublicar(Auth::user()),
        ]);
    }

    // ── Estrutura e responsáveis ─────────────────────────────────────

    public function configuracao()
    {
        $this->exigirGestor();

        return Inertia::render('GED/Diario/Configuracao', [
            'estrutura' => $this->estruturaPlana(true),
            'responsaveis' => Responsavel::with('user:id,name,email')->orderByDesc('ativo')->get()
                ->map(fn (Responsavel $r) => ['id' => $r->id, 'user_id' => $r->user_id, 'nome' => $r->user?->name, 'funcao' => $r->funcao,
                    'telefone' => $r->telefone, 'email' => $r->email ?: $r->user?->email, 'ativo' => $r->ativo]),
            'usuarios' => User::where('tipo', 'interno')->whereNull('suspenso_em')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function salvarEstrutura(Request $request, ?int $id = null)
    {
        $this->exigirGestor();
        $d = $request->validate(['nome' => ['required', 'string', 'max:150'], 'parent_id' => ['nullable', 'integer', 'exists:dof_estrutura,id'],
            'ordem' => ['nullable', 'integer', 'min:0'], 'ativo' => ['nullable', 'boolean']]);
        $pai = ! empty($d['parent_id']) ? Estrutura::find($d['parent_id']) : null;
        abort_if($pai && $pai->nivel >= 3, 422, 'Subseção não tem nível abaixo.');
        $dados = ['nome' => $d['nome'], 'parent_id' => $pai?->id, 'nivel' => $pai ? $pai->nivel + 1 : 1, 'ordem' => (int) ($d['ordem'] ?? 0), 'ativo' => $request->boolean('ativo', true)];
        $id ? Estrutura::findOrFail($id)->update($dados) : Estrutura::create($dados);

        return back()->with('success', 'Estrutura salva.');
    }

    public function excluirEstrutura(int $id)
    {
        $this->exigirGestor();
        $e = Estrutura::findOrFail($id);
        abort_if(Materia::whereIn('estrutura_id', $this->descendentes($e->id))->exists(), 422, 'Há atos nesta parte do diário: inative em vez de excluir.');
        $e->delete();

        return back()->with('success', 'Excluído da estrutura.');
    }

    public function salvarResponsavel(Request $request, ?int $id = null)
    {
        $this->exigirGestor();
        $d = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'funcao' => ['nullable', 'string', 'max:100'],
            'telefone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:150'], 'ativo' => ['nullable', 'boolean']]);
        $d['ativo'] = $request->boolean('ativo', true);
        $id ? Responsavel::findOrFail($id)->update($d) : Responsavel::updateOrCreate(['user_id' => $d['user_id']], $d);

        return back()->with('success', 'Responsável salvo.');
    }

    // ── Envio de atos e matérias ─────────────────────────────────────

    /** Envia ato assinado ao diário, com ou sem edição definida (TR 4.43.6, 4.43.7). */
    public function enviar(Request $request)
    {
        $d = $request->validate(['ato_id' => ['required', 'integer', 'exists:proc_atos,id'], 'estrutura_id' => ['nullable', 'integer', 'exists:dof_estrutura,id'],
            'edicao_id' => ['nullable', 'integer', 'exists:dof_edicoes,id']]);
        $ato = Ato::with('tipo')->findOrFail($d['ato_id']);
        abort_unless(Atos::podeElaborar(Auth::user(), $ato->tipo) || DiarioOficial::podeGerir(Auth::user()), 403);
        DiarioOficial::enviar($ato, ! empty($d['estrutura_id']) ? (int) $d['estrutura_id'] : null, ! empty($d['edicao_id']) ? (int) $d['edicao_id'] : null, Auth::user());

        return back()->with('success', "{$ato->identificacao()} enviado ao Diário Oficial.");
    }

    /** Muda seção ou edição de uma matéria ainda não publicada. */
    public function atualizarMateria(Request $request, int $id)
    {
        $this->exigirGestor();
        $m = Materia::with('edicao')->findOrFail($id);
        abort_if($m->edicao?->status === 'publicada', 422, 'Ato já publicado: a edição não muda mais.');
        $d = $request->validate(['estrutura_id' => ['nullable', 'integer', 'exists:dof_estrutura,id'], 'edicao_id' => ['nullable', 'integer', 'exists:dof_edicoes,id']]);
        if (array_key_exists('edicao_id', $d) && $d['edicao_id']) {
            abort_if(Edicao::whereKey($d['edicao_id'])->value('status') !== 'pendente', 422);
            $d['ordem'] = ((int) Materia::where('edicao_id', $d['edicao_id'])->max('ordem')) + 1;
        }
        $m->update($d);

        return back()->with('success', 'Ato atualizado no diário.');
    }

    /** Tira o ato da edição (volta a pendente) ou do diário. */
    public function retirarMateria(Request $request, int $id)
    {
        $this->exigirGestor();
        $m = Materia::with('edicao')->findOrFail($id);
        abort_if($m->edicao?->status === 'publicada', 422, 'Ato já publicado no diário.');
        $request->boolean('do_diario') ? $m->delete() : $m->update(['edicao_id' => null, 'ordem' => 0]);

        return back()->with('success', $request->boolean('do_diario') ? 'Ato retirado do Diário Oficial.' : 'Ato voltou para os pendentes.');
    }

    // ── Edições ──────────────────────────────────────────────────────

    public function criarEdicao(Request $request)
    {
        $this->exigirGestor();
        $d = $request->validate(['data' => ['required', 'date'], 'incluir_pendentes' => ['nullable', 'boolean']]);
        $e = DiarioOficial::criarEdicao((int) session('ug_id', Auth::user()->ug_id), substr($d['data'], 0, 10), Auth::user());
        if ($request->boolean('incluir_pendentes')) {
            DiarioOficial::incluirPendentes($e);
        }

        return redirect("/diario/edicoes/{$e->id}")->with('success', 'Edição ' . (Edicao::TIPOS[$e->tipo]) . ' criada.');
    }

    public function edicao(int $id)
    {
        $this->exigirGestor();
        $e = Edicao::with('publicadaPor:id,name')->findOrFail($id);
        $caminhos = DiarioOficial::caminhos();

        return Inertia::render('GED/Diario/Edicao', [
            'edicao' => ['id' => $e->id, 'titulo' => $e->titulo(), 'numero' => $e->numero, 'data' => $e->data->format('Y-m-d'), 'tipo' => $e->tipo,
                'status' => $e->status, 'automatica' => $e->automatica, 'paginas' => $e->paginas, 'assinada' => $e->assinada, 'certificado' => $e->certificado_cn,
                'publicada_por' => $e->publicadaPor?->name, 'publicada_em' => $e->publicada_em?->toIso8601String(), 'hash' => $e->hash_sha256,
                'verificar_url' => url("/diario/verificar/{$e->qr_code_token}")],
            'sumario' => DiarioOficial::sumario($e),
            'disponiveis' => $e->status === 'pendente' ? Materia::with('ato.tipo')->whereNull('edicao_id')->orderBy('id')->get()
                ->map(fn (Materia $m) => ['id' => $m->id, 'identificacao' => $m->ato?->identificacao(), 'ementa' => $m->ato?->ementa, 'grupo' => $caminhos[$m->estrutura_id] ?? 'Sem seção']) : [],
            'estrutura' => $this->estruturaPlana(),
            'pode_publicar' => DiarioOficial::podePublicar(Auth::user()) && $e->status === 'pendente',
        ]);
    }

    public function atualizarEdicao(Request $request, int $id)
    {
        $this->exigirGestor();
        $e = Edicao::findOrFail($id);
        abort_unless($e->status === 'pendente', 422, 'Edição publicada não muda (TR 4.43.29).');
        $d = $request->validate(['data' => ['nullable', 'date'], 'ordem' => ['nullable', 'array'], 'ordem.*' => ['integer'], 'incluir' => ['nullable', 'array'],
            'incluir.*' => ['integer'], 'reordenar_estrutura' => ['nullable', 'boolean']]);
        DB::transaction(function () use ($e, $d, $request) {
            if (! empty($d['data'])) {
                $data = substr($d['data'], 0, 10);
                $e->update(['data' => $data, 'tipo' => DiarioOficial::tipoParaData($e->ug_id, $data, $e->id)]);
            }
            if (! empty($d['incluir'])) {
                $max = (int) Materia::where('edicao_id', $e->id)->max('ordem');
                foreach (Materia::whereIn('id', $d['incluir'])->whereNull('edicao_id')->orderBy('id')->get() as $m) {
                    $m->update(['edicao_id' => $e->id, 'ordem' => ++$max]);
                }
            }
            foreach (array_values($d['ordem'] ?? []) as $i => $materiaId) {
                Materia::where('edicao_id', $e->id)->whereKey($materiaId)->update(['ordem' => $i + 1]);
            }
            if ($request->boolean('reordenar_estrutura')) {
                DiarioOficial::reordenarPelaEstrutura($e);
            }
        });

        return back()->with('success', 'Edição atualizada.');
    }

    public function excluirEdicao(int $id)
    {
        $this->exigirGestor();
        $e = Edicao::findOrFail($id);
        abort_unless($e->status === 'pendente', 422, 'Edição publicada não pode ser excluída (TR 4.43.29).');
        DB::transaction(function () use ($e) {
            Materia::where('edicao_id', $e->id)->update(['edicao_id' => null, 'ordem' => 0]);
            $e->delete();
        });

        return redirect('/diario')->with('success', 'Edição excluída; os atos voltaram para os pendentes.');
    }

    /** Publicação irreversível: número, PDF com capa e sumário, assinatura com o e-CNPJ (TR 4.43.29, 4.43.31). */
    public function publicar(int $id)
    {
        abort_unless(DiarioOficial::podePublicar(Auth::user()), 403, 'Só os responsáveis pelo diário publicam.');
        $e = \App\Support\PublicacaoDiario::publicar(Edicao::findOrFail($id), Auth::user());

        return back()->with('success', "Edição nº {$e->numero} publicada" . ($e->assinada ? ' e assinada com o certificado da prefeitura.' : '. Sem certificado da prefeitura cadastrado: o PDF saiu sem assinatura.'));
    }

    /** Versão certificada (o PDF assinado) da edição publicada. */
    public function pdf(int $id)
    {
        $this->exigirGestor();

        return self::respostaPdf(Edicao::findOrFail($id));
    }

    public static function respostaPdf(Edicao $e)
    {
        abort_unless($e->status === 'publicada' && $e->pdf_path && \Illuminate\Support\Facades\Storage::disk('documentos')->exists($e->pdf_path), 404);

        return response(\Illuminate\Support\Facades\Storage::disk('documentos')->get($e->pdf_path), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="diario-oficial-edicao-' . $e->numero . '-' . $e->data->format('Y-m-d') . '.pdf"']);
    }

    /** Verificação pública da edição (QR/rodapé do PDF). */
    public function verificar(string $token)
    {
        $e = \Illuminate\Support\Str::isUuid($token) ? Edicao::withoutGlobalScopes()->where('qr_code_token', $token)->where('status', 'publicada')->first() : null;
        \App\Support\Verificacoes::registrar(request(), $token, 'web', (bool) $e, 'diario', $e?->id);
        if (! $e) {
            return Inertia::render('GED/VerificarComunicacao', ['valido' => false, 'tipo' => 'Edição do Diário Oficial']);
        }

        return Inertia::render('GED/VerificarComunicacao', ['valido' => true, 'tipo' => 'Edição do Diário Oficial', 'comunicacao' => array_filter([
            'numero' => "Edição nº {$e->numero} — " . (Edicao::TIPOS[$e->tipo] ?? ''), 'assunto' => Materia::withoutGlobalScopes()->where('edicao_id', $e->id)->count() . ' ato(s) · ' . $e->paginas . ' página(s)',
            'orgao' => \App\Models\Ug::withoutGlobalScopes()->find($e->ug_id)?->nome, 'emitido_em' => $e->publicada_em?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            'situacao' => $e->assinada ? "Assinada por {$e->certificado_cn} · SHA-256 {$e->hash_sha256}" : "SHA-256 {$e->hash_sha256}",
        ])]);
    }

    /** @return list<array{id:int, nome:string, nivel:int, parent_id:?int, caminho:string, ordem:int, ativo:bool}> */
    private function estruturaPlana(bool $inativos = false): array
    {
        $caminhos = DiarioOficial::caminhos();
        $ordem = DiarioOficial::ordemDaEstrutura();

        return Estrutura::when(! $inativos, fn ($q) => $q->where('ativo', true))->get()
            ->sortBy(fn ($n) => $ordem[$n->id] ?? PHP_INT_MAX)->values()
            ->map(fn (Estrutura $n) => ['id' => $n->id, 'nome' => $n->nome, 'nivel' => $n->nivel, 'parent_id' => $n->parent_id,
                'caminho' => $caminhos[$n->id] ?? $n->nome, 'ordem' => $n->ordem, 'ativo' => $n->ativo])->all();
    }

    /** @return list<int> */
    private function descendentes(int $id): array
    {
        $ids = [$id];
        $fila = [$id];
        while ($fila) {
            $filhos = Estrutura::whereIn('parent_id', $fila)->pluck('id')->all();
            $ids = [...$ids, ...$filhos];
            $fila = $filhos;
        }

        return $ids;
    }

    private function exigirGestor(): void
    {
        abort_unless(DiarioOficial::podeGerir(Auth::user()), 403);
    }
}
