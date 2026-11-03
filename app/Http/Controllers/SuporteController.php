<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CentralDemandas\CentralDemandasClient;
use App\Support\HtmlSeguro;
use App\Support\Suporte;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Suporte na plataforma (TR 4.26.1–10). JSON para a aba Suporte do chat flutuante; telas da Central de
 * suporte (fila do atendente e histórico do usuário por situação), do atendimento, da central de ajuda e
 * da configuração; e a página pública do e-mail de resolução (avaliar ou reabrir).
 */
class SuporteController extends Controller
{
    // ---------------------------------------------------------------- chat flutuante (JSON)

    /** Atendentes on-line, assuntos e os atendimentos do usuário com mensagens não lidas. */
    public function painel()
    {
        $u = Auth::user();
        $this->presenca($u);
        $meus = $this->doUsuario($u->id)->limit(30)->get()->map(fn ($a) => $this->resumoLista($a, $u->id));

        return response()->json([
            'atendentes' => Suporte::atendentesDisponiveis(),
            'assuntos' => DB::table('sup_assuntos')->where('ativo', true)->orderBy('ordem')->orderBy('nome')->get(['id', 'nome', 'descricao']),
            'atendimentos' => $meus, 'nao_lidas' => $meus->sum('nao_lidas'), 'eh_atendente' => Suporte::ehAtendente($u),
            'fila' => Suporte::ehAtendente($u) ? DB::table('sup_atendimentos')->where('status', 'aberto')->where('via_central', false)->count() : 0,
            'central' => CentralDemandasClient::ativa(),
        ]);
    }

    /** Autoatendimento (4.26.3): artigos publicados do assunto (ou da busca). */
    public function artigos(Request $request)
    {
        $q = DB::table('sup_artigos')->where('publicado', true);
        if ($request->integer('assunto')) {
            $q->where('assunto_id', $request->integer('assunto'));
        }
        if ($b = trim((string) $request->input('q'))) {
            $q->where(fn ($w) => $w->where('titulo', 'ilike', "%{$b}%")->orWhere('resumo', 'ilike', "%{$b}%")->orWhere('conteudo', 'ilike', "%{$b}%"));
        }

        return response()->json($q->orderByDesc('resolveu')->orderBy('titulo')->limit(20)->get(['id', 'assunto_id', 'titulo', 'resumo', 'conteudo']));
    }

    public function artigoVisto(int $id)
    {
        DB::table('sup_artigos')->where('id', $id)->increment('visualizacoes');

        return response()->json(['ok' => true]);
    }

    public function artigoRetorno(Request $request, int $id)
    {
        DB::table('sup_artigos')->where('id', $id)->increment($request->boolean('resolveu') ? 'resolveu' : 'nao_resolveu');

        return response()->json(['ok' => true]);
    }

    public function abrir(Request $request)
    {
        $d = $request->validate(['titulo' => ['required', 'string', 'max:200'], 'descricao' => ['required', 'string', 'max:10000'],
            'assunto_id' => ['nullable', 'integer', 'exists:sup_assuntos,id'],
            // onde o servidor estava ao abrir o chat (vai para a Central de Demandas)
            'modulo' => ['nullable', 'string', 'in:ged,gepsp,configuracoes'], 'submodulo' => ['nullable', 'string', 'max:120'],
            'tela' => ['nullable', 'string', 'max:200'], 'url_tela' => ['nullable', 'string', 'max:500']]);
        $contexto = array_intersect_key($d, array_flip(['modulo', 'submodulo', 'tela', 'url_tela'])) + ['ug_id' => session('ug_id') ? (int) session('ug_id') : null];
        $a = Suporte::abrir(Auth::user(), $d['titulo'], $d['descricao'], $d['assunto_id'] ?? null, 'chat', $contexto);

        return response()->json(['atendimento' => $this->resumoLista($a, Auth::id())]);
    }

