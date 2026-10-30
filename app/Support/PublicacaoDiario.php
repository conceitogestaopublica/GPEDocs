<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Diario\Edicao;
use App\Models\Diario\Materia;
use App\Models\Documento;
use App\Models\Processo\Ato;
use App\Models\User;
use App\Services\CmsAdRb;
use App\Services\PadesIncremental;
use App\Services\PadesPlaceholder;
use App\Services\SeloInstitucionalService;
use App\Tenant\TenantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Publicação da edição do Diário Oficial (TR 4.43.29, 4.43.31): número da edição, PDF único com
 * capa (brasão), sumário com as páginas e cada ato (o PDF assinado dele) com cabeçalho e rodapé
 * da edição, assinado com o e-CNPJ da prefeitura (PAdES AD-RB). Depois disso a edição não muda.
 */
final class PublicacaoDiario
{
    public static function publicar(Edicao $e, User $u): Edicao
    {
        if ($e->status !== 'pendente') {
            throw ValidationException::withMessages(['edicao' => 'Esta edição já foi publicada.']);
        }
        $materias = Materia::withoutGlobalScopes()->with('ato.tipo')->where('edicao_id', $e->id)->orderBy('ordem')->orderBy('id')->get();
        if ($materias->isEmpty()) {
            throw ValidationException::withMessages(['edicao' => 'A edição não tem atos.']);
        }
        foreach ($materias as $m) {
            if (! DiarioOficial::atoAssinado($m->ato)) {
                throw ValidationException::withMessages(['edicao' => "{$m->ato->identificacao()} não está assinado por todos."]);
            }
        }

        return DB::transaction(function () use ($e, $u, $materias) {
            DB::select('select pg_advisory_xact_lock(?)', [crc32("dof-numero-{$e->ug_id}")]);
            $e->numero = ((int) Edicao::withoutGlobalScopes()->where('ug_id', $e->ug_id)->max('numero')) + 1;
            $e->publicada_em = now();

            [$bytes, $paginas, $iniciais] = self::montarPdf($e, $materias);
            $selo = app(SeloInstitucionalService::class)->material($e->ug_id);
            if ($selo) {
                $bytes = self::assinar($bytes, $selo, $e);
            }
            $path = TenantStorage::pasta('diario') . '/edicao-' . $e->numero . '-' . $e->data->format('Y-m-d') . '-' . Str::random(6) . '.pdf';
            Storage::disk('documentos')->put($path, $bytes);

            $e->update(['numero' => $e->numero, 'status' => 'publicada', 'publicada_em' => $e->publicada_em, 'publicada_por' => $u->id,
                'pdf_path' => $path, 'hash_sha256' => hash('sha256', $bytes), 'paginas' => $paginas, 'assinada' => (bool) $selo,
                'certificado_cn' => $selo ? ($selo['meta']['subject_cn'] ?? null) : null]);
            foreach ($materias as $m) {
                $m->update(['pagina_inicial' => $iniciais[$m->id] ?? null]);
                if ($m->ato->status !== 'publicado') {
                    Ato::withoutGlobalScopes()->whereKey($m->ato_id)->update(['status' => 'publicado', 'publicado_em' => $e->publicada_em]);
                }
            }

            return $e->fresh();
        });
    }

    /** @return array{0: string, 1: int, 2: array<int, int>} bytes, total de páginas, página inicial de cada matéria */
    private static function montarPdf(Edicao $e, $materias): array
    {
        $dir = sys_get_temp_dir() . '/dof-' . Str::random(10);
        @mkdir($dir, 0775, true);
        $liberar = [];
        try {
            // PDF de cada ato: o assinado (versão atual no GED) ou o PDF/A gerado
            $fontes = [];
            foreach ($materias as $m) {
                $path = $m->ato->documento_id ? Documento::withoutGlobalScopes()->find($m->ato->documento_id)?->versaoAtual?->arquivo_path : null;
                $path ??= $m->ato->pdf_path;
                if (! $path || ! Storage::disk('documentos')->exists($path)) {
                    throw ValidationException::withMessages(['edicao' => "{$m->ato->identificacao()} não tem PDF gerado."]);
                }
                $arq = "{$dir}/ato-{$m->id}.pdf";
                file_put_contents($arq, Storage::disk('documentos')->get($path));
                $compat = PdfCompativel::caminho($arq);
                $liberar[] = [$compat, $arq];
                $fontes[$m->id] = $compat;
            }
            $contar = function (string $arq): int {
                $p = new Fpdi();

                return $p->setSourceFile($arq);
            };
            $paginasAto = array_map($contar, $fontes);

            // Capa + sumário: primeiro para saber quantas páginas ocupam, depois com as páginas certas
            // Sem o "Página N de M" do Relatorio: a numeração é a da edição inteira, escrita abaixo
            $capa = fn (array $iniciais) => \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.diario-capa', ['e' => $e, 'ug' => \App\Models\Ug::withoutGlobalScopes()->find($e->ug_id),
                'sumario' => self::linhasSumario($materias, $iniciais)])->setPaper('A4')->output();
            file_put_contents("{$dir}/capa.pdf", $capa([]));
            $paginasCapa = $contar("{$dir}/capa.pdf");
            $iniciais = [];
            $pagina = $paginasCapa + 1;
            foreach ($materias as $m) {
                $iniciais[$m->id] = $pagina;
                $pagina += $paginasAto[$m->id];
            }
            file_put_contents("{$dir}/capa.pdf", $capa($iniciais));
            $total = $pagina - 1;

