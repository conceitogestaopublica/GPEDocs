<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assinatura;
use App\Models\Documento;
use App\Models\Processo\Arvore;
use App\Models\Processo\Processo;
use App\Models\Processo\ProcessoAnexo;
use App\Support\Expediente;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Árvore do processo em PDF (TR 4.9.16, 4.38.27–4.38.42): capa com sumário e, em ordem
 * cronológica, abertura, despachos, manifestações, comentários (e notas internas), anexos
 * logo depois do ato que os juntou, decisão e documento da decisão, e — na versão com
 * assinaturas — o arquivo assinado e o manifesto com o código de verificação. Todas as
 * páginas levam "Fl. N/M", o número do processo e o rodapé padrão dos relatórios; as
 * páginas geradas aqui levam também o cabeçalho padrão (pdf.partials.cabecalho-ug).
 *
 * Os arquivos são lidos na hora de gerar: cada nova árvore traz as versões atuais
 * (TR 4.38.39). PDF anexado entra inteiro (qpdf normaliza; protegido por senha vira
 * página de referência), imagem vira página, o resto vira página de referência
 * (TR 4.38.40). Comprimida reduz as imagens e recompacta o PDF; fracionada divide em
 * partes de até 5 MB num ZIP (TR 4.38.34, 4.38.35, 4.38.42).
 */
final class ArvoreDoProcesso
{
    private const LIMITE_PARTE = 5 * 1024 * 1024;
    private const LIMITE_PAGINA = 500 * 1024;

    private string $dir;
    /** @var list<array{arquivo: string, titulo: string}> */
    private array $pecas = [];
    /** @var list<array> atos de texto esperando para virar um PDF só */
    private array $trecho = [];
    /** @var list<string> */
    private array $observacoes = [];
    private ?\App\Models\Ug $ug = null;
    private string $usuario = 'Sistema';
    private string $protocolo = '';

    public function gerar(Arvore $arvore): void
    {
        $processo = Processo::query()->withoutGlobalScopes()
            ->with(['tipoProcesso', 'abertoPor:id,name', 'concluidoPor:id,name', 'tramitacoes.remetente:id,name',
                'tramitacoes.resolvedor:id,name', 'comentarios.usuario:id,name', 'anexos'])
            ->findOrFail($arvore->processo_id);
        $secoes = $arvore->secoes();
        $comprimir = in_array($arvore->configuracao, ['comprimida', 'fracionada'], true);
        $this->ug = \App\Models\Ug::withoutGlobalScopes()->find($processo->ug_id);
        $this->usuario = $arvore->solicitante?->name ?? 'Sistema';
        $this->protocolo = (string) $processo->numero_protocolo;

        $this->dir = storage_path('app/arvores-tmp/' . $arvore->id . '-' . uniqid());
        File::ensureDirectoryExists($this->dir);
        try {
            foreach ($this->linhaDoTempo($processo, $secoes) as $item) {
                match ($item['tipo']) {
                    'ato'   => $this->trecho[] = $item['ato'],
                    'anexo' => $this->anexo($item['anexo'], in_array('assinaturas', $secoes, true), $comprimir),
                    'pdf'   => $this->pdfDocumento($item['caminho'], $item['titulo']),
                };
            }
            if (in_array('assinaturas', $secoes, true)) {
                $this->manifestoAssinaturas($processo);
            }
            $this->fecharTrecho();
            if (! $this->pecas) {
                throw new RuntimeException('Nada a incluir com essa configuração.');
            }

            $final = $this->montar($processo, $arvore);
            if ($comprimir) {
                $final = $this->qpdf(['--recompress-flate', '--compression-level=9', '--object-streams=generate'], $final, 'comprimida.pdf');
            }
            $paginas = $this->paginas($final);

            if ($arvore->configuracao === 'fracionada') {
                [$arquivo, $partes] = $this->fracionar($final, $processo);
                $tipo = 'zip';
            } else {
                [$arquivo, $partes, $tipo] = [$final, 1, 'pdf'];
            }

            $destino = \App\Tenant\TenantStorage::pasta("arvores/{$processo->id}") . "/arvore-{$arvore->id}.{$tipo}";
            Storage::disk('documentos')->put($destino, file_get_contents($arquivo));
            // O worker da fila roda como root: a web precisa conseguir ler para baixar
            \App\Support\DonoDoArquivo::ajustar(Storage::disk('documentos')->path($destino));
            $arvore->update([
                'status' => 'pronta', 'arquivo_path' => $destino, 'arquivo_tipo' => $tipo, 'tamanho' => filesize($arquivo),
                'paginas' => $paginas, 'partes' => $partes, 'gerada_em' => now(), 'erro' => null,
                'observacao' => $this->observacoes ? implode("\n", array_unique($this->observacoes)) : null,
            ]);
        } finally {
            File::deleteDirectory($this->dir);
        }
    }