    /** Retomar pelo número (4.26.7). */
    public function buscar(Request $request)
    {
        $numero = mb_strtoupper(trim((string) $request->input('numero')));
        if (preg_match('#^(\d{1,6})/?(\d{4})?$#', $numero, $m)) {
            $numero = 'SUP-' . ($m[2] ?? now()->year) . '/' . str_pad($m[1], 6, '0', STR_PAD_LEFT);
        }
        $a = DB::table('sup_atendimentos')->where('numero', $numero)->first();
        abort_unless($a && Suporte::podeVer(Auth::user(), $a), 404, 'Atendimento não encontrado.');

        return response()->json(['atendimento' => $this->resumoLista($a, Auth::id())]);
    }

    public function mensagens(Request $request, int $id)
    {
        $u = Auth::user();
        $a = $this->atendimento($id);
        $q = DB::table('sup_mensagens as m')->leftJoin('users as us', 'us.id', '=', 'm.autor_id')->where('m.atendimento_id', $a->id)
            ->when(! Suporte::ehAtendente($u), fn ($w) => $w->where('m.tipo', '<>', 'nota'))
            ->when($request->integer('apos'), fn ($w) => $w->where('m.id', '>', $request->integer('apos')))
            ->orderBy('m.id')->limit(500);
        $msgs = $q->get(['m.id', 'm.autor_id', 'm.tipo', 'm.conteudo', 'm.created_at', DB::raw('coalesce(m.autor_externo, us.name) as autor'),
            'm.autor_externo', 'm.automatica', 'm.responder_ate', 'm.retorno'])
            ->map(fn ($m) => array_merge((array) $m, ['em' => Carbon::parse($m->created_at, 'UTC')->toIso8601String(), 'minha' => (int) $m->autor_id === $u->id,
                'externa' => (bool) $m->autor_externo, 'automatica' => (bool) $m->automatica,
                'responder_ate' => $m->responder_ate ? Carbon::parse($m->responder_ate, 'UTC')->toIso8601String() : null,
                // botões Resolveu / Não resolveu: só para o solicitante, até responder ou vencer o prazo
                'pergunta_aberta' => $m->automatica && ! $m->retorno && (int) $a->solicitante_id === $u->id
                    && (! $m->responder_ate || Carbon::parse($m->responder_ate, 'UTC')->isFuture())]));
        if ($msgs->isNotEmpty()) {
            DB::table('sup_participantes')->where('atendimento_id', $a->id)->where('user_id', $u->id)->where('lido_ate', '<', $msgs->max('id'))->update(['lido_ate' => $msgs->max('id')]);
        }

        return response()->json(['mensagens' => $msgs, 'atendimento' => $this->resumoLista(DB::table('sup_atendimentos')->find($a->id), $u->id)]);
    }

    public function enviar(Request $request, int $id)
    {
        $d = $request->validate(['conteudo' => ['required', 'string', 'max:10000'], 'nota' => ['boolean']]);
        $a = $this->atendimento($id);
        abort_if($a->status === 'cancelado', 422, 'Atendimento cancelado.');
        $mid = Suporte::responder($a, Auth::user(), $d['conteudo'], $request->boolean('nota'));

        return response()->json(['id' => $mid]);
    }

    /** "Isso resolveu?" da resposta automática da Central de Demandas. */
    public function retornoAutomatico(Request $request, int $id, int $mensagem)
    {
        $d = $request->validate(['resolveu' => ['required', 'boolean'], 'comentario' => ['nullable', 'string', 'max:2000']]);
        $a = $this->atendimento($id);
        abort_unless((int) $a->solicitante_id === Auth::id(), 403);
        $m = DB::table('sup_mensagens')->where('atendimento_id', $a->id)->where('id', $mensagem)->where('automatica', true)->first();
        abort_unless($m, 404);
        abort_if($m->retorno, 422, 'Você já respondeu.');
        abort_if($m->responder_ate && Carbon::parse($m->responder_ate, 'UTC')->isPast(), 422, 'O prazo para responder terminou: a equipe Conceito já está com o pedido.');
        Suporte::retornoAutomatico($a, Auth::user(), $m, $request->boolean('resolveu'), $d['comentario'] ?? null);

        return response()->json(['ok' => true]);
    }

    // ---------------------------------------------------------------- telas