            // Sem a página "Powered by TCPDF" que o TCPDF acrescenta por padrão
            $pdf = new class('P', 'mm', 'A4', true, 'UTF-8', false) extends Fpdi {
                public function __construct(...$args)
                {
                    parent::__construct(...$args);
                    $this->tcpdflink = false;   // o construtor do TCPDF religa
                }
            };
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->SetTitle("Diário Oficial — Edição nº {$e->numero}");
            $n = 0;
            $titulo = 'Diário Oficial Eletrônico — Edição nº ' . $e->numero . ' — ' . (\App\Models\Diario\Edicao::TIPOS[$e->tipo] ?? '') . ' — ' . $e->data->format('d/m/Y');
            foreach (['capa' => "{$dir}/capa.pdf", ...$fontes] as $chave => $arq) {
                $qtd = $pdf->setSourceFile($arq);
                for ($i = 1; $i <= $qtd; $i++) {
                    $tpl = $pdf->importPage($i);
                    $tam = $pdf->getTemplateSize($tpl);
                    $pdf->AddPage($tam['orientation'], [$tam['width'], $tam['height']]);
                    $pdf->useTemplate($tpl);
                    $n++;
                    $pdf->SetFont('helvetica', '', 7);
                    $pdf->SetTextColor(90, 90, 90);
                    if ($chave !== 'capa') {
                        $pdf->SetXY(10, 4);
                        $pdf->Cell($tam['width'] - 20, 4, $titulo, 0, 0, 'C');
                    }
                    $pdf->SetXY(10, $tam['height'] - 8);
                    $pdf->Cell($tam['width'] - 20, 4, "Página {$n} de {$total} · verifique em " . url("/diario/verificar/{$e->qr_code_token}"), 0, 0, 'C');
                }
            }

            return [$pdf->Output('', 'S'), $total, $iniciais];
        } finally {
            foreach ($liberar as [$compat, $orig]) {
                PdfCompativel::liberar($compat, $orig);
            }
            foreach (glob("{$dir}/*") ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }

    /** @return list<array{grupo: string, ato: string, ementa: string, pagina: ?int}> */
    private static function linhasSumario($materias, array $iniciais): array
    {
        $caminhos = DiarioOficial::caminhos();

        return $materias->map(fn (Materia $m) => ['grupo' => $caminhos[$m->estrutura_id] ?? 'Outros atos', 'ato' => $m->ato->identificacao(),
            'ementa' => $m->ato->ementa, 'pagina' => $iniciais[$m->id] ?? null])->all();
    }

    /** Assinatura da edição com o e-CNPJ (incremental, AD-RB) — mesma rotina do selo da prefeitura. */
    private static function assinar(string $bytes, array $selo, Edicao $e): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'dofsig');
        file_put_contents($tmp, $bytes);
        try {
            [$pdf, $byteRange] = PadesIncremental::preparar($tmp, [
                'nome' => $selo['meta']['subject_cn'] ?? '', 'razao' => "Diário Oficial Eletrônico — Edição nº {$e->numero}",
                'local' => 'Brasil', 'contato' => url("/diario/verificar/{$e->qr_code_token}"),
            ]);
            $atributos = CmsAdRb::atributosAssinados($selo['cert'], PadesPlaceholder::digest($pdf, $byteRange));
            $cms = CmsAdRb::envelope($selo['cert'], $selo['extracerts'] ?? [], $atributos, CmsAdRb::assinarComChave($atributos, $selo['pkey']));

            return PadesPlaceholder::embutir($pdf, $byteRange, $cms);
        } finally {
            @unlink($tmp);
        }
    }
}