    /** Itens em ordem cronológica. @return list<array> */
    private function linhaDoTempo(Processo $p, array $secoes): array
    {
        $tem = fn (string $s) => in_array($s, $secoes, true);
        $itens = [];
        $add = function (?CarbonInterface $quando, int $peso, array $item) use (&$itens) {
            $itens[] = ['quando' => ($quando ?? now())->getTimestamp(), 'peso' => $peso] + $item;
        };

        $add($p->created_at, 0, ['tipo' => 'ato', 'ato' => [
            'titulo' => 'Abertura do processo',
            'meta'   => 'Em ' . $this->fmt($p->created_at) . ($p->abertoPor ? " por {$p->abertoPor->name}" : '') . ($p->setor_origem ? " · origem: {$p->setor_origem}" : ''),
            'dados'  => array_filter([
                'Número' => $p->numero_protocolo, 'Assunto' => $p->assunto, 'Tipo' => $p->tipoProcesso?->nome,
                'Requerente' => $p->requerente_nome, 'CPF/CNPJ do requerente' => \App\Support\DocumentoPessoa::mascarar($p->requerente_cpf),
                'Prioridade' => $p->prioridade,
            ]) + $this->dadosFormulario($p),
            'texto'  => $p->descricao,
        ]]);

        foreach ($p->tramitacoes as $t) {
            if ($tem('despachos') && $t->despacho && $t->despachado_em) {
                $add($t->despachado_em, 1, ['tipo' => 'ato', 'ato' => [
                    'titulo' => ($t->modo === 'copia' ? 'Cópia para ciência — ' : 'Despacho — ') . $t->setor_destino,
                    'meta'   => 'Em ' . $this->fmt($t->despachado_em) . ($t->remetente ? " por {$t->remetente->name}" : '')
                        . ($t->setor_origem ? " · de {$t->setor_origem}" : ''),
                    'selo'   => $tem('manifestacoes') ? $t->manifestacao : null,
                    'html'   => \App\Support\HtmlSeguro::limpar($t->despacho),
                ]]);
            }
            if ($tem('manifestacoes') && $t->status === 'resolvido' && $t->resolvido_em) {
                $add($t->resolvido_em, 1, ['tipo' => 'ato', 'ato' => [
                    'titulo' => "Parte resolvida — {$t->setor_destino}",
                    'meta'   => 'Em ' . $this->fmt($t->resolvido_em) . ($t->resolvedor ? " por {$t->resolvedor->name}" : ''),
                    'selo'   => $t->manifestacao,
                    'texto'  => $t->parecer,
                ]]);
            }
        }

        foreach ($p->comentarios as $c) {
            if ($c->interno ? $tem('internos') : $tem('comentarios')) {
                $add($c->created_at, 1, ['tipo' => 'ato', 'ato' => [
                    'titulo'  => $c->interno ? 'Nota interna' : 'Comentário',
                    'meta'    => 'Em ' . $this->fmt($c->created_at) . ($c->usuario ? " por {$c->usuario->name}" : ''),
                    'texto'   => $c->texto,
                    'interno' => (bool) $c->interno,
                ]]);
            }
        }

        if ($tem('anexos')) {
            foreach ($p->anexos as $a) {
                // Logo depois do ato que juntou (TR 4.38.36): chega com a tramitação
                $add($a->created_at, 2 + (int) $a->ordem, ['tipo' => 'anexo', 'anexo' => $a]);
            }
        }

        if ($tem('decisao') && $p->status === 'concluido') {
            $add($p->concluido_em, 5, ['tipo' => 'ato', 'ato' => [
                'titulo' => 'Decisão' . ($p->decisao ? ': ' . ucfirst((string) $p->decisao) : ''),
                'meta'   => 'Em ' . $this->fmt($p->concluido_em) . ($p->concluidoPor ? " por {$p->concluidoPor->name}" : ''),
                'texto'  => $p->observacao_conclusao,
            ]]);
            if ($p->documento_decisao_id && ($arquivo = $this->arquivoDoDocumento((int) $p->documento_decisao_id, in_array('assinaturas', $secoes, true)))) {
                $add($p->concluido_em, 6, ['tipo' => 'pdf', 'caminho' => $arquivo, 'titulo' => 'Documento da decisão']);
            }
        }

        usort($itens, fn ($a, $b) => [$a['quando'], $a['peso']] <=> [$b['quando'], $b['peso']]);

        return $itens;
    }