    /** Central de suporte: fila (atendente) e histórico de todos os atendimentos por situação (4.26.10). */
    public function index(Request $request)
    {
        $u = Auth::user();
        $atendente = Suporte::ehAtendente($u);
        $this->presenca($u);
        $status = array_key_exists($request->input('status'), Suporte::STATUS) ? $request->input('status') : null;
        $escopo = $atendente && $request->input('escopo') !== 'meus' ? 'todos' : 'meus';
        // os atendimentos da Central de Demandas não entram na fila dos atendentes internos
        $q = $escopo === 'meus' ? $this->doUsuario($u->id) : DB::table('sup_atendimentos as a')->where('a.via_central', false)->orderByDesc('a.updated_at');
        if ($status) {
            $q->where('a.status', $status);
        }
        if ($request->integer('nivel')) {
            $q->where('a.nivel', $request->integer('nivel'));
        }
        if ($b = trim((string) $request->input('busca'))) {
            $q->where(fn ($w) => $w->where('a.numero', 'ilike', "%{$b}%")->orWhere('a.titulo', 'ilike', "%{$b}%"));
        }
        $contagem = ($escopo === 'meus' ? $this->doUsuario($u->id) : DB::table('sup_atendimentos as a')->where('a.via_central', false))->reorder()
            ->groupBy('a.status')->selectRaw('a.status as k, count(*) as v')->pluck('v', 'k');

        return Inertia::render('Suporte/Index', [
            'atendimentos' => $q->limit(300)->get()->map(fn ($a) => $this->resumoLista($a, $u->id)),
            'contagem' => $contagem, 'filtros' => ['status' => $status, 'escopo' => $escopo, 'nivel' => $request->integer('nivel') ?: null, 'busca' => $b ?? ''],
            'ehAtendente' => $atendente, 'statusRotulos' => Suporte::STATUS, 'niveis' => Suporte::NIVEIS,
            'atendentes' => Suporte::atendentesDisponiveis(),
            'eu' => $atendente ? DB::table('sup_atendentes')->where('user_id', $u->id)->first(['nivel', 'disponivel']) : null,
        ]);
    }

    public function show(int $id)
    {
        $u = Auth::user();
        $a = $this->atendimento($id);
        $this->presenca($u);

        return Inertia::render('Suporte/Atendimento', [
            'atendimento' => $this->resumoLista($a, $u->id) + ['resumo' => Suporte::resumo($a), 'avaliacao_comentario' => $a->avaliacao_comentario,
                'processo' => $a->processo_id ? DB::table('proc_processos')->where('id', $a->processo_id)->first(['id', 'numero_protocolo', 'status']) : null],
            'participantes' => DB::table('sup_participantes as p')->join('users as us', 'us.id', '=', 'p.user_id')->where('p.atendimento_id', $a->id)
                ->orderBy('p.id')->get(['us.id', 'us.name', 'us.email', 'p.papel']),
            'ehAtendente' => Suporte::ehAtendente($u), 'souSolicitante' => (int) $a->solicitante_id === $u->id,
            'statusRotulos' => Suporte::STATUS, 'niveis' => Suporte::NIVEIS,
            'chamadoConfigurado' => (bool) Suporte::config()->chamado_unidade_id,
        ]);
    }

    public function assumir(int $id)
    {
        $this->exigirAtendente();
        Suporte::assumir($this->interno($id), Auth::user());

        return back()->with('success', 'Atendimento assumido.');
    }

    public function escalar(Request $request, int $id)
    {
        $this->exigirAtendente();
        $d = $request->validate(['motivo' => ['required', 'string', 'max:1000']]);
        $a = $this->interno($id);
        abort_if((int) $a->nivel >= 3, 422, 'O atendimento já está no último nível.');
        if ((int) $a->nivel === 2 && ! Suporte::config()->chamado_unidade_id) {
            return back()->with('error', 'Configure o setor da TI e o assunto do chamado técnico em Configurações → Suporte antes de encaminhar ao nível 3.');
        }
        $p = Suporte::escalar($a, Auth::user(), $d['motivo']);

        return back()->with('success', $p ? "Encaminhado à TI: chamado técnico {$p->numero_protocolo} aberto." : 'Atendimento encaminhado ao nível ' . ((int) $a->nivel + 1) . '.');
    }

