<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Processo\OficioModelo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OficioModeloController extends Controller
{
    public function index(Request $request): Response
    {
        $ugId = (int) ($request->session()->get('ug_id') ?? 0);

        $modelos = OficioModelo::with('criador:id,name')
            ->when($ugId, fn ($q) => $q->where(fn ($w) => $w->where('ug_id', $ugId)->orWhereNull('ug_id')))
            ->orderBy('nome')
            ->get();

        return Inertia::render('GED/Admin/OficiosModelos/Index', [
            'modelos' => $modelos,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome'      => ['required', 'string', 'max:200'],
            'categoria' => ['nullable', 'string', 'max:80'],
            'descricao' => ['nullable', 'string'],
            'conteudo'  => ['required', 'string'],
            'ativo'     => ['nullable', 'boolean'],
        ]);

        OficioModelo::create([
            'ug_id'      => $request->session()->get('ug_id'),
            'nome'       => $request->input('nome'),
            'categoria'  => $request->input('categoria'),
            'descricao'  => $request->input('descricao'),
            'conteudo'   => $request->input('conteudo'),
            'ativo'      => $request->boolean('ativo', true),
            'criado_por' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Modelo criado com sucesso.');
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'nome'      => ['required', 'string', 'max:200'],
            'categoria' => ['nullable', 'string', 'max:80'],
            'descricao' => ['nullable', 'string'],
            'conteudo'  => ['required', 'string'],
            'ativo'     => ['nullable', 'boolean'],
        ]);

        $modelo = OficioModelo::findOrFail($id);
        $modelo->update($request->only(['nome', 'categoria', 'descricao', 'conteudo', 'ativo']));

        return redirect()->back()->with('success', 'Modelo atualizado.');
    }

    public function destroy(int $id)
    {
        OficioModelo::findOrFail($id)->delete();
        return redirect()->back()->with('success', 'Modelo removido.');
    }

    /**
     * Recebe um arquivo .docx (Word) e devolve o conteudo convertido em HTML.
     * Usado no editor de modelos para importar um documento existente.
     */
    public function importarDocx(Request $request)
    {
        $request->validate([
            'arquivo' => ['required', 'file', 'mimes:doc,docx,txt,html,htm', 'max:20480'],
        ]);

        $arquivo = $request->file('arquivo');
        $ext = strtolower($arquivo->getClientOriginalExtension());

        // .txt e .html simples — apenas le o conteudo
        if (in_array($ext, ['txt', 'html', 'htm'])) {
            $conteudo = file_get_contents($arquivo->getRealPath());
            if ($ext === 'txt') {
                // Converte quebras de linha em paragrafos HTML
                $conteudo = '<p>' . str_replace(["\r\n\r\n", "\n\n"], '</p><p>', e($conteudo)) . '</p>';
                $conteudo = str_replace(["\r\n", "\n"], '<br>', $conteudo);
            }
            return response()->json([
                'conteudo' => $conteudo,
                'nome_arquivo' => $arquivo->getClientOriginalName(),
            ]);
        }

        // .docx / .doc — usa PhpWord para converter em HTML
        try {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($arquivo->getRealPath());
            $tmpFile = tempnam(sys_get_temp_dir(), 'oficio_html_') . '.html';
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
            $writer->save($tmpFile);
            $html = file_get_contents($tmpFile);
            @unlink($tmpFile);

            // Extrai apenas o conteudo do <body> (descarta cabecalho e estilos da pagina)
            if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $m)) {
                $conteudo = trim($m[1]);
            } else {
                $conteudo = $html;
            }

            return response()->json([
                'conteudo' => $conteudo,
                'nome_arquivo' => $arquivo->getClientOriginalName(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'erro' => 'Nao foi possivel ler o arquivo: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Lista pública (usada no Create de oficio) com os modelos
     * disponíveis para a UG ativa do usuário.
     */
    public function disponiveis(Request $request)
    {
        $ugId = (int) ($request->session()->get('ug_id') ?? 0);

        $modelos = OficioModelo::where('ativo', true)
            ->when($ugId, fn ($q) => $q->where(fn ($w) => $w->where('ug_id', $ugId)->orWhereNull('ug_id')))
            ->orderBy('nome')
            ->get(['id', 'nome', 'categoria', 'descricao', 'conteudo']);

        return response()->json($modelos);
    }
}
