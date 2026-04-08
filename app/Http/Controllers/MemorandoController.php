<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notificacao;
use App\Models\Processo\Memorando;
use App\Models\Processo\MemorandoAnexo;
use App\Models\Processo\MemorandoDestinatario;
use App\Models\Processo\MemorandoResposta;
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
        $usuarios = User::where('id', '!=', Auth::id())
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('GED/Memorandos/Create', [
            'usuarios' => $usuarios,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'assunto'               => ['required', 'string', 'max:255'],
            'conteudo'              => ['required', 'string'],
            'destinatarios'         => ['required', 'array', 'min:1'],
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

            foreach ($request->input('destinatarios') as $usuarioId) {
                MemorandoDestinatario::create([
                    'memorando_id' => $memorando->id,
                    'usuario_id'   => (int) $usuarioId,
                    'lido'         => false,
                ]);
            }

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('memorandos', 'local');

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

            foreach ($request->input('destinatarios') as $usuarioId) {
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
            'anexos.enviadoPor',
            'respostas.usuario',
        ])->findOrFail($id);

        $userId = Auth::id();
        $isRemetente    = $memorando->remetente_id === $userId;
        $isDestinatario = $memorando->destinatarios->contains('usuario_id', $userId);

        if (! $isRemetente && ! $isDestinatario) {
            abort(403, 'Voce nao tem permissao para visualizar este memorando.');
        }

        if ($isDestinatario) {
            $memorando->destinatarios()
                ->where('usuario_id', $userId)
                ->where('lido', false)
                ->update([
                    'lido'    => true,
                    'lido_em' => now(),
                ]);
        }

        return Inertia::render('GED/Memorandos/Show', [
            'memorando' => $memorando,
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
        ];

        $pdf = Pdf::loadView('pdf.memorando', $data);
        $pdf->setPaper('A4', 'portrait');

        $filename = "memorando-{$memorando->numero}.pdf";
        $filename = str_replace('/', '-', $filename);

        return $pdf->download($filename);
    }
}
