<?php

declare(strict_types=1);

namespace App\Http\Controllers\Configuracao;

use App\Http\Controllers\Controller;
use App\Models\Portal\Banner;
use App\Models\Ug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BannerPortalController extends Controller
{
    public function index(int $ugId): Response
    {
        $ug = Ug::findOrFail($ugId);
        $banners = Banner::where('ug_id', $ug->id)
            ->orderBy('ordem')
            ->orderBy('id')
            ->get();

        return Inertia::render('Configuracao/Ugs/Banners', [
            'ug'      => ['id' => $ug->id, 'nome' => $ug->nome, 'codigo' => $ug->codigo, 'portal_slug' => $ug->portal_slug],
            'banners' => $banners,
        ]);
    }

    public function store(Request $request, int $ugId)
    {
        $ug = Ug::findOrFail($ugId);

        $data = $request->validate([
            'imagem'      => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:10240'],
            'titulo'      => ['nullable', 'string', 'max:200'],
            'subtitulo'   => ['nullable', 'string'],
            'link_url'    => ['nullable', 'string', 'max:500'],
            'link_label'  => ['nullable', 'string', 'max:60'],
            'ativo'       => ['nullable', 'boolean'],
        ]);

        $proximaOrdem = (int) Banner::where('ug_id', $ug->id)->max('ordem') + 1;

        Banner::create([
            'ug_id'       => $ug->id,
            'imagem_path' => $this->salvarImagem($request->file('imagem'), $ug->codigo),
            'titulo'      => $data['titulo'] ?? null,
            'subtitulo'   => $data['subtitulo'] ?? null,
            'link_url'    => $data['link_url'] ?? null,
            'link_label'  => $data['link_label'] ?? null,
            'ordem'       => $proximaOrdem,
            'ativo'       => $data['ativo'] ?? true,
        ]);

        return back()->with('success', 'Banner adicionado.');
    }

    public function update(Request $request, int $ugId, int $bannerId)
    {
        $banner = Banner::where('ug_id', $ugId)->findOrFail($bannerId);

        $data = $request->validate([
            'imagem'      => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:10240'],
            'titulo'      => ['nullable', 'string', 'max:200'],
            'subtitulo'   => ['nullable', 'string'],
            'link_url'    => ['nullable', 'string', 'max:500'],
            'link_label'  => ['nullable', 'string', 'max:60'],
            'ativo'       => ['nullable', 'boolean'],
        ]);

        $payload = [
            'titulo'     => $data['titulo'] ?? null,
            'subtitulo'  => $data['subtitulo'] ?? null,
            'link_url'   => $data['link_url'] ?? null,
            'link_label' => $data['link_label'] ?? null,
            'ativo'      => $data['ativo'] ?? false,
        ];

        if ($request->hasFile('imagem')) {
            if ($banner->imagem_path) {
                Storage::disk('documentos')->delete($banner->imagem_path);
            }
            $ug = Ug::find($ugId);
            $payload['imagem_path'] = $this->salvarImagem($request->file('imagem'), $ug->codigo);
        }

        $banner->update($payload);

        return back()->with('success', 'Banner atualizado.');
    }

    public function destroy(int $ugId, int $bannerId)
    {
        $banner = Banner::where('ug_id', $ugId)->findOrFail($bannerId);
        if ($banner->imagem_path) {
            Storage::disk('documentos')->delete($banner->imagem_path);
        }
        $banner->delete();
        return back()->with('success', 'Banner excluido.');
    }

    public function move(int $ugId, int $bannerId, string $direcao)
    {
        $banner = Banner::where('ug_id', $ugId)->findOrFail($bannerId);

        $vizinho = Banner::where('ug_id', $ugId)
            ->when($direcao === 'cima', fn ($q) => $q->where('ordem', '<', $banner->ordem)->orderByDesc('ordem'))
            ->when($direcao === 'baixo', fn ($q) => $q->where('ordem', '>', $banner->ordem)->orderBy('ordem'))
            ->first();

        if ($vizinho) {
            $temp = $banner->ordem;
            $banner->update(['ordem' => $vizinho->ordem]);
            $vizinho->update(['ordem' => $temp]);
        }

        return back();
    }

    public function imagem(int $ugId, int $bannerId)
    {
        $banner = Banner::where('ug_id', $ugId)->findOrFail($bannerId);
        if (! $banner->imagem_path || ! Storage::disk('documentos')->exists($banner->imagem_path)) {
            abort(404);
        }
        return Storage::disk('documentos')->response($banner->imagem_path);
    }

    private function salvarImagem(\Illuminate\Http\UploadedFile $file, ?string $codigo): string
    {
        $slug = $codigo ? preg_replace('/[^a-z0-9]/', '-', strtolower($codigo)) : uniqid();
        $ext = $file->getClientOriginalExtension();
        $path = 'banners/' . $slug . '-' . substr(md5(uniqid()), 0, 8) . '.' . $ext;
        Storage::disk('documentos')->put($path, file_get_contents($file->getRealPath()));
        return $path;
    }
}
