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
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MemorandoController extends Controller
{
    public function index(Request $request): Response
    {
        $tipo = $request->input('tipo', 'recebidos');

        if ($tipo === 'enviados') {
            $query = Memorando::where('remetente_id', Auth::id());
        } else {
            $query = Memorando::whereHas('destinatarios', function ($q) {
                $q->where('usuario_id', Auth::id());
            });
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
            ->get(['id', 'nome', 'codigo', 'nivel', 'parent_id']);

        return Inertia::render('GED/Memorandos/Create', [
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
            'confidencial'          => ['nullable', 'boolean'],
            'setor_origem'          => ['nullable', 'string', 'max:150'],
            'data_arquivamento_auto'=> ['nullable', 'date'],
            'files'                 => ['nullable', 'array'],
            'files.*'               => ['file', 'max:51200'],
        ]);

        try {
            DB::beginTransaction();

            $year = date('Y');

            $lastNum = Memorando::whereYear('created_at', $year)
                ->max(DB::raw("CAST(SPLIT_PART(numero, '/', 2) AS INTEGER)"));

            $nextNum = ($lastNum ?? 0) + 1;
            $numero  = 'MEM-' . $year . '/' . str_pad((string) $nextNum, 6, '0', STR_PAD_LEFT);

            $memorando = Memorando::create([
                'numero'                => $numero,
                'assunto'               => $request->input('assunto'),
                'conteudo'              => $request->input('conteudo'),
                'remetente_id'          => Auth::id(),
                'setor_origem'          => $request->input('setor_origem'),
                'confidencial'          => $request->boolean('confidencial'),
                'status'                => 'enviado',
                'enviado_em'            => now(),
                'data_arquivamento_auto'=> $request->input('data_arquivamento_auto'),
            ]);

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
                $usuariosNotificar = User::where('unidade_id', $unidadeId)->pluck('id')->all();
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
                    $path = $file->store('memorandos', 'documentos');

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

            return redirect("/memorandos/{$memorando->id}")->with('success', 'Memorando enviado com sucesso. Numero: ' . $numero);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao enviar memorando: ' . $e->getMessage());
        }
    }

    public function show($id): Response
    {
        $memorando = Memorando::with([
            'remetente',
            'destinatarios.usuario',
            'destinatarios.unidade',
            'anexos.enviadoPor',
            'respostas.usuario',
            'tramitacoes.origemUsuario:id,name',
            'tramitacoes.origemUnidade:id,nome,codigo',
            'tramitacoes.destinoUsuario:id,name',
            'tramitacoes.destinoUnidade:id,nome,codigo',
        ])->findOrFail($id);

        $user = Auth::user();
        $userId = $user->id;
        $unidadeId = $user->unidade_id;
        $acessoGeral = (bool) $user->acesso_geral_ug;

        // Tem acesso? Remetente, destinatario direto, destinatario via unidade, ou tramitacao p/ ele
        $isRemetente    = $memorando->remetente_id === $userId;
        $isDestinatario = $memorando->destinatarios->contains(fn ($d) =>
            $d->usuario_id === $userId
            || ($unidadeId && $d->unidade_id === $unidadeId)
            || ($acessoGeral && $d->unidade_id !== null)
        );
        $isTramiteDestino = $memorando->tramitacoes->contains(fn ($t) =>
            $t->destino_usuario_id === $userId
            || ($unidadeId && $t->destino_unidade_id === $unidadeId)
            || ($acessoGeral && $t->destino_unidade_id !== null)
        );
        $isTramiteOrigem = $memorando->tramitacoes->contains('origem_usuario_id', $userId);

        if (! $isRemetente && ! $isDestinatario && ! $isTramiteDestino && ! $isTramiteOrigem) {
            abort(403, 'Voce nao tem permissao para visualizar este memorando.');
        }

        // Tramitacao ativa enderecada a esse user/setor (ordenada por id desc para pegar a mais recente)
        $tramiteAtivo = $memorando->tramitacoes
            ->where('em_uso', true)
            ->sortByDesc('id')
            ->first(fn ($t) =>
                $t->destino_usuario_id === $userId
                || ($unidadeId && $t->destino_unidade_id === $unidadeId)
                || ($acessoGeral && $t->destino_unidade_id !== null)
            );

        // Destinatario original ativo (sem tramitacao ainda)
        $destinatarioAtivo = $memorando->tramitacoes->isEmpty()
            ? $memorando->destinatarios->first(fn ($d) =>
                $d->usuario_id === $userId
                || ($unidadeId && $d->unidade_id === $unidadeId)
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
            $meuStatus = ['estado' => 'remetente', 'mensagem' => 'Voce e o remetente. Aguardando recebimento do destino.'];
        } elseif ($tramiteAtivo && ! $tramiteAtivo->finalizado) {
            $meuStatus = ['estado' => 'pendente', 'mensagem' => 'Aguardando seu recebimento. Clique em "Receber" para acusar.'];
        } elseif ($tramiteAtivo && $tramiteAtivo->finalizado) {
            $meuStatus = ['estado' => 'recebido', 'mensagem' => 'Recebido em ' . $tramiteAtivo->recebido_em?->format('d/m/Y H:i') . '. Voce pode tramitar, responder ou arquivar.'];
        } elseif ($destinatarioAtivo && ! $destinatarioAtivo->lido && $destinatarioAtivo->unidade_id) {
            $meuStatus = ['estado' => 'pendente', 'mensagem' => 'Aguardando recebimento pelo setor. Clique em "Receber" para acusar.'];
        } elseif ($destinatarioAtivo) {
            $meuStatus = ['estado' => 'recebido', 'mensagem' => 'Recebido em ' . ($destinatarioAtivo->lido_em?->format('d/m/Y H:i') ?? '-') . '. Voce pode tramitar, responder ou arquivar.'];
        } elseif ($isTramiteOrigem) {
            $meuStatus = ['estado' => 'tramitou', 'mensagem' => 'Voce ja tramitou este memorando para frente. Acompanhando.'];
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

        return Inertia::render('GED/Memorandos/Show', [
            'memorando'       => $memorando,
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

        MemorandoResposta::create([
            'memorando_id' => $memorando->id,
            'usuario_id'   => Auth::id(),
            'conteudo'     => $request->input('conteudo'),
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
                if ($user->unidade_id) {
                    $q->orWhere('destino_unidade_id', $user->unidade_id);
                }
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
                    if ($user->unidade_id) {
                        $q->orWhere('unidade_id', $user->unidade_id);
                    }
                    if ($user->acesso_geral_ug) {
                        $q->orWhereNotNull('unidade_id');
                    }
                })
                ->update(['lido' => true, 'lido_em' => now()]);
        }

        $quando = now()->format('d/m/Y H:i');
        return redirect()->back()->with('success', "Recebimento confirmado em {$quando}. Agora voce pode encaminhar, responder ou arquivar.");
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

        try {
            DB::beginTransaction();

            // Identifica a tramitacao ativa anterior (se houver) — ela vai ser fechada
            $tramiteAnterior = MemorandoTramitacao::where('memorando_id', $memorando->id)
                ->where('em_uso', true)
                ->where(function ($q) use ($user) {
                    $q->where('destino_usuario_id', $user->id);
                    if ($user->unidade_id) {
                        $q->orWhere('destino_unidade_id', $user->unidade_id);
                    }
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
                        'mensagem'        => "O memorando {$memorando->numero} recebeu uma resposta junto com a tramitacao.",
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
                $usuariosNotificar = User::where('unidade_id', $destinoUnidadeId)->pluck('id')->all();
            }
            foreach (array_unique($usuariosNotificar) as $uid) {
                Notificacao::create([
                    'usuario_id'      => (int) $uid,
                    'tipo'            => 'memorando_tramitado',
                    'titulo'          => 'Memorando tramitado para voce',
                    'mensagem'        => "Memorando {$memorando->numero} - {$memorando->assunto} chegou via tramitacao.",
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

        $memorando->update([
            'status'       => 'arquivado',
            'arquivado_em' => now(),
        ]);

        return redirect()->back()->with('success', 'Memorando arquivado com sucesso.');
    }

    public function downloadPdf($id)
    {
        $memorando = Memorando::with([
            'remetente',
            'destinatarios.usuario',
            'respostas.usuario',
        ])->findOrFail($id);

        $qrCodeUrl = url("/memorandos/verificar/{$memorando->qr_code_token}");

        $data = [
            'memorando'  => $memorando,
            'qrCodeUrl'  => $qrCodeUrl,
            'ug'         => \App\Models\Ug::find($memorando->ug_id),
        ];

        $pdf = Pdf::loadView('pdf.memorando', $data);
        $pdf->setPaper('A4', 'portrait');

        $filename = "memorando-{$memorando->numero}.pdf";
        $filename = str_replace('/', '-', $filename);

        return $pdf->download($filename);
    }
}
