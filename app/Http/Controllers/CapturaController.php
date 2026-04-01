<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Documento;
use App\Models\TipoDocumental;
use App\Models\Versao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CapturaController extends Controller
{
    public function index(): Response
    {
        $tiposDocumentais = TipoDocumental::where('ativo', true)
            ->orderBy('nome')
            ->get();

        return Inertia::render('GED/Captura/Index', [
            'tipos_documentais' => $tiposDocumentais,
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'arquivos'           => ['required', 'array', 'min:1'],
            'arquivos.*'         => ['required', 'file', 'max:51200'],
            'tipo_documental_id' => ['required', 'integer', 'exists:ged_tipos_documentais,id'],
            'pasta_id'           => ['nullable', 'integer', 'exists:ged_pastas,id'],
        ]);

        try {
            DB::beginTransaction();

            $arquivos = $request->file('arquivos');
            $criados = 0;

            foreach ($arquivos as $file) {
                $path = $file->store('documentos', 'ged');
                $nome = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

                $documento = Documento::create([
                    'nome'              => $nome,
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
                    'comentario'   => 'Upload inicial via captura',
                ]);

                AuditLog::create([
                    'documento_id' => $documento->id,
                    'usuario_id'   => Auth::id(),
                    'acao'         => 'captura',
                    'detalhes'     => ['nome_original' => $file->getClientOriginalName()],
                    'ip'           => $request->ip(),
                    'user_agent'   => $request->userAgent(),
                ]);

                $criados++;
            }

            DB::commit();

            return redirect()->back()->with('success', "{$criados} documento(s) capturado(s) com sucesso.");
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao capturar documentos: ' . $e->getMessage());
        }
    }
}