    public function incluir(Request $request, int $id)
    {
        $a = $this->atendimento($id);
        $d = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        Suporte::incluir($a, User::findOrFail($d['user_id']), Auth::user());

        return back()->with('success', 'Participante incluído.');
    }

    public function resolver(Request $request, int $id)
    {
        $this->exigirAtendente();
        $d = $request->validate(['solucao' => ['required', 'string', 'max:10000']]);
        Suporte::resolver($this->interno($id), Auth::user(), $d['solucao']);

        return back()->with('success', 'Resolvido. O usuário recebeu o e-mail para avaliar ou reabrir.');
    }

    public function reabrir(Request $request, int $id)
    {
        $a = $this->atendimento($id);
        abort_unless(in_array($a->status, ['resolvido', 'fechado'], true), 422);
        if ($a->via_central) {
            Suporte::pedirReabertura($a, Auth::user(), (string) $request->input('motivo', ''));

            return back()->with('success', 'Pedido de reabertura enviado à equipe Conceito.');
        }
        Suporte::reabrir($a, Auth::user(), (string) $request->input('motivo', ''));

        return back()->with('success', 'Atendimento reaberto.');
    }

    public function cancelar(int $id)
    {
        $a = $this->atendimento($id);
        abort_unless((int) $a->solicitante_id === Auth::id() || Suporte::ehAtendente(Auth::user()), 403);
        DB::table('sup_atendimentos')->where('id', $a->id)->update(['status' => 'cancelado', 'fechado_em' => now(), 'updated_at' => now()]);
        Suporte::sistema($a->id, Auth::user()->name . ' cancelou o atendimento.');

        return back()->with('success', 'Atendimento cancelado.');
    }

    /** Usuários para incluir no atendimento. */
    public function pessoas(Request $request)
    {
        $b = trim((string) $request->input('q'));

        return response()->json(strlen($b) < 2 ? [] : DB::table('users')->whereNull('suspenso_em')->where('somente_portal', false)->where('tipo', '<>', 'externo')
            ->where(fn ($w) => $w->where('name', 'ilike', "%{$b}%")->orWhere('email', 'ilike', "%{$b}%"))->orderBy('name')->limit(15)->get(['id', 'name', 'email']));
    }

    /** O atendente liga ou desliga a disponibilidade (4.26.2). */
    public function disponibilidade(Request $request)
    {
        $this->exigirAtendente();
        DB::table('sup_atendentes')->updateOrInsert(['user_id' => Auth::id()], ['disponivel' => $request->boolean('disponivel'), 'visto_em' => now(), 'updated_at' => now()]);

        return back();
    }

    /** Central de ajuda para qualquer servidor. */
    public function ajuda(Request $request)
    {
        return Inertia::render('Suporte/Ajuda', [
            'assuntos' => DB::table('sup_assuntos')->where('ativo', true)->orderBy('ordem')->orderBy('nome')->get(['id', 'nome', 'descricao']),
            'artigos' => DB::table('sup_artigos')->where('publicado', true)->orderBy('titulo')->get(['id', 'assunto_id', 'titulo', 'resumo', 'conteudo', 'visualizacoes']),
            'artigo' => $request->integer('artigo') ?: null,
        ]);
    }

    // ---------------------------------------------------------------- avaliação pelo e-mail (sem login)

    public function avaliacao(Request $request, string $token)
    {
        $a = DB::table('sup_atendimentos')->where('token', $token)->first();
        abort_unless($a, 404);

        return Inertia::render('Suporte/Avaliar', ['atendimento' => ['numero' => $a->numero, 'titulo' => $a->titulo, 'status' => $a->status,
            'avaliado' => (bool) $a->avaliado_em, 'nota' => $a->avaliacao_nota], 'token' => $token, 'reabrir' => $request->boolean('reabrir'),
            'resumo' => Suporte::resumo($a)]);
    }

