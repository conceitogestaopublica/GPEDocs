<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notificacao;
use App\Models\Processo\Processo;
use App\Models\Processo\ProcessoAnexo;
use App\Models\Processo\ProcessoHistorico;
use App\Services\AcaoNoProcesso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Anexos do processo (TR 4.14.5, 4.20.2, 4.20.3, 4.38.24–4.38.26): ordenar, aprovar ou
 * reprovar com motivo, baixar todos em ZIP (contando os downloads) e gerar link seguro
 * com validade para quem não tem acesso ao sistema.
 */
class AnexoProcessoController extends Controller
{
    /** Ordem dos anexos — quem está com o processo (TR 4.38.24). */
    public function ordenar(Request $request, int $processoId)
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        $processo = $this->comProcessoNaMao($processoId);

        DB::transaction(function () use ($request, $processo, $ids) {
            foreach (array_values($ids) as $i => $id) {
                ProcessoAnexo::where('processo_id', $processo->id)->whereKey($id)->update(['ordem' => $i + 1]);
            }
            $this->historico($request, $processo->id, 'anexos_reordenados', ['ordem' => array_map('intval', $ids)]);
        });

        return back()->with('success', 'Ordem dos anexos salva.');
    }

    /** Aprovar ou reprovar um anexo, com motivo na reprovação (TR 4.38.26). */
    public function avaliar(Request $request, int $anexoId)
    {
        $dados = $request->validate([
            'avaliacao' => ['required', 'in:aprovado,reprovado,pendente'],
            'motivo'    => ['required_if:avaliacao,reprovado', 'nullable', 'string', 'max:2000'],
        ], ['motivo.required_if' => 'Informe o motivo da reprovação.']);
        $anexo = ProcessoAnexo::findOrFail($anexoId);
        $processo = $this->comProcessoNaMao($anexo->processo_id);

        DB::transaction(function () use ($request, $dados, $anexo, $processo) {
            $pendente = $dados['avaliacao'] === 'pendente';
            $anexo->update([
                'avaliacao'        => $pendente ? null : $dados['avaliacao'],
                'avaliacao_motivo' => $pendente ? null : ($dados['motivo'] ?? null),
                'avaliado_por'     => $pendente ? null : Auth::id(),
                'avaliado_em'      => $pendente ? null : now(),
            ]);
            $this->historico($request, $processo->id, 'anexo_avaliado',
                ['anexo_id' => $anexo->id, 'anexo' => $anexo->nome, 'avaliacao' => $dados['avaliacao'], 'motivo' => $dados['motivo'] ?? null]);

            // Quem enviou fica sabendo da reprovação
            if ($dados['avaliacao'] === 'reprovado' && $anexo->enviado_por && $anexo->enviado_por !== Auth::id()) {
                Notificacao::create([
                    'usuario_id'      => $anexo->enviado_por,
                    'tipo'            => 'processo',
                    'titulo'          => 'Anexo reprovado',
                    'mensagem'        => "\"{$anexo->nome}\" do processo {$processo->numero_protocolo} foi reprovado: {$dados['motivo']}",
                    'referencia_tipo' => 'processo',
                    'referencia_id'   => $processo->id,
                    'lida'            => false,
                ]);
            }
        });

        return back()->with('success', match ($dados['avaliacao']) {
            'aprovado' => 'Anexo aprovado.', 'reprovado' => 'Anexo reprovado.', default => 'Avaliação retirada.',
        });
    }

    /** Todos os anexos num ZIP (TR 4.38.25); cada um conta um download. */
    public function zip(int $processoId)
    {
        $processo = Processo::findOrFail($processoId);   // escopo de UG
        $anexos = $processo->anexos()->get();
        abort_if($anexos->isEmpty(), 404, 'O processo não tem anexos.');

        $tmp = tempnam(sys_get_temp_dir(), 'anexos');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $usados = [];
        $disco = Storage::disk('documentos');
        foreach ($anexos as $i => $a) {
            if (! $disco->exists($a->arquivo_path)) {
                continue;
            }
            // Nomes iguais não se sobrescrevem no ZIP; o número dá a ordem
            $nome = sprintf('%02d - %s', $i + 1, preg_replace('/[\\\\\/:*?"<>|]/', '_', $a->nome));
            while (isset($usados[mb_strtolower($nome)])) {
                $nome = preg_replace('/(\.[^.]+)?$/', ' (2)$1', $nome, 1);
            }
            $usados[mb_strtolower($nome)] = true;
            $zip->addFromString($nome, $disco->get($a->arquivo_path));
        }
        $zip->close();
        ProcessoAnexo::whereIn('id', $anexos->pluck('id'))->increment('downloads');

        $arquivo = 'anexos-' . str_replace(['/', '\\'], '-', $processo->numero_protocolo) . '.zip';

        return response()->download($tmp, $arquivo, ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    /** Link seguro com validade para mandar a quem não usa o sistema (TR 4.20.2). */
    public function link(Request $request, int $anexoId)
    {
        $horas = (int) $request->validate(['horas' => ['required', 'integer', 'between:1,168']])['horas'];
        $anexo = ProcessoAnexo::findOrFail($anexoId);
        $processo = Processo::findOrFail($anexo->processo_id);   // escopo de UG

        $ate = now()->addHours($horas);
        $url = URL::temporarySignedRoute('anexos.compartilhado', $ate, ['anexo' => $anexo->id]);
        $this->historico($request, $processo->id, 'link_anexo', ['anexo_id' => $anexo->id, 'anexo' => $anexo->nome,
            'valido_ate' => $ate->copy()->setTimezone(\App\Support\Expediente::FUSO)->format('d/m/Y H:i')]);

        return response()->json(['url' => $url, 'valido_ate' => $ate->toIso8601String()]);
    }

    /** Quem recebeu o link abre o anexo, sem login, até a validade (rota assinada). */
    public function compartilhado(int $anexo)
    {
        $a = ProcessoAnexo::findOrFail($anexo);
        ProcessoAnexo::whereKey($a->id)->increment('downloads');
        \App\Support\Acessos::registrar('processo', (int) $a->processo_id, null, 'Link seguro de anexo: ' . $a->nome);

        return \App\Support\Anexos::baixar($a->arquivo_path, $a->nome);
    }

    /** Processo de quem está com ele no fluxo (ou super admin); senão 403. */
    private function comProcessoNaMao(int $processoId): Processo
    {
        $processo = Processo::findOrFail($processoId);
        $user = Auth::user();
        abort_unless($user->super_admin || AcaoNoProcesso::avaliar($processo, $user)['despachar'], 403,
            'Só quem está com o processo organiza e avalia os anexos.');

        return $processo;
    }

    private function historico(Request $request, int $processoId, string $acao, array $detalhes): void
    {
        ProcessoHistorico::create([
            'processo_id' => $processoId,
            'usuario_id'  => Auth::id(),
            'acao'        => $acao,
            'detalhes'    => $detalhes,
            'ip'          => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);
    }
}
