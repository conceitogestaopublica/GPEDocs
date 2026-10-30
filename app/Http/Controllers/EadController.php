<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Ead;
use App\Tenant\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Treinamento EAD (TR 4.24, 4.25.2): meus treinamentos, sala de aula (vídeo com progresso, avaliação,
 * certificado), gestão dos cursos (aulas, questões, publicação) e o progresso de cada servidor.
 */
class EadController extends Controller
{
    public const MODULOS = ['geral' => 'Uso geral', 'ged' => 'GPE Docs (documentos)', 'fluxos' => 'Processos e tramitação', 'comunicacao' => 'Memorando, circular e ofício',
        'atendimento' => 'Atendimento, ouvidoria e e-SIC', 'assinatura' => 'Assinatura digital', 'atos' => 'Atos e Diário Oficial', 'gestao' => 'Gestão e relatórios'];

    // ---------------------------------------------------------------- servidor

    public function index(Request $request)
    {
        $u = Auth::user();
        $mats = DB::table('ead_matriculas')->where('user_id', $u->id)->get()->keyBy('curso_id');

        return Inertia::render('Ead/Index', [
            'cursos' => DB::table('ead_cursos')->where('publicado', true)->orderBy('titulo')->get()->map(fn ($c) => [
                'id' => $c->id, 'titulo' => $c->titulo, 'descricao' => $c->descricao, 'modulo' => $c->modulo, 'carga_horaria' => (float) $c->carga_horaria,
            ] + Ead::resumo($c, $mats[$c->id] ?? null)),
            'modulos' => self::MODULOS, 'podeGerir' => $this->pode(),
        ]);
    }

    public function curso(int $id)
    {
        $c = DB::table('ead_cursos')->find($id);
        abort_unless($c && ($c->publicado || $this->pode()), 404);
        $m = Ead::matricula($c->id, Auth::id());
        $aulas = Ead::aulas($c, $m);
        $liberaProva = (bool) $m->aulas_concluidas_em;

        return Inertia::render('Ead/Curso', [
            'curso' => ['id' => $c->id, 'titulo' => $c->titulo, 'descricao' => $c->descricao, 'carga_horaria' => (float) $c->carga_horaria, 'nota_minima' => $c->nota_minima,
                'ordem_obrigatoria' => (bool) $c->ordem_obrigatoria, 'percentual_minimo' => $c->percentual_minimo, 'publicado' => (bool) $c->publicado] + Ead::resumo($c, $m),
            'aulas' => $aulas,
            // as questões só vão para a tela depois de todas as aulas, e nunca com a resposta certa
            'questoes' => $liberaProva && ! $m->aprovado ? DB::table('ead_questoes')->where('curso_id', $c->id)->orderBy('ordem')->orderBy('id')->get(['id', 'enunciado', 'alternativas'])
                ->map(fn ($q) => ['id' => $q->id, 'enunciado' => $q->enunciado, 'alternativas' => json_decode($q->alternativas, true)]) : [],
            'tentativas' => DB::table('ead_tentativas')->where('matricula_id', $m->id)->orderByDesc('id')->get(['nota', 'aprovado', 'created_at'])
                ->map(fn ($t) => ['nota' => (float) $t->nota, 'aprovado' => (bool) $t->aprovado, 'em' => Carbon::parse($t->created_at, 'UTC')->toIso8601String()]),
            'tentativasRestantes' => Ead::tentativasRestantes($c, $m),
        ]);
    }

    public function progresso(Request $request, int $aulaId)
    {
        $d = $request->validate(['posicao' => ['required', 'integer', 'min:0'], 'assistido_ate' => ['required', 'integer', 'min:0'], 'duracao' => ['nullable', 'integer', 'min:1']]);
        $aula = DB::table('ead_aulas')->find($aulaId);
        abort_unless($aula, 404);
        $c = DB::table('ead_cursos')->find($aula->curso_id);
        $m = Ead::matricula($c->id, Auth::id());
        $aulas = collect(Ead::aulas($c, $m));
        abort_unless($aulas->firstWhere('id', $aula->id)['liberada'] ?? false, 403, 'Conclua a aula anterior primeiro.');

        return response()->json(Ead::progresso($m, $aula, $d['posicao'], $d['assistido_ate'], $d['duracao'] ?? null));
    }

    /** Vídeo da aula com suporte a Range (o navegador avança e volta sem baixar tudo). */
    public function video(int $aulaId)
    {
        $a = DB::table('ead_aulas')->find($aulaId);
        abort_unless($a && $a->video_tipo === 'arquivo' && $a->video_path, 404);
        $disco = Storage::disk('documentos');
        abort_unless($disco->exists($a->video_path), 404);
        if (config('filesystems.disks.documentos.driver') !== 'local') {
            return redirect($disco->temporaryUrl($a->video_path, now()->addHours(2)));
        }
        $r = new BinaryFileResponse($disco->path($a->video_path), 200, ['Content-Type' => $a->video_mime ?: 'video/mp4', 'Cache-Control' => 'private, max-age=3600']);
        $r->headers->set('Accept-Ranges', 'bytes');

        return $r;
    }

