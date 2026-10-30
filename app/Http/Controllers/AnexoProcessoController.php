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
    /** Tela de revisão do PDF/planta com anotações e medição (TR 4.48.16–4.48.18, 4.48.39). */
    public function revisar(int $anexoId)
    {
        $anexo = ProcessoAnexo::findOrFail($anexoId);
        $processo = Processo::findOrFail($anexo->processo_id);   // escopo de UG
        abort_unless(str_contains((string) $anexo->mime_type, 'pdf') || str_ends_with(mb_strtolower($anexo->nome), '.pdf'), 422, 'Só PDF pode ser revisado.');
        $user = Auth::user();
        $pode = $user->super_admin || AcaoNoProcesso::avaliar($processo, $user)['despachar'];
        $rascunho = DB::table('proc_anexo_revisoes')->where('anexo_id', $anexo->id)->where('autor_id', $user->id)->whereNull('resultado')->latest('id')->first();

        return \Inertia\Inertia::render('GED/Processos/RevisarAnexo', [
            'anexo' => ['id' => $anexo->id, 'nome' => $anexo->nome, 'url' => "/processos/anexos/{$anexo->id}/download?inline=1", 'avaliacao' => $anexo->avaliacao],
            'processo' => ['id' => $processo->id, 'numero' => $processo->numero_protocolo, 'assunto' => $processo->assunto, 'requerente_email' => $processo->requerente_email],
            'rascunho' => $rascunho ? ['anotacoes' => json_decode((string) $rascunho->anotacoes, true) ?: [], 'escala' => json_decode((string) $rascunho->escala, true) ?: [],
                'precisao' => (int) $rascunho->precisao] : null,
            'revisoes' => DB::table('proc_anexo_revisoes as r')->join('users as u', 'u.id', '=', 'r.autor_id')->where('r.anexo_id', $anexo->id)->whereNotNull('r.resultado')
                ->orderByDesc('r.id')->get(['r.resultado', 'r.motivo', 'r.concluida_em', 'r.anexo_gerado_id', 'u.name']),
            'pode' => $pode, 'usuario' => $user->name,
        ]);
    }

    /** Salva o rascunho da revisão (anotações, escala, precisão). */
    public function salvarRevisao(Request $request, int $anexoId)
    {
        $anexo = ProcessoAnexo::findOrFail($anexoId);
        $this->comProcessoNaMao($anexo->processo_id);
        $d = $this->dadosRevisao($request);
        $id = DB::table('proc_anexo_revisoes')->where('anexo_id', $anexo->id)->where('autor_id', Auth::id())->whereNull('resultado')->value('id');
        $valores = ['anotacoes' => json_encode($d['anotacoes'], JSON_UNESCAPED_UNICODE), 'escala' => json_encode($d['escala']), 'precisao' => $d['precisao'], 'updated_at' => now()];
        $id ? DB::table('proc_anexo_revisoes')->where('id', $id)->update($valores)
            : DB::table('proc_anexo_revisoes')->insert($valores + ['anexo_id' => $anexo->id, 'processo_id' => $anexo->processo_id, 'autor_id' => Auth::id(), 'created_at' => now()]);

        return back()->with('success', 'Revisão salva.');
    }

    /**
     * Conclui: gera o PDF com as anotações como novo anexo (o original fica), avalia o original
     * (aceito → aprovado; recusado → reprovado com motivo) e, recusado, manda a cópia ao requerente.
     */
    public function concluirRevisao(Request $request, int $anexoId)
    {
        $anexo = ProcessoAnexo::findOrFail($anexoId);
        $processo = $this->comProcessoNaMao($anexo->processo_id);
        $d = $this->dadosRevisao($request) + $request->validate(['resultado' => ['required', 'in:aceito,recusado'],
            'motivo' => ['required_if:resultado,recusado', 'nullable', 'string', 'max:2000'], 'enviar_email' => ['nullable', 'boolean']],
            ['motivo.required_if' => 'Informe o motivo da recusa.']);
        $user = Auth::user();
        $quando = now()->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i');
        $rodape = "Revisão de {$user->name} em {$quando} — " . ($d['resultado'] === 'aceito' ? 'ACEITO' : 'RECUSADO') . " · original: {$anexo->nome} · {$processo->numero_protocolo}";

        $tmp = tempnam(sys_get_temp_dir(), 'rev');
        file_put_contents($tmp, Storage::disk('documentos')->get($anexo->arquivo_path));
        try {
            $bytes = \App\Support\RevisaoPdf::aplicar($tmp, $d['anotacoes'], $d['escala'], $d['precisao'], $rodape);
        } catch (\RuntimeException $e) {
            report($e);

            return back()->with('error', 'Não foi possível gerar o PDF revisado deste arquivo.');
        } finally {
            @unlink($tmp);
        }
        $nome = preg_replace('/\.pdf$/i', '', $anexo->nome) . ' — revisado (' . $d['resultado'] . ').pdf';

        $gerado = DB::transaction(function () use ($request, $anexo, $processo, $d, $bytes, $nome, $user) {
            $path = \App\Tenant\TenantStorage::pasta('processos') . '/' . \Illuminate\Support\Str::uuid() . '.pdf';
            Storage::disk('documentos')->put($path, $bytes);
            $gerado = ProcessoAnexo::create(['processo_id' => $processo->id, 'tramitacao_id' => $processo->etapa_atual_id, 'nome' => mb_substr($nome, 0, 255),
                'arquivo_path' => $path, 'tamanho' => strlen($bytes), 'mime_type' => 'application/pdf', 'hash_sha256' => hash('sha256', $bytes), 'enviado_por' => $user->id]);
            $anexo->update(['avaliacao' => $d['resultado'] === 'aceito' ? 'aprovado' : 'reprovado', 'avaliacao_motivo' => $d['motivo'] ?? null,
                'avaliado_por' => $user->id, 'avaliado_em' => now()]);
            $valores = ['anotacoes' => json_encode($d['anotacoes'], JSON_UNESCAPED_UNICODE), 'escala' => json_encode($d['escala']), 'precisao' => $d['precisao'],
                'resultado' => $d['resultado'], 'motivo' => $d['motivo'] ?? null, 'anexo_gerado_id' => $gerado->id, 'concluida_em' => now(), 'updated_at' => now()];
            $id = DB::table('proc_anexo_revisoes')->where('anexo_id', $anexo->id)->where('autor_id', $user->id)->whereNull('resultado')->value('id');
            $id ? DB::table('proc_anexo_revisoes')->where('id', $id)->update($valores)
                : DB::table('proc_anexo_revisoes')->insert($valores + ['anexo_id' => $anexo->id, 'processo_id' => $processo->id, 'autor_id' => $user->id, 'created_at' => now()]);
            $this->historico($request, $processo->id, 'anexo_revisado', ['anexo' => $anexo->nome, 'resultado' => $d['resultado'], 'motivo' => $d['motivo'] ?? null,
                'anotacoes' => count($d['anotacoes']), 'gerado' => $gerado->nome]);

            return $gerado;
        });

        $email = $d['resultado'] === 'recusado' && $request->boolean('enviar_email', true) && $processo->requerente_email
            && filter_var($processo->requerente_email, FILTER_VALIDATE_EMAIL)
            && \App\Support\RastreioEmail::enviar(new \App\Mail\AnexoRecusado($processo, $anexo->nome, (string) $d['motivo'], $bytes, $nome),
                $processo->requerente_email, 'cidadao', "Documento recusado: {$processo->numero_protocolo}", 'processo', $processo->id, $user->id);

        return redirect("/processos/{$processo->id}")->with('success', "Revisão concluída: \"{$gerado->nome}\" anexado ao processo"
            . ($email ? ' e a cópia foi enviada ao requerente.' : '.'));
    }

    /** @return array{anotacoes: list<array<string, mixed>>, escala: array<string, float>, precisao: int} */
    private function dadosRevisao(Request $request): array
    {
        $d = $request->validate([
            'anotacoes' => ['present', 'array', 'max:500'], 'anotacoes.*.tipo' => ['required', 'in:texto,retangulo,elipse,linha,seta,caneta,marca,selo,medida,area'],
            'anotacoes.*.pagina' => ['required', 'integer', 'min:1'], 'escala' => ['nullable', 'array'], 'escala.*' => ['numeric', 'min:0'],
            'precisao' => ['nullable', 'integer', 'between:0,4'],
        ]);

        // validate() devolve só as chaves validadas (sumiria x, y, pontos…): lê a entrada e limpa cada anotação
        $num = fn ($v) => is_numeric($v) ? (float) $v : null;
        $anotacoes = array_map(fn ($a) => array_filter([
            'id' => mb_substr((string) ($a['id'] ?? ''), 0, 40), 'tipo' => $a['tipo'], 'pagina' => (int) $a['pagina'],
            'x' => $num($a['x'] ?? null), 'y' => $num($a['y'] ?? null), 'w' => $num($a['w'] ?? null), 'h' => $num($a['h'] ?? null),
            'pontos' => isset($a['pontos']) && is_array($a['pontos'])
                ? array_values(array_map(fn ($p) => [(float) ($p[0] ?? 0), (float) ($p[1] ?? 0)], array_slice($a['pontos'], 0, 2000))) : null,
            'texto' => isset($a['texto']) ? mb_substr(strip_tags((string) $a['texto']), 0, 1000) : null,
            'cor' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($a['cor'] ?? '')) ? $a['cor'] : null,
            'espessura' => $num($a['espessura'] ?? null), 'tamanho' => $num($a['tamanho'] ?? null),
            'selo' => in_array($a['selo'] ?? null, ['aprovado', 'reprovado', 'revisar'], true) ? $a['selo'] : null,
            'mm' => $num($a['mm'] ?? null), 'mm2' => $num($a['mm2'] ?? null),
        ], fn ($v) => $v !== null), array_values((array) $request->input('anotacoes', [])));

        return ['anotacoes' => $anotacoes, 'escala' => array_map('floatval', $d['escala'] ?? []), 'precisao' => (int) ($d['precisao'] ?? 2)];
    }

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