    private function anexo(ProcessoAnexo $a, bool $assinado, bool $comprimir): void
    {
        $titulo = "Anexo — {$a->nome}";
        $disco = Storage::disk('documentos');
        $caminho = ($assinado && $a->documento_id ? $this->arquivoDoDocumento((int) $a->documento_id, true) : null) ?? $a->arquivo_path;
        if (! $disco->exists($caminho)) {
            $this->fecharTrecho();
            $this->html(['modo' => 'referencia', 'anexo' => $a], $titulo . ' (arquivo não encontrado)');
            $this->observacoes[] = "Arquivo não encontrado: {$a->nome}";

            return;
        }
        $mime = (string) ($a->mime_type ?: $disco->mimeType($caminho));

        $this->fecharTrecho();
        if (str_contains($mime, 'pdf')) {
            $this->pdfDocumento($caminho, $titulo, $a);
        } elseif (in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            $this->html(['modo' => 'imagem', 'anexo' => $a, 'titulo' => $titulo, 'src' => $this->imagem($disco->get($caminho), $comprimir)], $titulo);
        } else {
            $this->html(['modo' => 'referencia', 'anexo' => $a], $titulo . ' (referência)');
            $this->observacoes[] = "Não convertido (entra como referência): {$a->nome}";
        }
    }

    /** PDF guardado no disco: normalizado pelo qpdf; protegido por senha vira referência. */
    private function pdfDocumento(string $caminho, string $titulo, ?ProcessoAnexo $anexo = null): void
    {
        $this->fecharTrecho();
        $origem = $this->dir . '/orig-' . count($this->pecas) . '.pdf';
        file_put_contents($origem, Storage::disk('documentos')->get($caminho));
        try {
            $this->pecas[] = ['arquivo' => $this->qpdf(['--object-streams=disable', '--decrypt'], $origem, 'norm-' . count($this->pecas) . '.pdf'), 'titulo' => $titulo];
        } catch (RuntimeException) {
            if ($anexo) {
                $this->html(['modo' => 'referencia', 'anexo' => $anexo], $titulo . ' (PDF protegido)');
            }
            $this->observacoes[] = "PDF que não pôde ser lido (protegido ou corrompido): {$titulo}";
        }
    }

    /** Arquivo do documento do GED: o assinado mais recente, se pedido; senão a versão atual. */
    private function arquivoDoDocumento(int $documentoId, bool $assinado): ?string
    {
        if ($assinado) {
            $a = Assinatura::where('documento_id', $documentoId)->where('status', 'assinado')
                ->whereNotNull('arquivo_assinado_path')->orderByDesc('assinado_em')->first();
            if ($a) {
                return $a->arquivo_assinado_path;
            }
        }

        return Documento::query()->withoutGlobalScopes()->with('versaoAtual')->find($documentoId)?->versaoAtual?->arquivo_path;
    }

