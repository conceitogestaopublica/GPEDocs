<?php

declare(strict_types=1);

namespace App\Http\Controllers\Configuracao;

use App\Http\Controllers\Controller;
use App\Models\Ug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class UgController extends Controller
{
    /** @var array<int,string> Regras comuns entre store e update (com excecao do unique do codigo) */
    private function regrasComuns(?int $ignoreId = null): array
    {
        $portalSlugUnique = 'unique:ugs,portal_slug'.($ignoreId ? ",{$ignoreId}" : '');
        return [
            'portal_slug'         => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $portalSlugUnique],
            'nome'                => ['required', 'string', 'max:200'],
            'cnpj'                => ['nullable', 'string', 'max:18'],
            'cep'                 => ['nullable', 'string', 'max:9'],
            'logradouro'          => ['nullable', 'string', 'max:200'],
            'numero'              => ['nullable', 'string', 'max:20'],
            'complemento'         => ['nullable', 'string', 'max:100'],
            'bairro'              => ['nullable', 'string', 'max:100'],
            'cidade'              => ['nullable', 'string', 'max:100'],
            'uf'                  => ['nullable', 'string', 'size:2'],
            'telefone'            => ['nullable', 'string', 'max:50'],
            'email_institucional' => ['nullable', 'email', 'max:150'],
            'site'                => ['nullable', 'string', 'max:150'],
            'brasao'              => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'], // 2MB
            'remover_brasao'      => ['nullable', 'boolean'],
            'nivel_1_label'       => ['required', 'string', 'max:60'],
            'nivel_2_label'       => ['required', 'string', 'max:60'],
            'nivel_3_label'       => ['required', 'string', 'max:60'],
            'observacoes'         => ['nullable', 'string'],
        ];
    }

    public function index(): Response
    {
        $ugs = Ug::withCount(['organograma', 'usuarios'])
            ->orderByDesc('ativo')
            ->orderBy('codigo')
            ->get();

        return Inertia::render('Configuracao/Ugs/Index', [
            'ugs' => $ugs,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Configuracao/Ugs/Form', ['ug' => null]);
    }

    public function edit($id): Response
    {
        $ug = Ug::findOrFail($id);
        $payload = $ug->toArray();
        // URL temporaria do brasao (para preview no form)
        $payload['brasao_url'] = $ug->brasao_path
            ? route('configuracoes.ug.brasao', ['id' => $ug->id])
            : null;

        return Inertia::render('Configuracao/Ugs/Form', ['ug' => $payload]);
    }

    public function store(Request $request)
    {
        $rules = ['codigo' => ['required', 'string', 'max:20', 'unique:ugs,codigo']]
            + $this->regrasComuns();
        $validated = $request->validate($rules);
        $validated['portal_slug'] = $validated['portal_slug'] ?? null;

        $brasaoPath = null;
        if ($request->hasFile('brasao')) {
            $brasaoPath = $this->salvarBrasao($request->file('brasao'), $request->input('codigo'));
        }

        $dados = collect($validated)
            ->except(['brasao', 'remover_brasao'])
            ->all();
        $dados['brasao_path'] = $brasaoPath;
        $dados['ativo'] = true;

        Ug::create($dados);

        return redirect()->route('configuracoes.ugs.index')->with('success', 'UG cadastrada.');
    }

    public function update(Request $request, $id)
    {
        $ug = Ug::findOrFail($id);

        $rules = ['codigo' => ['required', 'string', 'max:20', 'unique:ugs,codigo,' . $ug->id]]
            + $this->regrasComuns($ug->id);
        $validated = $request->validate($rules);

        $dados = collect($validated)->except(['brasao', 'remover_brasao'])->all();

        // Remover brasao
        if ($request->boolean('remover_brasao') && $ug->brasao_path) {
            Storage::disk('documentos')->delete($ug->brasao_path);
            $dados['brasao_path'] = null;
        }

        // Novo brasao (substitui o anterior)
        if ($request->hasFile('brasao')) {
            if ($ug->brasao_path) {
                Storage::disk('documentos')->delete($ug->brasao_path);
            }
            $dados['brasao_path'] = $this->salvarBrasao($request->file('brasao'), $request->input('codigo'));
        }

        $ug->update($dados);

        return redirect()->route('configuracoes.ugs.index')->with('success', 'UG atualizada.');
    }

    public function destroy($id)
    {
        $ug = Ug::withCount(['organograma', 'usuarios'])->findOrFail($id);

        if ($ug->organograma_count > 0 || $ug->usuarios_count > 0) {
            return back()->with('error',
                "Não é possível excluir: {$ug->organograma_count} unidade(s) no organograma, "
                . "{$ug->usuarios_count} usuário(s) vinculado(s). Use \"Inativar\" no lugar."
            );
        }

        if ($ug->brasao_path) {
            Storage::disk('documentos')->delete($ug->brasao_path);
        }

        $ug->delete();
        return back()->with('success', 'UG excluída.');
    }

    public function toggleAtivo($id)
    {
        $ug = Ug::findOrFail($id);
        $ug->update(['ativo' => ! $ug->ativo]);

        return back()->with('success', 'UG ' . ($ug->ativo ? 'reativada' : 'inativada') . '.');
    }

    /**
     * Serve o brasao como imagem (uso na tela de cadastro e nos PDFs HTML).
     */
    public function brasao($id)
    {
        $ug = Ug::findOrFail($id);

        if (! $ug->brasao_path || ! Storage::disk('documentos')->exists($ug->brasao_path)) {
            abort(404);
        }

        return Storage::disk('documentos')->response($ug->brasao_path);
    }

    private function salvarBrasao(\Illuminate\Http\UploadedFile $file, ?string $codigo): string
    {
        $slug = $codigo ? preg_replace('/[^a-z0-9]/', '-', strtolower($codigo)) : uniqid();
        $ext = $file->getClientOriginalExtension();
        $path = 'brasoes/' . $slug . '-' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
        Storage::disk('documentos')->put($path, file_get_contents($file->getRealPath()));
        return $path;
    }
}
