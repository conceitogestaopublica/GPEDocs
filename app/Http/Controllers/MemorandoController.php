<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notificacao;
use App\Models\Processo\Memorando;
use App\Models\Processo\MemorandoAnexo;
use App\Models\Processo\MemorandoDestinatario;
use App\Models\Processo\MemorandoResposta;
use App\Models\Processo\MemorandoTramitacao;
use App\Models\User;
use App\Support\Acessos;
use App\Support\Setores;
use App\Support\Relatorio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use App\Support\EmNomeDe;

class MemorandoController extends Controller
{
    public function index(Request $request): Response
    {
        $tipo = $request->input('tipo', 'recebidos');

        if ($tipo === 'enviados') {
            $query = Memorando::where(fn ($q) => $q->where('remetente_id', Auth::id())->orWhere('digitador_id', Auth::id()));
        } else {
            // Recebidos: para mim, para os setores que atendo (inclui "todos os setores") ou tramitados a eles
            $setores = Setores::doUsuario(Auth::user()) ?: [-1];
            $query = Memorando::where(fn ($p) => $p->where('confidencial', false)->orWhere('remetente_id', Auth::id())
                    ->orWhereHas('destinatarios', fn ($q) => $q->where('usuario_id', Auth::id()))
                    ->orWhereHas('tramitacoes', fn ($q) => $q->where('destino_usuario_id', Auth::id())))
                ->where(fn ($m) => $m
                ->whereHas('destinatarios', fn ($q) => $q->where('usuario_id', Auth::id())->orWhereIn('unidade_id', $setores))
                ->orWhereHas('tramitacoes', fn ($q) => $q->where('destino_usuario_id', Auth::id())->orWhereIn('destino_unidade_id', $setores)));
        }

        $memorandos = $query->with(['remetente', 'destinatarios.usuario'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $usuarios = User::orderBy('name')->get(['id', 'name', 'email']);

        return Inertia::render('GED/Memorandos/Index', [
            'memorandos' => $memorandos,
            'usuarios'   => $usuarios,
            'filters'    => ['tipo' => $tipo],
        ]);
    }

    public function create(): Response
    {
        $ugId = session('ug_id');

        $usuarios = User::where('id', '!=', Auth::id())
            ->when($ugId, fn ($q) => $q->where(function ($q) use ($ugId) {
                $q->whereHas('ugs', fn ($q) => $q->where('ugs.id', $ugId))
                  ->orWhere('ug_id', $ugId);
            }))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'unidade_id']);

        $unidades = \App\Models\UgOrganograma::query()
            ->when($ugId, fn ($q) => $q->where('ug_id', $ugId))
            ->where('ativo', true)
            ->orderBy('nivel')
            ->orderBy('nome')
            ->get(['id', 'nome', 'sigla', 'codigo', 'nivel', 'parent_id']);

        return Inertia::render('GED/Memorandos/Create', [
            'variaveis'    => Memorando::variaveisParaEditor(),
            'rascunho'     => \App\Support\Rascunhos::abrir(request()->integer('rascunho') ?: null, Auth::user(), 'memorando'),
            'autorizacoes' => EmNomeDe::autorizacoes(),
            'usuarios' => $usuarios,
            'unidades' => $unidades,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'assunto'               => ['required', 'string', 'max:255'],
            'conteudo'              => ['required', 'string'],
            'tipo_destino'          => ['required', 'in:todos_setores,setor,usuario'],
            'unidade_id'            => ['nullable', 'integer', 'exists:ug_organograma,id', 'required_if:tipo_destino,setor'],
            'destinatarios'         => ['nullable', 'array', 'required_if:tipo_destino,usuario'],
            'destinatarios.*'       => ['integer', 'exists:users,id'],
            'confidencial'          => ['nullable', 'boolean', function ($a, $v, $falha) use ($request) {
                if ($v && $request->input('tipo_destino') !== 'usuario') {
                    $falha('Memorando privado vai só para pessoas: escolha "Setor + Usuário".');
                }
            }],
            'setor_origem'          => ['nullable', 'string', 'max:150'],
            'data_arquivamento_auto'=> ['nullable', 'date'],
            'files'                 => ['nullable', 'array'],
            'files.*'               => ['file', 'max:51200'],
        ]);

        // Em nome de outro servidor, por contrassenha (TR 4.18)
        $contrassenha = \App\Support\EmNomeDe::conferir($request);

        try {
            DB::beginTransaction();
            $origem = \App\Support\EmNomeDe::consumir($contrassenha);

            $year = date('Y');

            $lastNum = Memorando::whereYear('created_at', $year)
                ->max(DB::raw("CAST(SPLIT_PART(numero, '/', 2) AS INTEGER)"));

            $nextNum = ($lastNum ?? 0) + 1;
            $numero  = 'MEM-' . $year . '/' . str_pad((string) $nextNum, 6, '0', STR_PAD_LEFT);

            $memorando = Memorando::create([
                'numero'                => $numero,
                'assunto'               => $request->input('assunto'),
                'conteudo'              => $request->input('conteudo'),
                'remetente_id'          => $origem['remetente_id'],
                'digitador_id'          => $origem['digitador_id'],
                'contrassenha_id'       => $origem['contrassenha_id'],
                'setor_origem'          => $request->input('setor_origem'),
                'confidencial'          => $request->boolean('confidencial'),
                'status'                => 'enviado',
                'enviado_em'            => now(),
                'data_arquivamento_auto'=> $request->input('data_arquivamento_auto'),
            ]);
            \App\Support\EmNomeDe::avisarAutorizador($origem, 'memorando', $memorando->id, "Memorando {$numero} — {$memorando->assunto}");

            $tipoDestino = $request->input('tipo_destino');
            $usuariosNotificar = [];

            if ($tipoDestino === 'todos_setores') {
                $ugId = session('ug_id') ?? Auth::user()->ug_id;
                $unidades = \App\Models\UgOrganograma::where('ug_id', $ugId)->where('ativo', true)->pluck('id');
                foreach ($unidades as $unidadeId) {
                    MemorandoDestinatario::create([
                        'memorando_id' => $memorando->id,
                        'unidade_id'   => (int) $unidadeId,
                        'lido'         => false,
                    ]);
                }
                $usuariosNotificar = User::whereIn('unidade_id', $unidades)->pluck('id')->all();
            } elseif ($tipoDestino === 'setor') {
                $unidadeId = (int) $request->input('unidade_id');
                MemorandoDestinatario::create([
                    'memorando_id' => $memorando->id,
                    'unidade_id'   => $unidadeId,
                    'lido'         => false,
                ]);
                $usuariosNotificar = Setores::servidores((int) $unidadeId);
            } else { // usuario
                foreach ((array) $request->input('destinatarios') as $usuarioId) {
                    MemorandoDestinatario::create([
                        'memorando_id' => $memorando->id,
                        'usuario_id'   => (int) $usuarioId,
                        'lido'         => false,
                    ]);
                    $usuariosNotificar[] = (int) $usuarioId;
                }
            }

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store(\App\Tenant\TenantStorage::pasta('memorandos'), 'documentos');

                    MemorandoAnexo::create([
                        'memorando_id' => $memorando->id,
                        'nome'         => $file->getClientOriginalName(),
                        'arquivo_path' => $path,
                        'tamanho'      => $file->getSize(),
                        'mime_type'    => $file->getMimeType(),
                        'enviado_por'  => Auth::id(),
                    ]);
                }
            }