    /** Manifesto com as assinaturas dos documentos do processo e o código de verificação. */
    private function manifestoAssinaturas(Processo $p): void
    {
        $docs = array_filter([...$p->anexos->pluck('documento_id')->all(), $p->documento_decisao_id]);
        $assinaturas = Assinatura::whereIn('documento_id', $docs)->where('status', 'assinado')
            ->with(['documento' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'nome'), 'signatario:id,name'])
            ->orderBy('assinado_em')->get();
        $this->trecho[] = [
            'titulo' => 'Manifesto de assinaturas',
            'meta'   => $assinaturas->isEmpty() ? 'Nenhum documento do processo foi assinado.' : 'Confira cada assinatura pelo código no endereço indicado.',
            'dados'  => $assinaturas->mapWithKeys(fn (Assinatura $a) => [
                ($a->documento?->nome ?? 'Documento') . ' — ' . ($a->signatario?->name ?? $a->nome_signatario) => trim(
                    ($a->tipo_assinatura ? strtoupper((string) $a->tipo_assinatura) . ' · ' : '') . $this->fmt($a->assinado_em)
                    . ($a->codigo_verificacao ? ' · código ' . $a->codigo_verificacao . ' · ' . url('/verificar/codigo/' . $a->codigo_verificacao) : '')),
            ])->all(),
        ];
    }

    /** Fecha o trecho de atos de texto num PDF. */
    private function fecharTrecho(): void
    {
        if (! $this->trecho) {
            return;
        }
        $titulos = array_column($this->trecho, 'titulo');
        $titulo = count($titulos) === 1 ? $titulos[0] : $titulos[0] . ' … ' . end($titulos);
        $this->html(['modo' => 'atos', 'atos' => $this->trecho], $titulo);
        $this->trecho = [];
    }

    private function html(array $dados, string $titulo): void
    {
        $arquivo = $this->dir . '/html-' . count($this->pecas) . '.pdf';
        file_put_contents($arquivo, $this->render($dados));
        $this->pecas[] = ['arquivo' => $arquivo, 'titulo' => $titulo];
    }

    private function render(array $dados): string
    {
        return Pdf::loadView('pdf.arvore', $dados + ['fmt' => fn ($d) => $this->fmt($d), 'ugCabecalho' => $this->ug,
            'usuario' => $this->usuario, 'protocolo' => $this->protocolo])->setPaper('A4')->output();
    }

    /** Capa + sumário + peças, numeradas "Fl. N/M". */
    private function montar(Processo $p, Arvore $arvore): string
    {
        $paginas = array_map(fn ($pc) => $this->paginas($pc['arquivo']), $this->pecas);
        $capa = $this->dir . '/capa.pdf';
        $paginasCapa = 1;
        for ($tentativa = 0; $tentativa < 3; $tentativa++) {
            $inicio = $paginasCapa + 1;
            $sumario = [];
            foreach ($this->pecas as $i => $pc) {
                $sumario[] = ['titulo' => $pc['titulo'], 'pagina' => $inicio];
                $inicio += $paginas[$i];
            }
            file_put_contents($capa, $this->render(['modo' => 'capa', 'processo' => $p, 'sumario' => $sumario,
                'configuracao' => Arvore::CONFIGURACOES[$arvore->configuracao] ?? $arvore->configuracao,
                'solicitante' => $arvore->solicitante?->name]));
            $real = $this->paginas($capa);
            if ($real === $paginasCapa) {
                break;
            }
            $paginasCapa = $real;
        }

        $juntado = $this->dir . '/juntado.pdf';
        $args = ['qpdf', '--empty', '--object-streams=disable', '--pages', $capa];
        foreach ($this->pecas as $pc) {
            $args[] = $pc['arquivo'];
        }
        $this->executar([...$args, '--', $juntado]);

        return $this->numerar($juntado, $p->numero_protocolo);
    }

    private function numerar(string $arquivo, string $protocolo): string
    {
        $pdf = new Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $total = $pdf->setSourceFile($arquivo);
        for ($n = 1; $n <= $total; $n++) {
            $tpl = $pdf->importPage($n);
            $tam = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($tam['orientation'], [$tam['width'], $tam['height']]);
            $pdf->useTemplate($tpl);
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->SetTextColor(90, 90, 90);
            $pdf->SetXY($tam['width'] - 75, 1.5);
            $pdf->Cell(63, 4, "{$protocolo} · Fl. {$n}/{$total}", 0, 0, 'R');

            // Rodapé padrão dos relatórios, também sobre as páginas dos anexos
            $base = $tam['height'] - 11;
            $pdf->SetDrawColor(51, 51, 51);
            $pdf->SetLineWidth(0.2);
            $pdf->Line(12, $base, $tam['width'] - 12, $base);
            $pdf->SetFont('helvetica', '', 6);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(12, $base + 0.8);
            $pdf->Cell(90, 3, 'GPE Cloud — Gestão Pública Eficiente', 0, 0, 'L');
            $pdf->SetXY($tam['width'] - 112, $base + 0.8);
            $pdf->Cell(100, 3, "Usuário do Sistema: {$this->usuario}", 0, 2, 'R');
            $pdf->Cell(100, 3, "Página {$n} de {$total}", 0, 0, 'R');
        }
        $saida = $this->dir . '/numerado.pdf';
        $pdf->Output($saida, 'F');

        return $saida;
    }

    /** Partes de até 5 MB num ZIP; aponta páginas acima de 500 KB. @return array{0: string, 1: int} */
    private function fracionar(string $arquivo, Processo $p): array
    {
        $total = $this->paginas($arquivo);
        $tamPagina = [];
        for ($n = 1; $n <= $total; $n++) {
            $um = $this->dir . "/pg-{$n}.pdf";
            $this->executar(['qpdf', '--empty', '--pages', $arquivo, (string) $n, '--', $um]);
            $tamPagina[$n] = filesize($um);
            if ($tamPagina[$n] > self::LIMITE_PAGINA) {
                $this->observacoes[] = "Fl. {$n} tem " . number_format($tamPagina[$n] / 1024, 0, ',', '.') . ' KB (acima de 500 KB)';
            }
            @unlink($um);
        }

        $faixas = [];
        $ini = 1;
        $soma = 0;
        for ($n = 1; $n <= $total; $n++) {
            if ($soma > 0 && $soma + $tamPagina[$n] > self::LIMITE_PARTE * 0.9) {
                $faixas[] = [$ini, $n - 1];
                [$ini, $soma] = [$n, 0];
            }
            $soma += $tamPagina[$n];
        }
        $faixas[] = [$ini, $total];

        $zip = new \ZipArchive();
        $zipArquivo = $this->dir . '/partes.zip';
        $zip->open($zipArquivo, \ZipArchive::CREATE);
        $base = str_replace(['/', '\\'], '-', $p->numero_protocolo);
        foreach ($faixas as $i => [$a, $b]) {
            $parte = $this->dir . "/parte-{$i}.pdf";
            $this->executar(['qpdf', '--empty', '--pages', $arquivo, "{$a}-{$b}", '--', $parte]);
            if (filesize($parte) > self::LIMITE_PARTE) {
                $this->observacoes[] = 'Parte ' . ($i + 1) . " (fl. {$a}–{$b}) ficou acima de 5 MB: a página é grande demais para dividir";
            }
            $zip->addFile($parte, sprintf('%s - parte %02d de %02d (fl %d-%d).pdf', $base, $i + 1, count($faixas), $a, $b));
        }
        $zip->close();

        return [$zipArquivo, count($faixas)];
    }

    /** Imagem para a página: comprimida reduz bastante; normal só limita o tamanho. */
    private function imagem(string $bytes, bool $comprimir): string
    {
        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            return 'data:image/png;base64,' . base64_encode($bytes);
        }
        $max = $comprimir ? 1200 : 2000;
        [$w, $h] = [imagesx($img), imagesy($img)];
        if ($w > $max) {
            $img = imagescale($img, $max, (int) round($h * $max / $w));
        }
        ob_start();
        imagejpeg($img, null, $comprimir ? 60 : 85);

        return 'data:image/jpeg;base64,' . base64_encode((string) ob_get_clean());
    }

    /** @param list<string> $opcoes */
    private function qpdf(array $opcoes, string $entrada, string $nomeSaida): string
    {
        $saida = $this->dir . '/' . $nomeSaida;
        $this->executar(['qpdf', ...$opcoes, $entrada, $saida]);

        return $saida;
    }

    private function paginas(string $arquivo): int
    {
        return (int) trim($this->executar(['qpdf', '--show-npages', $arquivo]));
    }

    /** @param list<string> $comando */
    private function executar(array $comando): string
    {
        $r = Process::timeout(300)->run($comando);
        // qpdf devolve 3 para "conseguiu, com avisos"
        if (! in_array($r->exitCode(), [0, 3], true)) {
            throw new RuntimeException(trim($r->errorOutput() ?: $r->output()) ?: 'Falha ao processar o PDF.');
        }

        return $r->output();
    }

    /** @return array<string, string> */
    private function dadosFormulario(Processo $p): array
    {
        $schema = collect($p->tipoProcesso?->schema_formulario ?? [])->keyBy('campo');
        $saida = [];
        foreach ((array) ($p->dados_formulario ?? []) as $chave => $v) {
            $campo = $schema[$chave] ?? null;
            $saida[(string) ($campo['label'] ?? str_replace('_', ' ', (string) $chave))] = match (true) {
                $v === null || $v === ''          => '-',
                is_bool($v)                       => $v ? 'Sim' : 'Não',
                is_array($v)                      => trim(implode(', ', array_filter([
                    trim(($v['logradouro'] ?? '') . ', ' . ($v['numero'] ?? ''), ', '), $v['complemento'] ?? null, $v['bairro'] ?? null,
                    trim(($v['cidade'] ?? '') . '/' . ($v['uf'] ?? ''), '/'), ! empty($v['cep']) ? 'CEP ' . $v['cep'] : null,
                ]))),
                ($campo['tipo'] ?? '') === 'cpf_cnpj' => (string) \App\Support\DocumentoPessoa::mascarar((string) $v),
                default                           => (string) $v,
            };
        }

        return $saida;
    }

    private function fmt(?\DateTimeInterface $d): string
    {
        return $d ? \Carbon\Carbon::instance($d)->setTimezone(Expediente::FUSO)->format('d/m/Y H:i') : '-';
    }
}