    public function avaliar(Request $request, int $id)
    {
        $c = DB::table('ead_cursos')->find($id);
        abort_unless($c, 404);
        $m = Ead::matricula($c->id, Auth::id());
        abort_unless($m->aulas_concluidas_em, 422, 'Assista a todas as aulas antes da avaliação.');
        abort_if($m->aprovado, 422, 'Você já foi aprovado neste curso.');
        abort_if(Ead::tentativasRestantes($c, $m) === 0, 422, 'Você usou todas as tentativas. Fale com o gestor do treinamento.');
        $r = Ead::avaliar($m, $c, (array) $request->input('respostas', []));

        return back()->with($r['aprovado'] ? 'success' : 'warning', $r['aprovado']
            ? "Aprovado com nota {$r['nota']} ({$r['acertos']} de {$r['total']}). O certificado já está disponível."
            : "Nota {$r['nota']} ({$r['acertos']} de {$r['total']}); a mínima é {$c->nota_minima}. Reveja as aulas e tente de novo.");
    }

    /** Certificado em PDF (TR 4.24.7): conteúdo, carga horária, nota e código de verificação. */
    public function certificado(string $codigo)
    {
        $m = DB::table('ead_matriculas')->where('certificado_codigo', $codigo)->first();
        abort_unless($m && $m->aprovado, 404);
        abort_unless($m->user_id === Auth::id() || $this->pode(), 403);

        // sem o 'Página N de M' dos relatórios: o certificado é uma página só
        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.ead-certificado', $this->dadosCertificado($m))->setPaper('A4', 'landscape')->stream('certificado-' . substr($codigo, 0, 8) . '.pdf');
    }

    /** Verificação pública do certificado (QR do PDF). */
    public function verificar(string $codigo)
    {
        $m = DB::table('ead_matriculas')->where('certificado_codigo', $codigo)->first();
        $d = $m && $m->aprovado ? $this->dadosCertificado($m) : null;

        return Inertia::render('Ead/Verificar', ['valido' => (bool) $d, 'codigo' => $codigo, 'certificado' => $d ? [
            'servidor' => $d['servidor'], 'curso' => $d['curso']->titulo, 'carga_horaria' => (float) $d['curso']->carga_horaria, 'nota' => (float) $m->nota,
            'concluido_em' => Carbon::parse($m->concluido_em, 'UTC')->setTimezone('America/Sao_Paulo')->format('d/m/Y'), 'orgao' => $d['ug']?->nome] : null]);
    }

    private function dadosCertificado(object $m): array
    {
        $u = DB::table('users')->find($m->user_id);
        $c = DB::table('ead_cursos')->find($m->curso_id);

        return ['m' => $m, 'curso' => $c, 'servidor' => $u->name, 'cpf' => $u->cpf ? substr($u->cpf, 0, 3) . '.***.***-' . substr(preg_replace('/\D/', '', $u->cpf), -2) : null,
            'aulas' => DB::table('ead_aulas')->where('curso_id', $c->id)->orderBy('ordem')->orderBy('id')->pluck('titulo'),
            'concluido' => Carbon::parse($m->concluido_em, 'UTC')->setTimezone('America/Sao_Paulo'),
            'url' => url("/ead/certificados/{$m->certificado_codigo}"), 'ug' => \App\Models\Ug::withoutGlobalScopes()->find($u->ug_id)];
    }

    // ---------------------------------------------------------------- gestão

    public function gestao()
    {
        $this->exigir();

        return Inertia::render('Ead/Gestao', [
            'cursos' => DB::table('ead_cursos')->orderByDesc('id')->get()->map(fn ($c) => ['id' => $c->id, 'titulo' => $c->titulo, 'modulo' => $c->modulo, 'publicado' => (bool) $c->publicado,
                'aulas' => DB::table('ead_aulas')->where('curso_id', $c->id)->count(), 'questoes' => DB::table('ead_questoes')->where('curso_id', $c->id)->count(),
                'matriculados' => DB::table('ead_matriculas')->where('curso_id', $c->id)->count(),
                'concluidos' => DB::table('ead_matriculas')->where('curso_id', $c->id)->where('aprovado', true)->count()]),
            'modulos' => self::MODULOS,
        ]);
    }