    public function avaliar(Request $request, string $token)
    {
        $a = DB::table('sup_atendimentos')->where('token', $token)->first();
        abort_unless($a, 404);
        if ($request->boolean('reabrir')) {
            $d = $request->validate(['motivo' => ['required', 'string', 'max:2000']]);
            abort_unless(in_array($a->status, ['resolvido', 'fechado'], true), 422, 'O atendimento já está aberto.');
            if ($a->via_central) {
                Suporte::pedirReabertura($a, User::findOrFail($a->solicitante_id), $d['motivo']);

                return back()->with('success', "Pedido de reabertura do atendimento {$a->numero} enviado à equipe Conceito.");
            }
            Suporte::reabrir($a, User::find($a->solicitante_id), $d['motivo']);
            Suporte::mensagem($a->id, (int) $a->solicitante_id, $d['motivo']);

            return back()->with('success', "Atendimento {$a->numero} reaberto. A equipe de suporte vai continuar por ele.");
        }
        $d = $request->validate(['nota' => ['required', 'integer', 'between:1,5'], 'comentario' => ['nullable', 'string', 'max:2000']]);
        abort_if((bool) $a->avaliado_em, 422, 'Este atendimento já foi avaliado.');
        Suporte::avaliar($a, $d['nota'], $d['comentario'] ?? null);

        return back()->with('success', 'Obrigado pela avaliação!');
    }

    // ---------------------------------------------------------------- configuração