            // Anexos guardados no rascunho automático (TR 4.16.8)
            foreach (\App\Support\Rascunhos::entregarAnexos($request->integer('rascunho_id') ?: null, 'memorandos', Auth::user()) as $anexo) {
                MemorandoAnexo::create(['memorando_id' => $memorando->id] + $anexo);
            }

            foreach (array_unique($usuariosNotificar) as $usuarioId) {
                Notificacao::create([
                    'usuario_id'      => (int) $usuarioId,
                    'tipo'            => 'memorando_recebido',
                    'titulo'          => 'Novo memorando recebido',
                    'mensagem'        => "Memorando {$numero} - {$memorando->assunto} recebido.",
                    'referencia_tipo' => 'memorando',
                    'referencia_id'   => $memorando->id,
                    'lida'            => false,
                ]);
            }

            DB::commit();
            \App\Support\Rascunhos::concluir($request->integer('rascunho_id') ?: null, Auth::user());
            // @ menções, # citações e ! assinaturas do texto (TR 4.16.13–4.16.18)
            if ($avisos = \App\Support\Marcadores::aposSalvar('memorando', $memorando->id, $memorando->conteudo, Auth::user(), $memorando)) {
                session()->flash('warning', implode(' ', $avisos));
            }

            return redirect("/memorandos/{$memorando->id}")->with('success', 'Memorando enviado com sucesso. Número: ' . $numero);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao enviar memorando: ' . $e->getMessage());
        }
    }

    /**
     * Remetente, destinatário direto ou pela unidade, ou participante da tramitação.
     * Vale para ver o memorando e para baixar os anexos dele.
     */
    private function podeVer(Memorando $memorando, \App\Models\User $user): bool
    {
        $memorando->loadMissing(['destinatarios', 'tramitacoes']);
        $acessoGeral = (bool) $user->acesso_geral_ug;

        // Privado (TR 4.45.6): só remetente, quem digitou e as pessoas a quem foi endereçado ou tramitado
        if ($memorando->confidencial) {
            return in_array($user->id, [$memorando->remetente_id, $memorando->digitador_id], true)
                || $memorando->destinatarios->contains('usuario_id', $user->id)
                || $memorando->tramitacoes->contains(fn ($t) => $t->destino_usuario_id === $user->id || $t->origem_usuario_id === $user->id)
                || \App\Support\Marcadores::temAcesso('memorando', $memorando->id, $user->id);
        }

        return $memorando->remetente_id === $user->id
            || $memorando->destinatarios->contains(fn ($d) =>
                $d->usuario_id === $user->id
                || Setores::atende($user, $d->unidade_id)
                || ($acessoGeral && $d->unidade_id !== null))
            || $memorando->tramitacoes->contains(fn ($t) =>
                $t->destino_usuario_id === $user->id
                || Setores::atende($user, $t->destino_unidade_id)
                || ($acessoGeral && $t->destino_unidade_id !== null))
            || $memorando->tramitacoes->contains('origem_usuario_id', $user->id)
            // Mencionado no texto (@) ou chamado a assinar (!) — TR 4.16.16
            || \App\Support\Marcadores::temAcesso('memorando', $memorando->id, $user->id);
    }

    /** Download de anexo — a rota que a tela usava não existia (404). */
    public function downloadAnexo($id, $anexoId)
    {
        $memorando = Memorando::findOrFail($id);
        if (! $this->podeVer($memorando, Auth::user())) {
            abort(403);
        }
        $anexo = \App\Models\Processo\MemorandoAnexo::where('memorando_id', $memorando->id)->findOrFail($anexoId);

        return \App\Support\Anexos::baixar($anexo->arquivo_path, $anexo->nome);
    }

    public function show($id): Response
    {
        $memorando = Memorando::with([
            'remetente', 'digitador:id,name',
            'destinatarios.usuario',
            'destinatarios.unidade',
            'anexos.enviadoPor',
            'respostas.usuario',
            'tramitacoes.origemUsuario:id,name,foto_path',
            'tramitacoes.origemUnidade:id,nome,codigo',
            'tramitacoes.destinoUsuario:id,name,foto_path',
            'tramitacoes.destinoUnidade:id,nome,codigo',
        ])->findOrFail($id);

        $user = Auth::user();
        $userId = $user->id;
        $acessoGeral = (bool) $user->acesso_geral_ug;
        // Usada no banner abaixo e nunca definida: toda tela de memorando dava erro 500.
        $isRemetente = $memorando->remetente_id === $userId;
        // Também usada no banner e nunca definida (erro 500 para quem só tramitou adiante)
        $isTramiteOrigem = $memorando->tramitacoes->contains('origem_usuario_id', $userId);

        if (! $this->podeVer($memorando, $user)) {
            abort(403, 'Você não tem permissão para visualizar este memorando.');
        }

        // Tramitacao ativa enderecada a esse user/setor (ordenada por id desc para pegar a mais recente)
        $tramiteAtivo = $memorando->tramitacoes
            ->where('em_uso', true)
            ->sortByDesc('id')
            ->first(fn ($t) =>
                $t->destino_usuario_id === $userId
                || Setores::atende($user, $t->destino_unidade_id)
                || ($acessoGeral && $t->destino_unidade_id !== null)
            );

        // Destinatario original ativo (sem tramitacao ainda)
        $destinatarioAtivo = $memorando->tramitacoes->isEmpty()
            ? $memorando->destinatarios->first(fn ($d) =>
                $d->usuario_id === $userId
                || Setores::atende($user, $d->unidade_id)
                || ($acessoGeral && $d->unidade_id !== null)
            )
            : null;

        // Marca como lido se for destinatario direto pessoal
        if ($destinatarioAtivo && $destinatarioAtivo->usuario_id === $userId && ! $destinatarioAtivo->lido) {
            $destinatarioAtivo->update(['lido' => true, 'lido_em' => now()]);
        }

        $podeReceber  = ($tramiteAtivo && ! $tramiteAtivo->finalizado)
                      || ($destinatarioAtivo && $destinatarioAtivo->unidade_id !== null && ! $destinatarioAtivo->lido);
        $podeTramitar = ($tramiteAtivo && $tramiteAtivo->finalizado)
                      || ($destinatarioAtivo && ($destinatarioAtivo->lido || $destinatarioAtivo->usuario_id === $userId));

        // Status do usuario logado em relacao ao documento (pra banner)
        $meuStatus = null;
        if ($isRemetente && ! $tramiteAtivo && ! $destinatarioAtivo) {
            $meuStatus = ['estado' => 'remetente', 'mensagem' => 'Você e o remetente. Aguardando recebimento do destino.'];
        } elseif ($tramiteAtivo && ! $tramiteAtivo->finalizado) {
            $meuStatus = ['estado' => 'pendente', 'mensagem' => 'Aguardando seu recebimento. Clique em "Receber" para acusar.'];
        } elseif ($tramiteAtivo && $tramiteAtivo->finalizado) {
            $meuStatus = ['estado' => 'recebido', 'mensagem' => 'Recebido em ' . $tramiteAtivo->recebido_em?->format('d/m/Y H:i') . '. Voce pode tramitar, responder ou arquivar.'];
        } elseif ($destinatarioAtivo && ! $destinatarioAtivo->lido && $destinatarioAtivo->unidade_id) {
            $meuStatus = ['estado' => 'pendente', 'mensagem' => 'Aguardando recebimento pelo setor. Clique em "Receber" para acusar.'];
        } elseif ($destinatarioAtivo) {
            $meuStatus = ['estado' => 'recebido', 'mensagem' => 'Recebido em ' . ($destinatarioAtivo->lido_em?->format('d/m/Y H:i') ?? '-') . '. Voce pode tramitar, responder ou arquivar.'];
        } elseif ($isTramiteOrigem) {
            $meuStatus = ['estado' => 'tramitou', 'mensagem' => 'Você já tramitou este memorando para frente. Acompanhando.'];
        }

        // Lista de unidades + usuarios pra modal de tramitar
        $ugId = session('ug_id');
        $unidades = \App\Models\UgOrganograma::query()
            ->when($ugId, fn ($q) => $q->where('ug_id', $ugId))
            ->where('ativo', true)
            ->orderBy('nivel')
            ->orderBy('nome')
            ->get(['id', 'nome', 'codigo', 'nivel', 'parent_id']);
        $usuariosTramite = User::where('id', '!=', $userId)
            ->when($ugId, fn ($q) => $q->where(function ($q) use ($ugId) {
                $q->whereHas('ugs', fn ($q) => $q->where('ugs.id', $ugId))
                  ->orWhere('ug_id', $ugId);
            }))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'unidade_id']);

        Acessos::registrar('memorando', $memorando->id, $user);

        return Inertia::render('GED/Memorandos/Show', [
            'classificacao'   => \App\Support\Classificacao::doItem('memorando', $memorando->id),
            'acessos'         => Acessos::de('memorando', $memorando->id),
            'memorando'       => $memorando,
            'conteudo_html'   => $memorando->conteudoFinal(),
            'marcadores'      => ['citado_em' => \App\Support\Marcadores::citadoEm('memorando', $memorando->id), 'pessoas' => \App\Support\Marcadores::doDocumento('memorando', $memorando->id)],
            'despachos'       => \App\Support\Memorandos::despachos($memorando),
            'envolvidos'      => \App\Support\Memorandos::envolvidos($memorando),
            'pode_receber'    => $podeReceber,
            'pode_tramitar'   => $podeTramitar,
            'meu_status'      => $meuStatus,
            'unidades'        => $unidades,
            'usuarios'        => $usuariosTramite,
        ]);
    }

    public function responder(Request $request, $id)
    {
        $request->validate([
            'conteudo' => ['required', 'string'],
        ]);

        $memorando = Memorando::findOrFail($id);
        abort_unless($this->podeVer($memorando, Auth::user()), 403);
        abort_if($memorando->status === 'arquivado', 422, 'Memorando arquivado.');

        MemorandoResposta::create([
            'memorando_id' => $memorando->id,
            'usuario_id'   => Auth::id(),
            'conteudo'     => \App\Support\HtmlSeguro::limpar($request->input('conteudo')),
        ]);

        Notificacao::create([
            'usuario_id'      => $memorando->remetente_id,
            'tipo'            => 'memorando_resposta',
            'titulo'          => 'Nova resposta em memorando',
            'mensagem'        => "O memorando {$memorando->numero} recebeu uma nova resposta.",
            'referencia_tipo' => 'memorando',
            'referencia_id'   => $memorando->id,
            'lida'            => false,
        ]);

        return redirect()->back()->with('success', 'Resposta enviada com sucesso.');
    }

    /**
     * Marca o memorando como recebido pelo usuario logado (no contexto da tramitacao
     * ativa). Sem isso o usuario nao pode tramitar.
     */
    public function receber($id)
    {
        $memorando = Memorando::findOrFail($id);
        $user = Auth::user();

        // Tenta achar a tramitacao ativa enderecada a esse usuario / setor dele
        // (ou qualquer setor da UG se o usuario tem acesso geral)
        $tramite = MemorandoTramitacao::where('memorando_id', $memorando->id)
            ->where('em_uso', true)
            ->where(function ($q) use ($user) {
                $q->where('destino_usuario_id', $user->id);
                $q->orWhereIn('destino_unidade_id', Setores::doUsuario($user));
                if ($user->acesso_geral_ug) {
                    $q->orWhereNotNull('destino_unidade_id');
                }
            })
            ->first();

        if ($tramite) {
            $tramite->update([
                'finalizado'  => true,
                'recebido_em' => now(),
            ]);
        } else {
            // Sem tramitacao ainda — marca destinatarios pertinentes como lido
            MemorandoDestinatario::where('memorando_id', $memorando->id)
                ->where('lido', false)
                ->where(function ($q) use ($user) {
                    $q->where('usuario_id', $user->id);
                    $q->orWhereIn('unidade_id', Setores::doUsuario($user));
                    if ($user->acesso_geral_ug) {
                        $q->orWhereNotNull('unidade_id');
                    }
                })
                ->update(['lido' => true, 'lido_em' => now()]);
        }

        $quando = now()->format('d/m/Y H:i');
        return redirect()->back()->with('success', "Recebimento confirmado em {$quando}. Agora você pode encaminhar, responder ou arquivar.");
    }

    /**
     * Encaminha o memorando para o proximo passo (usuario ou unidade).
     * Encerra a tramitacao ativa anterior (em_uso=false) e cria uma nova.
     */
    public function tramitar(Request $request, $id)
    {
        $request->validate([
            'tipo_destino'           => ['required', 'in:setor,usuario'],
            'destino_unidade_id'     => ['nullable', 'integer', 'exists:ug_organograma,id', 'required_if:tipo_destino,setor'],
            'destino_usuario_id'     => ['nullable', 'integer', 'exists:users,id', 'required_if:tipo_destino,usuario'],
            'parecer'                => ['nullable', 'string', 'max:5000'],
            'registrar_como_resposta'=> ['nullable', 'boolean'],
        ]);

        $memorando = Memorando::findOrFail($id);
        $user = Auth::user();
        abort_unless($this->podeVer($memorando, $user), 403);
        if ($memorando->confidencial && $request->input('tipo_destino') !== 'usuario') {
            throw \Illuminate\Validation\ValidationException::withMessages(['tipo_destino' => 'Memorando privado só pode ser tramitado a uma pessoa.']);
        }

        try {
            DB::beginTransaction();

            // Identifica a tramitacao ativa anterior (se houver) — ela vai ser fechada
            $tramiteAnterior = MemorandoTramitacao::where('memorando_id', $memorando->id)
                ->where('em_uso', true)
                ->where(function ($q) use ($user) {
                    $q->where('destino_usuario_id', $user->id);
                    $q->orWhereIn('destino_unidade_id', Setores::doUsuario($user));
                    if ($user->acesso_geral_ug) {
                        $q->orWhereNotNull('destino_unidade_id');
                    }
                })
                ->latest('id')
                ->first();

            // Encerra a tramitacao anterior — recebido + sai do "em_uso"
            if ($tramiteAnterior) {
                $tramiteAnterior->update([
                    'finalizado' => true,
                    'recebido_em' => $tramiteAnterior->recebido_em ?? now(),
                    'em_uso'     => false,
                ]);
            }

            // Cria a nova tramitacao
            $destinoUsuarioId = $request->input('tipo_destino') === 'usuario' ? (int) $request->input('destino_usuario_id') : null;
            $destinoUnidadeId = $request->input('tipo_destino') === 'setor'   ? (int) $request->input('destino_unidade_id') : null;

            MemorandoTramitacao::create([
                'memorando_id'        => $memorando->id,
                'tramite_origem_id'   => $tramiteAnterior?->id,
                'origem_usuario_id'   => $user->id,
                'origem_unidade_id'   => $user->unidade_id,
                'destino_usuario_id'  => $destinoUsuarioId,
                'destino_unidade_id'  => $destinoUnidadeId,
                'parecer'             => $request->input('parecer'),
                'em_uso'              => true,
                'finalizado'          => false,
                'despachado_em'       => now(),
            ]);

            // Se marcado, registra o parecer tambem como resposta no thread (visivel para remetente original)
            if ($request->boolean('registrar_como_resposta') && trim((string) $request->input('parecer')) !== '') {
                MemorandoResposta::create([
                    'memorando_id' => $memorando->id,
                    'usuario_id'   => $user->id,
                    'conteudo'     => $request->input('parecer'),
                ]);

                // Notifica o remetente original que houve resposta
                if ($memorando->remetente_id !== $user->id) {
                    Notificacao::create([
                        'usuario_id'      => $memorando->remetente_id,
                        'tipo'            => 'memorando_resposta',
                        'titulo'          => 'Resposta no memorando',
                        'mensagem'        => "O memorando {$memorando->numero} recebeu uma resposta junto com a tramitação.",
                        'referencia_tipo' => 'memorando',
                        'referencia_id'   => $memorando->id,
                        'lida'            => false,
                    ]);
                }
            }

            // Notifica o destino
            $usuariosNotificar = [];
            if ($destinoUsuarioId) {
                $usuariosNotificar[] = $destinoUsuarioId;
            } elseif ($destinoUnidadeId) {
                $usuariosNotificar = Setores::servidores((int) $destinoUnidadeId);
            }
            foreach (array_unique($usuariosNotificar) as $uid) {
                Notificacao::create([
                    'usuario_id'      => (int) $uid,
                    'tipo'            => 'memorando_tramitado',
                    'titulo'          => 'Memorando tramitado para voce',
                    'mensagem'        => "Memorando {$memorando->numero} - {$memorando->assunto} chegou via tramitação.",
                    'referencia_tipo' => 'memorando',
                    'referencia_id'   => $memorando->id,
                    'lida'            => false,
                ]);
            }

            DB::commit();
            return redirect()->back()->with('success', 'Memorando tramitado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Erro ao tramitar: ' . $e->getMessage());
        }
    }

    public function arquivar($id)
    {
        $memorando = Memorando::findOrFail($id);
        abort_unless($this->podeVer($memorando, Auth::user()), 403);

        $memorando->update([
            'status'       => 'arquivado',
            'arquivado_em' => now(),
        ]);

        return redirect()->back()->with('success', 'Memorando arquivado com sucesso.');
    }

    /**
     * Arquiva o memorando em uma pasta do GPE Docs.
     * Gera PDF, cria Documento+Versao e move para a pasta.
     */
    public function arquivarNoGed(Request $request, $id)
    {
        $request->validate(['pasta_id' => ['required', 'integer', 'exists:ged_pastas,id']]);

        $memorando = Memorando::with(['remetente', 'destinatarios.usuario', 'respostas.usuario'])->findOrFail($id);

        // Valida pasta na mesma UG
        $pasta = DB::table('ged_pastas')->where('id', $request->input('pasta_id'))->first();
        if (! $pasta || $pasta->ug_id !== $memorando->ug_id) {
            return redirect()->back()->with('error', 'A pasta selecionada não pertence a UG deste memorando.');
        }

        try {
            DB::beginTransaction();

            // Reaproveita o documento ja gerado, se houver
            if ($memorando->documento_id) {
                $documento = \App\Models\Documento::find($memorando->documento_id);
                if ($documento) {
                    $documento->update(['pasta_id' => (int) $request->input('pasta_id'), 'status' => 'arquivado']);
                    DB::commit();
                    return redirect()->back()->with('success', "Memorando arquivado na pasta \"{$pasta->nome}\".");
                }
            }

            // Gera o PDF do memorando
            $pdf = Relatorio::pdf('pdf.memorando', [
                'memorando' => $memorando,
                'qrCodeUrl' => url("/memorandos/verificar/{$memorando->qr_code_token}"),
                'ug'        => \App\Models\Ug::find($memorando->ug_id),
            ]);
            $pdfBytes = $pdf->output();

            $filename = 'memorando-' . str_replace(['/', '\\'], '-', $memorando->numero) . '.pdf';
            $path = \App\Tenant\TenantStorage::pasta('documentos') . '/' . date('Y/m') . '/' . uniqid() . '-' . $filename;
            Storage::disk('documentos')->put($path, $pdfBytes);

            $textoPesquisavel = collect([
                'Memorando', $memorando->numero, $memorando->assunto,
                \App\Support\Variaveis::textoPuro($memorando->conteudoFinal()), $memorando->setor_origem,
                $memorando->remetente?->name,
            ])->filter()->implode(' | ');

            $documento = \App\Models\Documento::create([
                'nome'              => 'Memorando ' . $memorando->numero,
                'descricao'         => $memorando->assunto,
                'tipo_documental_id'=> \App\Support\TiposDocumentais::id('Memorando'),
                'pasta_id'          => (int) $request->input('pasta_id'),
                'versao_atual'      => 1,
                'tamanho'           => strlen($pdfBytes),
                'mime_type'         => 'application/pdf',
                'autor_id'          => Auth::id(),
                'status'            => 'arquivado',
                'ocr_texto'         => $textoPesquisavel,
            ]);

            \App\Models\Versao::create([
                'documento_id' => $documento->id,
                'versao'       => 1,
                'arquivo_path' => $path,
                'tamanho'      => strlen($pdfBytes),
                'hash_sha256'  => hash('sha256', $pdfBytes),
                'autor_id'     => Auth::id(),
                'comentario'   => 'Arquivado automaticamente do GPE Fluxos e Processos',
            ]);

            $memorando->update(['documento_id' => $documento->id]);

            DB::commit();
            return redirect()->back()->with('success', "Memorando arquivado na pasta \"{$pasta->nome}\".");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Erro ao arquivar: ' . $e->getMessage());
        }
    }

    public function downloadPdf($id)
    {
        $memorando = Memorando::with([
            'remetente',
            'destinatarios.usuario',
            'destinatarios.unidade',
            'respostas.usuario',
        ])->findOrFail($id);
        abort_unless($this->podeVer($memorando, Auth::user()), 403);

        $qrCodeUrl = url("/memorandos/verificar/{$memorando->qr_code_token}");

        $data = [
            'memorando'  => $memorando,
            'qrCodeUrl'  => $qrCodeUrl,
            'ug'         => \App\Models\Ug::find($memorando->ug_id),
        ];

        $pdf = Relatorio::pdf('pdf.memorando', $data);

        $filename = "memorando-{$memorando->numero}.pdf";
        $filename = str_replace('/', '-', $filename);

        return $pdf->download($filename);
    }

    /** Folha de rosto (TR 4.45.7): capa de uma página para o processo físico ou o arquivo. */
    public function folhaDeRosto($id)
    {
        $memorando = Memorando::with(['remetente', 'destinatarios.usuario', 'destinatarios.unidade', 'anexos', 'respostas', 'tramitacoes'])->findOrFail($id);
        abort_unless($this->podeVer($memorando, Auth::user()), 403);

        return Relatorio::pdf('pdf.memorando-folha-rosto', [
            'memorando' => $memorando,
            'despachos' => $memorando->respostas->count() + $memorando->tramitacoes->count(),
            'ug'        => \App\Models\Ug::find($memorando->ug_id),
        ])->stream('folha-de-rosto-' . str_replace('/', '-', $memorando->numero) . '.pdf');
    }
}