    public function editar(int $id)
    {
        $this->exigir();
        $c = DB::table('ead_cursos')->find($id);
        abort_unless($c, 404);

        return Inertia::render('Ead/Editar', [
            'curso' => (array) $c + ['carga_horaria' => (float) $c->carga_horaria],
            'aulas' => DB::table('ead_aulas')->where('curso_id', $id)->orderBy('ordem')->orderBy('id')->get(),
            'questoes' => DB::table('ead_questoes')->where('curso_id', $id)->orderBy('ordem')->orderBy('id')->get()->map(fn ($q) => (array) $q + ['alternativas' => json_decode($q->alternativas, true)]),
            'modulos' => self::MODULOS, 'videoMaxMb' => config('services.ead.video_max_mb'),
        ]);
    }

    public function salvarCurso(Request $request, ?int $id = null)
    {
        $this->exigir();
        $d = $request->validate(['titulo' => ['required', 'string', 'max:200'], 'descricao' => ['nullable', 'string', 'max:5000'], 'modulo' => ['nullable', 'in:' . implode(',', array_keys(self::MODULOS))],
            'carga_horaria' => ['required', 'numeric', 'min:0.5', 'max:999'], 'ordem_obrigatoria' => ['boolean'], 'percentual_minimo' => ['required', 'integer', 'between:10,100'],
            'nota_minima' => ['required', 'integer', 'between:0,100'], 'tentativas' => ['nullable', 'integer', 'between:1,20'], 'publicado' => ['boolean']]);
        $d += ['ordem_obrigatoria' => $request->boolean('ordem_obrigatoria'), 'publicado' => $request->boolean('publicado')];
        if ($d['publicado'] && $id && ! DB::table('ead_aulas')->where('curso_id', $id)->exists()) {
            return back()->with('error', 'Inclua ao menos uma aula antes de publicar.');
        }
        if ($id) {
            DB::table('ead_cursos')->where('id', $id)->update($d + ['updated_at' => now()]);

            return back()->with('success', 'Curso salvo.');
        }
        $novo = DB::table('ead_cursos')->insertGetId($d + ['publicado' => false, 'criado_por' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);

        return redirect("/ead/gestao/{$novo}")->with('success', 'Curso criado. Agora inclua as aulas e as questões.');
    }

    public function salvarAula(Request $request, int $cursoId, ?int $id = null)
    {
        $this->exigir();
        $d = $request->validate(['titulo' => ['required', 'string', 'max:200'], 'descricao' => ['nullable', 'string', 'max:3000'], 'ordem' => ['required', 'integer', 'min:1'],
            'video_tipo' => ['required', 'in:arquivo,youtube'], 'video_url' => ['nullable', 'required_if:video_tipo,youtube', 'url', 'max:300'],
            'video' => [$id ? 'nullable' : 'required_if:video_tipo,arquivo', 'file', 'mimetypes:video/mp4,video/webm,video/ogg', 'max:' . (config('services.ead.video_max_mb') * 1024)]]);
        $l = ['curso_id' => $cursoId, 'titulo' => $d['titulo'], 'descricao' => $d['descricao'] ?? null, 'ordem' => $d['ordem'], 'video_tipo' => $d['video_tipo'],
            'video_url' => $d['video_tipo'] === 'youtube' ? $d['video_url'] : null, 'updated_at' => now()];
        if ($request->hasFile('video')) {
            $l += ['video_path' => $request->file('video')->store(TenantStorage::pasta('ead'), 'documentos'), 'video_mime' => $request->file('video')->getMimeType(), 'duracao_seg' => null];
        }
        $id ? DB::table('ead_aulas')->where('id', $id)->where('curso_id', $cursoId)->update($l) : DB::table('ead_aulas')->insert($l + ['created_at' => now()]);

        return back()->with('success', 'Aula salva.');
    }

    public function excluirAula(int $cursoId, int $id)
    {
        $this->exigir();
        DB::table('ead_aulas')->where('id', $id)->where('curso_id', $cursoId)->delete();

        return back()->with('success', 'Aula excluída.');
    }

    public function salvarQuestao(Request $request, int $cursoId, ?int $id = null)
    {
        $this->exigir();
        $d = $request->validate(['enunciado' => ['required', 'string', 'max:2000'], 'alternativas' => ['required', 'array', 'min:2', 'max:6'], 'alternativas.*' => ['required', 'string', 'max:500'],
            'correta' => ['required', 'integer', 'min:0'], 'peso' => ['required', 'integer', 'between:1,10'], 'ordem' => ['required', 'integer', 'min:1']]);
        abort_unless($d['correta'] < count($d['alternativas']), 422, 'Marque a alternativa correta.');
        $l = ['curso_id' => $cursoId, 'enunciado' => $d['enunciado'], 'alternativas' => json_encode(array_values($d['alternativas']), JSON_UNESCAPED_UNICODE),
            'correta' => $d['correta'], 'peso' => $d['peso'], 'ordem' => $d['ordem'], 'updated_at' => now()];
        $id ? DB::table('ead_questoes')->where('id', $id)->where('curso_id', $cursoId)->update($l) : DB::table('ead_questoes')->insert($l + ['created_at' => now()]);

        return back()->with('success', 'Questão salva.');
    }

    public function excluirQuestao(int $cursoId, int $id)
    {
        $this->exigir();
        DB::table('ead_questoes')->where('id', $id)->where('curso_id', $cursoId)->delete();

        return back()->with('success', 'Questão excluída.');
    }

    /** Progresso de cada servidor (TR 4.24.8, 4.25.2), com CSV. */
    public function relatorio(Request $request)
    {
        $this->exigir();
        $cursoId = $request->integer('curso') ?: DB::table('ead_cursos')->where('publicado', true)->orderBy('titulo')->value('id');
        $linhas = $cursoId ? $this->linhasRelatorio($cursoId, $request->integer('unidade_id') ?: null) : [];
        if ($request->input('formato') === 'csv') {
            return response()->streamDownload(function () use ($linhas) {
                $o = fopen('php://output', 'w');
                fwrite($o, "\xEF\xBB\xBF");
                fputcsv($o, ['Servidor', 'E-mail', 'Setor', 'Situação', 'Aulas concluídas', '% das aulas', 'Nota', 'Tentativas', 'Iniciado em', 'Concluído em'], ';');
                foreach ($linhas as $l) {
                    fputcsv($o, [$l['servidor'], $l['email'], $l['setor'], $l['situacao_rotulo'], "{$l['aulas_concluidas']}/{$l['aulas']}", $l['percentual'], $l['nota'], $l['tentativas'], $l['iniciado'], $l['concluido']], ';');
                }
                fclose($o);
            }, "ead-progresso-{$cursoId}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
        $col = collect($linhas);

        return Inertia::render('Ead/Relatorio', [
            'cursos' => DB::table('ead_cursos')->orderBy('titulo')->get(['id', 'titulo', 'publicado']), 'cursoId' => $cursoId,
            'setores' => DB::table('ug_organograma')->where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'sigla']), 'unidadeId' => $request->integer('unidade_id') ?: null,
            'linhas' => $linhas,
            'resumo' => ['matriculados' => $col->count(), 'concluidos' => $col->where('situacao', 'concluido')->count(), 'em_andamento' => $col->whereIn('situacao', ['em_andamento', 'avaliacao'])->count(),
                'media_nota' => $col->whereNotNull('nota')->avg('nota'), 'media_percentual' => $col->avg('percentual')],
        ]);
    }

    private function linhasRelatorio(int $cursoId, ?int $unidade): array
    {
        $c = DB::table('ead_cursos')->find($cursoId);
        $rot = ['nao_iniciado' => 'Não iniciado', 'em_andamento' => 'Assistindo', 'avaliacao' => 'Falta a avaliação', 'concluido' => 'Concluído'];
        $q = DB::table('ead_matriculas as m')->join('users as u', 'u.id', '=', 'm.user_id')->leftJoin('ug_organograma as s', 's.id', '=', 'u.unidade_id')->where('m.curso_id', $cursoId);
        if ($unidade) {
            $q->whereIn('u.unidade_id', \App\Support\Organograma::comDescendentes($unidade));
        }
        $h = fn ($v) => $v ? Carbon::parse($v, 'UTC')->setTimezone('America/Sao_Paulo')->format('d/m/Y') : '';

        return $q->orderBy('u.name')->get(['m.*', 'u.name', 'u.email', 's.nome as setor', 's.sigla'])->map(function ($m) use ($c, $rot, $h) {
            $r = Ead::resumo($c, $m);

            return ['servidor' => $m->name, 'email' => $m->email, 'setor' => $m->sigla ?: $m->setor, 'situacao' => $r['situacao'], 'situacao_rotulo' => $rot[$r['situacao']],
                'aulas' => $r['aulas'], 'aulas_concluidas' => $r['aulas_concluidas'], 'percentual' => $r['percentual'], 'nota' => $r['nota'],
                'tentativas' => DB::table('ead_tentativas')->where('matricula_id', $m->id)->count(), 'iniciado' => $h($m->iniciado_em), 'concluido' => $h($m->concluido_em),
                'certificado' => $r['certificado']];
        })->all();
    }

    private function pode(): bool
    {
        return Auth::user()->super_admin || Auth::user()->can('ead.gerir');
    }

    private function exigir(): void
    {
        abort_unless($this->pode(), 403);
    }
}
