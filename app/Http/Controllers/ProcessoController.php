<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notificacao;
use App\Models\Processo\Processo;
use App\Models\Processo\ProcessoAnexo;
use App\Models\Processo\ProcessoHistorico;
use App\Models\Processo\TipoEtapa;
use App\Models\Processo\TipoProcesso;
use App\Models\Processo\Tramitacao;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProcessoController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Processo::with(['tipoProcesso', 'abertoPor', 'etapaAtual'])
            ->whereNull('deleted_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('tipo_processo_id')) {
            $query->where('tipo_processo_id', $request->input('tipo_processo_id'));
        }

        if ($request->filled('prioridade')) {
            $query->where('prioridade', $request->input('prioridade'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('numero_protocolo', 'ilike', "%{$search}%")
                  ->orWhere('assunto', 'ilike', "%{$search}%");
            });
        }

        $processos = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $tiposProcesso = TipoProcesso::where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'sigla']);

        return Inertia::render('GED/Processos/Index', [
            'processos'      => $processos,
            'tipos_processo'  => $tiposProcesso,
            'filters'        => $request->only(['search', 'status', 'tipo_processo_id', 'prioridade']),
        ]);
    }

    public function create(): Response
    {
        $tiposProcesso = TipoProcesso::where('ativo', true)
            ->with('etapas')
            ->orderBy('nome')
            ->get();

        return Inertia::render('GED/Processos/Create', [
            'tipos_processo' => $tiposProcesso,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'assunto'            => ['required', 'string', 'max:255'],
            'tipo_processo_id'   => ['required', 'integer', 'exists:proc_tipos_processo,id'],
            'descricao'          => ['nullable', 'string'],
            'dados_formulario'   => ['nullable', 'array'],
            'prioridade'         => ['nullable', 'string', 'in:baixa,normal,alta,urgente'],
            'requerente_nome'    => ['nullable', 'string', 'max:255'],
            'requerente_cpf'     => ['nullable', 'string', 'max:20'],
            'requerente_email'   => ['nullable', 'string', 'email', 'max:255'],
            'requerente_telefone'=> ['nullable', 'string', 'max:20'],
            'setor_origem'       => ['nullable', 'string', 'max:150'],
            'files'              => ['nullable', 'array'],
            'files.*'            => ['file', 'max:51200'],
        ]);

        try {
            DB::beginTransaction();

            $tipoId = (int) $request->input('tipo_processo_id');
            $tipo = TipoProcesso::findOrFail($tipoId);
            $year = date('Y');

            $lastNum = Processo::where('tipo_processo_id', $tipoId)
                ->whereYear('created_at', $year)
                ->max(DB::raw("CAST(SPLIT_PART(numero_protocolo, '/', 2) AS INTEGER)"));

            $nextNum = ($lastNum ?? 0) + 1;
            $protocolo = $tipo->sigla . '-' . $year . '/' . str_pad((string) $nextNum, 6, '0', STR_PAD_LEFT);

            $primeiraEtapa = TipoEtapa::where('tipo_processo_id', $tipoId)
                ->orderBy('ordem')
                ->first();

            $processo = Processo::create([
                'numero_protocolo'  => $protocolo,
                'tipo_processo_id'  => $tipoId,
                'assunto'           => $request->input('assunto'),
                'descricao'         => $request->input('descricao'),
                'dados_formulario'  => $request->input('dados_formulario'),
                'prioridade'        => $request->input('prioridade', 'normal'),
                'requerente_nome'   => $request->input('requerente_nome'),
                'requerente_cpf'    => $request->input('requerente_cpf'),
                'requerente_email'  => $request->input('requerente_email'),
                'requerente_telefone' => $request->input('requerente_telefone'),
                'setor_origem'      => $request->input('setor_origem'),
                'status'            => 'aberto',
                'aberto_por'        => Auth::id(),
            ]);

            $tramitacao = Tramitacao::create([
                'processo_id'     => $processo->id,
                'tipo_etapa_id'   => $primeiraEtapa?->id,
                'ordem'           => 1,
                'setor_origem'    => $request->input('setor_origem'),
                'setor_destino'   => $primeiraEtapa?->setor_destino,
                'remetente_id'    => Auth::id(),
                'destinatario_id' => $primeiraEtapa?->responsavel_id,
                'status'          => 'pendente',
                'sla_horas'       => $primeiraEtapa?->sla_horas ?? $tipo->sla_padrao_horas,
                'prazo'           => now()->addHours($primeiraEtapa?->sla_horas ?? $tipo->sla_padrao_horas),
            ]);

            $processo->update(['etapa_atual_id' => $tramitacao->id]);

            // Armazenar anexos
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('processos', 'local');

                    ProcessoAnexo::create([
                        'processo_id'   => $processo->id,
                        'tramitacao_id' => $tramitacao->id,
                        'nome'          => $file->getClientOriginalName(),
                        'arquivo_path'  => $path,
                        'tamanho'       => $file->getSize(),
                        'mime_type'     => $file->getMimeType(),
                        'hash_sha256'   => hash_file('sha256', $file->getRealPath()),
                        'enviado_por'   => Auth::id(),
                    ]);
                }
            }

            ProcessoHistorico::create([
                'processo_id' => $processo->id,
                'usuario_id'  => Auth::id(),
                'acao'        => 'abertura',
                'detalhes'    => [
                    'numero_protocolo' => $protocolo,
                    'assunto'          => $processo->assunto,
                    'tipo'             => $tipo->nome,
                ],
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Notificar destinatario
            if ($primeiraEtapa?->responsavel_id) {
                Notificacao::create([
                    'usuario_id'     => $primeiraEtapa->responsavel_id,
                    'tipo'           => 'processo',
                    'titulo'         => 'Novo processo recebido',
                    'mensagem'       => "Processo {$protocolo} - {$processo->assunto} foi encaminhado para voce.",
                    'referencia_tipo'=> 'processo',
                    'referencia_id'  => $processo->id,
                    'lida'           => false,
                ]);
            }

            DB::commit();

            return redirect("/processos/{$processo->id}")->with('success', 'Processo aberto com sucesso. Protocolo: ' . $protocolo);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao abrir processo: ' . $e->getMessage());
        }
    }

    public function show($id): Response
    {
        $processo = Processo::with([
            'tramitacoes.remetente',
            'tramitacoes.destinatario',
            'tramitacoes.recebedor',
            'anexos.enviadoPor',
            'comentarios.usuario',
            'historico.usuario',
            'tipoProcesso.etapas',
            'abertoPor',
            'concluidoPor',
        ])->findOrFail($id);

        $usuarios = User::orderBy('name')->get(['id', 'name', 'email']);

        return Inertia::render('GED/Processos/Show', [
            'processo' => $processo,
            'usuarios' => $usuarios,
        ]);
    }

    public function concluir(Request $request, $id)
    {
        $request->validate([
            'observacao_conclusao' => ['nullable', 'string'],
        ]);

        try {
            DB::beginTransaction();

            $processo = Processo::findOrFail($id);
            $processo->update([
                'status'              => 'concluido',
                'concluido_por'       => Auth::id(),
                'concluido_em'        => now(),
                'observacao_conclusao'=> $request->input('observacao_conclusao'),
            ]);

            ProcessoHistorico::create([
                'processo_id' => $processo->id,
                'usuario_id'  => Auth::id(),
                'acao'        => 'conclusao',
                'detalhes'    => [
                    'observacao' => $request->input('observacao_conclusao'),
                ],
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Processo concluido com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao concluir processo: ' . $e->getMessage());
        }
    }

    public function cancelar(Request $request, $id)
    {
        $request->validate([
            'motivo' => ['nullable', 'string'],
        ]);

        try {
            DB::beginTransaction();

            $processo = Processo::findOrFail($id);
            $processo->update([
                'status' => 'cancelado',
            ]);

            ProcessoHistorico::create([
                'processo_id' => $processo->id,
                'usuario_id'  => Auth::id(),
                'acao'        => 'cancelamento',
                'detalhes'    => [
                    'motivo' => $request->input('motivo'),
                ],
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Processo cancelado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao cancelar processo: ' . $e->getMessage());
        }
    }
}
