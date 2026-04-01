<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Documento;
use App\Models\Versao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DocumentoController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Documento::with(['tipoDocumental', 'autor', 'pasta'])
            ->whereNull('deleted_at');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'ilike', "%{$search}%")
                  ->orWhere('descricao', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('tipo')) {
            $query->where('tipo_documental_id', $request->input('tipo'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('pasta_id')) {
            $query->where('pasta_id', $request->input('pasta_id'));
        }

        $documentos = $query->orderByDesc('updated_at')->paginate(20)->withQueryString();

        return Inertia::render('GED/Documentos/Index', [
            'documentos' => $documentos,
            'filters'    => $request->only(['search', 'tipo', 'status', 'pasta_id']),
        ]);
    }

    public function show($id): Response
    {
        $documento = Documento::with([
            'versoes.autor',
            'metadados',
            'tags',
            'auditLogs.usuario',
            'compartilhamentos',
            'fluxoInstancias.fluxo',
            'tipoDocumental',
            'autor',
            'pasta',
        ])->findOrFail($id);

        return Inertia::render('GED/Documentos/Show', [
            'documento' => $documento,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome'              => ['required', 'string', 'max:255'],
            'descricao'         => ['nullable', 'string'],
            'tipo_documental_id'=> ['required', 'integer', 'exists:ged_tipos_documentais,id'],
            'pasta_id'          => ['nullable', 'integer', 'exists:ged_pastas,id'],
            'arquivo'           => ['required', 'file', 'max:51200'],
        ]);

        try {
            DB::beginTransaction();

            $file = $request->file('arquivo');
            $path = $file->store('documentos', 'ged');

            $documento = Documento::create([
                'nome'              => $request->input('nome'),
                'descricao'         => $request->input('descricao'),
                'tipo_documental_id'=> $request->input('tipo_documental_id'),
                'pasta_id'          => $request->input('pasta_id'),
                'versao_atual'      => 1,
                'tamanho'           => $file->getSize(),
                'mime_type'         => $file->getMimeType(),
                'autor_id'          => Auth::id(),
                'status'            => 'rascunho',
            ]);

            Versao::create([
                'documento_id' => $documento->id,
                'versao'       => 1,
                'arquivo_path' => $path,
                'tamanho'      => $file->getSize(),
                'hash_sha256'  => hash_file('sha256', $file->getRealPath()),
                'autor_id'     => Auth::id(),
                'comentario'   => 'Versão inicial',
            ]);

            AuditLog::create([
                'documento_id' => $documento->id,
                'usuario_id'   => Auth::id(),
                'acao'         => 'criacao',
                'detalhes'     => ['nome' => $documento->nome],
                'ip'           => $request->ip(),
                'user_agent'   => $request->userAgent(),
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Documento criado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao criar documento: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nome'              => ['required', 'string', 'max:255'],
            'descricao'         => ['nullable', 'string'],
            'tipo_documental_id'=> ['nullable', 'integer', 'exists:ged_tipos_documentais,id'],
            'pasta_id'          => ['nullable', 'integer', 'exists:ged_pastas,id'],
            'status'            => ['nullable', 'string', 'in:rascunho,publicado,revisao,arquivado'],
        ]);

        try {
            $documento = Documento::findOrFail($id);

            $documento->update($request->only([
                'nome', 'descricao', 'tipo_documental_id', 'pasta_id', 'status',
            ]));

            AuditLog::create([
                'documento_id' => $documento->id,
                'usuario_id'   => Auth::id(),
                'acao'         => 'edicao',
                'detalhes'     => $request->only(['nome', 'descricao', 'status']),
                'ip'           => $request->ip(),
                'user_agent'   => $request->userAgent(),
            ]);

            return redirect()->back()->with('success', 'Documento atualizado com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao atualizar documento: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $documento = Documento::findOrFail($id);
            $documento->delete();

            AuditLog::create([
                'documento_id' => $documento->id,
                'usuario_id'   => Auth::id(),
                'acao'         => 'exclusao',
                'detalhes'     => ['nome' => $documento->nome],
                'ip'           => request()->ip(),
                'user_agent'   => request()->userAgent(),
            ]);

            return redirect()->back()->with('success', 'Documento excluído com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao excluir documento: ' . $e->getMessage());
        }
    }

    public function download($id)
    {
        $documento = Documento::with('versaoAtual')->findOrFail($id);
        $versao = $documento->versaoAtual;

        if (!$versao || !Storage::disk('ged')->exists($versao->arquivo_path)) {
            return redirect()->back()->with('error', 'Arquivo não encontrado.');
        }

        AuditLog::create([
            'documento_id' => $documento->id,
            'usuario_id'   => Auth::id(),
            'acao'         => 'download',
            'detalhes'     => ['versao' => $versao->versao],
            'ip'           => request()->ip(),
            'user_agent'   => request()->userAgent(),
        ]);

        return Storage::disk('ged')->download($versao->arquivo_path, $documento->nome);
    }

    public function preview($id)
    {
        $documento = Documento::with('versaoAtual')->findOrFail($id);
        $versao = $documento->versaoAtual;

        if (!$versao || !Storage::disk('ged')->exists($versao->arquivo_path)) {
            return redirect()->back()->with('error', 'Arquivo não encontrado.');
        }

        return Storage::disk('ged')->response($versao->arquivo_path, $documento->nome, [
            'Content-Type' => $documento->mime_type,
        ]);
    }
}