    public function configuracao()
    {
        $this->exigir('suporte.gerir');

        return Inertia::render('Configuracao/Suporte/Index', [
            'assuntos' => DB::table('sup_assuntos')->orderBy('ordem')->orderBy('nome')->get(),
            'artigos' => DB::table('sup_artigos')->orderBy('titulo')->get(['id', 'assunto_id', 'titulo', 'resumo', 'conteudo', 'publicado', 'visualizacoes', 'resolveu', 'nao_resolveu']),
            'atendentes' => DB::table('sup_atendentes as a')->join('users as u', 'u.id', '=', 'a.user_id')->orderBy('a.nivel')->orderBy('u.name')
                ->get(['u.id', 'u.name', 'u.email', 'a.nivel', 'a.disponivel', 'a.visto_em']),
            'config' => collect(Suporte::config())->except(['central_token', 'central_segredo']),
            // token e segredo nunca voltam para a tela: só o começo do token e se o segredo existe
            'central' => $this->centralParaTela(),
            'tiposChamado' => DB::table('proc_tipos_processo')->where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'modalidade']),
            'setores' => DB::table('ug_organograma')->where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'sigla']),
            'niveis' => Suporte::NIVEIS,
            'indicadores' => [
                'abertos' => DB::table('sup_atendimentos')->whereIn('status', ['aberto', 'em_atendimento'])->count(),
                'nota_media' => DB::table('sup_atendimentos')->whereNotNull('avaliacao_nota')->avg('avaliacao_nota'),
                'min_primeira_resposta' => DB::table('sup_atendimentos')->whereNotNull('primeira_resposta_em')
                    ->value(DB::raw('round(avg(extract(epoch from (primeira_resposta_em - created_at)) / 60))')),
            ],
        ]);
    }

    public function salvarAssunto(Request $request, ?int $id = null)
    {
        $this->exigir('suporte.gerir');
        $d = $request->validate(['nome' => ['required', 'string', 'max:120'], 'descricao' => ['nullable', 'string', 'max:300'], 'ordem' => ['nullable', 'integer'], 'ativo' => ['boolean']]);
        $l = ['nome' => $d['nome'], 'descricao' => $d['descricao'] ?? null, 'ordem' => (int) ($d['ordem'] ?? 0), 'ativo' => $request->boolean('ativo', true), 'updated_at' => now()];
        $id ? DB::table('sup_assuntos')->where('id', $id)->update($l) : DB::table('sup_assuntos')->insert($l + ['created_at' => now()]);

        return back()->with('success', 'Assunto salvo.');
    }

    public function salvarArtigo(Request $request, ?int $id = null)
    {
        $this->exigir('suporte.gerir');
        $d = $request->validate(['assunto_id' => ['required', 'integer', 'exists:sup_assuntos,id'], 'titulo' => ['required', 'string', 'max:200'],
            'resumo' => ['nullable', 'string', 'max:400'], 'conteudo' => ['required', 'string', 'max:100000'], 'publicado' => ['boolean']]);
        $l = ['assunto_id' => $d['assunto_id'], 'titulo' => $d['titulo'], 'resumo' => $d['resumo'] ?? null, 'conteudo' => HtmlSeguro::limpar($d['conteudo']),
            'publicado' => $request->boolean('publicado', true), 'updated_at' => now()];
        $id ? DB::table('sup_artigos')->where('id', $id)->update($l) : DB::table('sup_artigos')->insert($l + ['created_at' => now()]);

        return back()->with('success', 'Artigo salvo.');
    }

    public function excluirArtigo(int $id)
    {
        $this->exigir('suporte.gerir');
        DB::table('sup_artigos')->where('id', $id)->delete();

        return back()->with('success', 'Artigo excluído.');
    }

    public function salvarAtendente(Request $request)
    {
        $this->exigir('suporte.gerir');
        $d = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'nivel' => ['required', 'integer', 'between:1,3'], 'remover' => ['boolean']]);
        if ($request->boolean('remover')) {
            DB::table('sup_atendentes')->where('user_id', $d['user_id'])->delete();

            return back()->with('success', 'Atendente removido.');
        }
        DB::table('sup_atendentes')->updateOrInsert(['user_id' => $d['user_id']], ['nivel' => $d['nivel'], 'updated_at' => now()]);
        $u = User::find($d['user_id']);
        $msg = Suporte::ehAtendente($u) ? 'Atendente salvo.' : 'Atendente salvo. Dê a ele a permissão "suporte.atender" no perfil para ver a fila.';

        return back()->with('success', $msg);
    }

    public function salvarConfig(Request $request)
    {
        $this->exigir('suporte.gerir');
        $d = $request->validate(['email_entrada' => ['nullable', 'email', 'max:150'], 'chamado_tipo_id' => ['nullable', 'integer', 'exists:proc_tipos_processo,id'],
            'chamado_unidade_id' => ['nullable', 'integer', 'exists:ug_organograma,id'], 'horas_fechamento' => ['required', 'integer', 'between:1,720']]);
        $id = DB::table('sup_config')->value('id');
        $id ? DB::table('sup_config')->where('id', $id)->update($d + ['updated_at' => now()]) : DB::table('sup_config')->insert($d + ['created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Configuração do suporte salva.');
    }

    /** Central de Demandas da Conceito: endereço, token e segredo (cifrados) e liga/desliga. */
    public function salvarCentral(Request $request)
    {
        $this->exigir('suporte.gerir');
        $d = $request->validate(['central_url' => ['nullable', 'url:http,https', 'max:300'], 'central_token' => ['nullable', 'string', 'regex:/^gpe_[A-Za-z0-9]{40}$/'],
            'central_segredo' => ['nullable', 'string', 'size:48'], 'central_ativo' => ['boolean']], [
                'central_token.regex' => 'O token começa com gpe_ e tem 44 caracteres.', 'central_segredo.size' => 'O segredo tem 48 caracteres.']);
        $atual = DB::table('sup_config')->first();
        $l = ['central_url' => $d['central_url'] ? rtrim($d['central_url'], '/') : null, 'central_ativo' => $request->boolean('central_ativo'), 'updated_at' => now()];
        if (! empty($d['central_token'])) {
            $l['central_token'] = Crypt::encryptString($d['central_token']);
        }
        if (! empty($d['central_segredo'])) {
            $l['central_segredo'] = Crypt::encryptString($d['central_segredo']);
        }
        if ($l['central_ativo'] && (! $l['central_url'] || ! ($l['central_token'] ?? $atual?->central_token) || ! ($l['central_segredo'] ?? $atual?->central_segredo))) {
            return back()->with('error', 'Para ligar a integração informe o endereço da API, o token e o segredo.');
        }
        $atual ? DB::table('sup_config')->where('id', $atual->id)->update($l) : DB::table('sup_config')->insert($l + ['created_at' => now()]);

        return back()->with('success', $l['central_ativo'] ? 'Integração com a Central de Demandas ligada: os atendimentos novos vão para a equipe Conceito.'
            : 'Integração com a Central de Demandas salva (desligada).');
    }

    public function testarCentral()
    {
        $this->exigir('suporte.gerir');
        try {
            $r = CentralDemandasClient::doTenant()->testar();
        } catch (\Throwable $e) {
            $r = ['ok' => false, 'mensagem' => 'Salve o endereço e o token antes de testar.'];
        }

        return back()->with($r['ok'] ? 'success' : 'error', $r['mensagem']);
    }

    private function centralParaTela(): array
    {
        $c = DB::table('sup_config')->first(['central_ativo', 'central_url', 'central_token', 'central_segredo']);
        $inicio = null;
        try {
            $inicio = $c?->central_token ? mb_substr(Crypt::decryptString($c->central_token), 0, 8) . '…' : null;
        } catch (\Throwable) {
            $inicio = '(ilegível: informe de novo)';
        }

        return ['ativo' => (bool) $c?->central_ativo, 'url' => $c?->central_url, 'token_inicio' => $inicio, 'tem_segredo' => (bool) $c?->central_segredo,
            'pendentes' => DB::table('sup_atendimentos')->where('via_central', true)->whereNotNull('central_erro')->count()];
    }

    // ---------------------------------------------------------------- apoio

    /** Atendimento do fluxo interno: os da Central de Demandas quem atende é a equipe Conceito. */
    private function interno(int $id): object
    {
        $a = $this->atendimento($id);
        abort_if($a->via_central, 422, 'Este atendimento é da Central de Demandas: quem atende é a equipe Conceito.');

        return $a;
    }

    private function atendimento(int $id): object
    {
        $a = DB::table('sup_atendimentos')->find($id);
        abort_unless($a && Suporte::podeVer(Auth::user(), $a), 404);

        return $a;
    }

    private function doUsuario(int $userId)
    {
        return DB::table('sup_atendimentos as a')->whereExists(fn ($e) => $e->from('sup_participantes as p')->whereColumn('p.atendimento_id', 'a.id')->where('p.user_id', $userId))
            ->select('a.*')->orderByRaw("case when a.status in ('aberto','em_atendimento') then 0 else 1 end")->orderByDesc('a.updated_at');
    }

    private function resumoLista(object $a, int $userId): array
    {
        $lido = (int) DB::table('sup_participantes')->where('atendimento_id', $a->id)->where('user_id', $userId)->value('lido_ate');
        $ult = DB::table('sup_mensagens')->where('atendimento_id', $a->id)->where('tipo', '<>', 'nota')->orderByDesc('id')->first(['conteudo', 'created_at']);

        return ['id' => $a->id, 'numero' => $a->numero, 'titulo' => $a->titulo, 'status' => $a->status, 'status_rotulo' => Suporte::STATUS[$a->status] ?? $a->status,
            'nivel' => (int) $a->nivel, 'canal' => $a->canal, 'avaliacao_nota' => $a->avaliacao_nota,
            'via_central' => (bool) $a->via_central, 'central_numero' => $a->central_numero,
            'assunto' => $a->assunto_id ? DB::table('sup_assuntos')->where('id', $a->assunto_id)->value('nome') : null,
            'solicitante' => DB::table('users')->where('id', $a->solicitante_id)->value('name'),
            'atendente' => $a->atendente_id ? DB::table('users')->where('id', $a->atendente_id)->value('name') : null,
            'ultima' => $ult?->conteudo ? mb_substr($ult->conteudo, 0, 120) : null,
            'nao_lidas' => DB::table('sup_mensagens')->where('atendimento_id', $a->id)->where('id', '>', $lido)->where('tipo', 'mensagem')
                ->where(fn ($w) => $w->whereNull('autor_id')->orWhere('autor_id', '<>', $userId))->count(),
            'criado_em' => Carbon::parse($a->created_at, 'UTC')->toIso8601String(), 'atualizado_em' => Carbon::parse($a->updated_at, 'UTC')->toIso8601String()];
    }

    private function presenca(User $u): void
    {
        DB::table('sup_atendentes')->where('user_id', $u->id)->update(['visto_em' => now()]);
    }

    private function exigirAtendente(): void
    {
        abort_unless(Suporte::ehAtendente(Auth::user()), 403);
    }

    private function exigir(string $perm): void
    {
        abort_unless(Auth::user()->super_admin || Auth::user()->can($perm), 403);
    }
}
