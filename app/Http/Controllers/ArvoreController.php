<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\GerarArvoreProcesso;
use App\Models\Processo\Arvore;
use App\Models\Processo\Processo;
use App\Models\Processo\ProcessoHistorico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/** Árvore do processo (TR 4.9.16, 4.38.27–4.38.42): pedir a geração e baixar. */
class ArvoreController extends Controller
{
    public function solicitar(Request $request, int $processoId)
    {
        $dados = $request->validate([
            'configuracao' => ['required', 'in:' . implode(',', array_keys(Arvore::CONFIGURACOES))],
            'secoes'       => ['required_if:configuracao,personalizada', 'nullable', 'array', 'min:1'],
            'secoes.*'     => ['in:' . implode(',', array_keys(Arvore::SECOES))],
        ], ['secoes.required_if' => 'Escolha o que entra na árvore personalizada.']);
        $processo = Processo::findOrFail($processoId);   // escopo de UG

        // Uma por vez por configuração: pedir de novo enquanto a anterior gera não duplica
        $andamento = Arvore::where('processo_id', $processo->id)->where('configuracao', $dados['configuracao'])
            ->whereIn('status', ['na_fila', 'gerando'])->exists();
        if ($andamento) {
            return back()->with('error', 'Já há uma árvore desta configuração sendo gerada.');
        }

        $arvore = Arvore::create([
            'processo_id'    => $processo->id,
            'configuracao'   => $dados['configuracao'],
            'opcoes'         => $dados['configuracao'] === 'personalizada' ? ['secoes' => $dados['secoes']] : null,
            'status'         => 'na_fila',
            'solicitado_por' => Auth::id(),
        ]);
        ProcessoHistorico::create([
            'processo_id' => $processo->id,
            'usuario_id'  => Auth::id(),
            'acao'        => 'arvore_solicitada',
            'detalhes'    => ['arvore_id' => $arvore->id, 'configuracao' => $arvore->configuracao],
            'ip'          => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);
        GerarArvoreProcesso::dispatch($arvore->id);

        return back()->with('success', 'Árvore pedida. Você será avisado quando estiver pronta.');
    }

    public function baixar(int $id)
    {
        $arvore = Arvore::findOrFail($id);
        $processo = Processo::findOrFail($arvore->processo_id);   // escopo de UG
        abort_unless($arvore->status === 'pronta' && $arvore->arquivo_path
            && Storage::disk('documentos')->exists($arvore->arquivo_path), 404, 'Árvore não disponível.');

        $nome = sprintf('arvore-%s-%s.%s', str_replace(['/', '\\'], '-', $processo->numero_protocolo), $arvore->configuracao, $arvore->arquivo_tipo);

        return Storage::disk('documentos')->download($arvore->arquivo_path, $nome);
    }
}
