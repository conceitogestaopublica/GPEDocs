<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Processo\Ato;
use App\Models\Processo\AtoTipo;
use App\Models\User;
use App\Tenant\TenantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;

/**
 * Atos oficiais (TR 4.42): quem pode elaborar, numeração (geral, por tipo ou manual) e a geração
 * do PDF/A — o modelo .docx do tipo com os marcadores preenchidos (ou o texto do editor num .docx
 * montado aqui), com o QR de verificação, convertido pelo LibreOffice em PDF/A-2b.
 */
final class Atos
{
    /** Marcadores que todo modelo pode usar: ${numero}, ${ementa}, ${qrcode}… */
    public const MARCADORES = ['tipo' => 'Tipo do ato', 'numero' => 'Número', 'ano' => 'Ano', 'numero_ano' => 'Número/ano', 'ementa' => 'Ementa',
        'texto' => 'Texto do ato', 'data' => 'Data (dd/mm/aaaa)', 'data_extenso' => 'Data por extenso', 'autor' => 'Quem elaborou',
        'cargo' => 'Cargo de quem elaborou', 'setor' => 'Setor', 'orgao' => 'Órgão', 'processo' => 'Processo', 'verificacao' => 'Endereço de verificação',
        'qrcode' => 'QR code (imagem)'];

    public static function podeElaborar(?User $u, ?AtoTipo $tipo = null): bool
    {
        if (! $u || ! ($u->super_admin || $u->can('ato.elaborar'))) {
            return false;
        }
        $setores = $tipo?->setores ?? [];

        return $u->super_admin || ! $setores || array_intersect(array_map('intval', $setores), Setores::doUsuario($u)) !== [];
    }

    /** Dá o número na geração (automática) — chamar dentro da transação. */
    public static function numerar(Ato $ato): void
    {
        $tipo = $ato->tipo;
        if ($ato->numero || $tipo->numeracao === 'manual') {
            return;
        }
        DB::select('select pg_advisory_xact_lock(?)', [crc32("ato-{$ato->ug_id}-{$ato->ano}-" . ($tipo->numeracao === 'geral' ? 'geral' : $tipo->id))]);
        $q = Ato::withoutGlobalScopes()->withTrashed()->where('ug_id', $ato->ug_id)->where('ano', $ato->ano);
        $tipo->numeracao === 'geral'
            ? $q->whereIn('tipo_id', AtoTipo::where('numeracao', 'geral')->pluck('id'))
            : $q->where('tipo_id', $tipo->id);
        $ato->numero = ((int) $q->max('numero')) + 1;
        $ato->save();
    }

    /** Gera o PDF/A, guarda e devolve o caminho no disco "documentos". */
    public static function gerarPdf(Ato $ato): string
    {
        $ato->loadMissing(['tipo', 'autor', 'processo', 'ug']);
        $dir = sys_get_temp_dir() . '/ato-' . Str::random(12);
        @mkdir($dir, 0775, true);
        try {
            $docx = "{$dir}/ato.docx";
            $qr = "{$dir}/qr.png";
            file_put_contents($qr, (new \TCPDF2DBarcode(self::urlVerificacao($ato), 'QRCODE,M'))->getBarcodePngData(4, 4, [0, 0, 0]));
            $ato->tipo->modelo_path ? self::doModelo($ato, $docx, $qr) : self::doTexto($ato, $docx, $qr);

            $pdf = self::paraPdfA($docx, $dir);
            $path = TenantStorage::pasta('atos') . '/' . Str::uuid() . '.pdf';
            Storage::disk('documentos')->put($path, $pdf);
            $ato->update(['pdf_path' => $path, 'pdfa' => str_contains($pdf, 'pdfaid:part')]);

            return $path;
        } finally {
            foreach (glob("{$dir}/*") ?: [] as $arq) {
                is_dir($arq) ? exec('rm -rf ' . escapeshellarg($arq)) : @unlink($arq);
            }
            @rmdir($dir);
        }
    }

    public static function urlVerificacao(Ato $ato): string
    {
        return url("/atos/verificar/{$ato->qr_code_token}");
    }

    /** @return array<string, string> valores dos marcadores */
    public static function valores(Ato $ato): array
    {
        // Meio-dia: a data do ato vem à meia-noite UTC e viraria o dia anterior no fuso de Brasília
        $data = ($ato->data_ato ?? now())->copy()->setTime(12, 0);

        return [
            'tipo' => (string) $ato->tipo?->nome, 'numero' => (string) $ato->numero, 'ano' => (string) $ato->ano,
            'numero_ano' => $ato->numero ? "{$ato->numero}/{$ato->ano}" : '', 'ementa' => (string) $ato->ementa,
            'texto' => Variaveis::textoPuro($ato->conteudo), 'data' => $data->format('d/m/Y'), 'data_extenso' => Variaveis::dataPorExtenso($data),
            'autor' => (string) $ato->autor?->name, 'cargo' => (string) $ato->autor?->cargo,
            'setor' => (string) ($ato->unidade_id ? DB::table('ug_organograma')->where('id', $ato->unidade_id)->value('nome') : ''),
            'orgao' => (string) $ato->ug?->nome, 'processo' => (string) $ato->processo?->numero_protocolo, 'verificacao' => self::urlVerificacao($ato),
        ] + array_map('strval', $ato->campos ?? []);
    }

    private static function doModelo(Ato $ato, string $docx, string $qr): void
    {
        $modelo = dirname($docx) . '/modelo.docx';
        file_put_contents($modelo, Storage::disk('documentos')->get($ato->tipo->modelo_path));
        $t = new TemplateProcessor($modelo);
        $marcadores = $t->getVariables();
        foreach (self::valores($ato) as $k => $v) {
            if (in_array($k, $marcadores, true)) {
                $t->setValue($k, htmlspecialchars($v, ENT_XML1));
            }
        }
        if (in_array('qrcode', $marcadores, true)) {
            $t->setImageValue('qrcode', ['path' => $qr, 'width' => 90, 'height' => 90]);
        }
        $t->saveAs($docx);
    }

    private static function doTexto(Ato $ato, string $docx, string $qr): void
    {
        $w = new PhpWord();
        $w->setDefaultFontName('Liberation Sans');
        $w->setDefaultFontSize(11);
        $s = $w->addSection(['marginTop' => 1100, 'marginBottom' => 1100, 'marginLeft' => 1400, 'marginRight' => 1100]);
        $s->addText(mb_strtoupper((string) $ato->ug?->nome), ['bold' => true, 'size' => 12], ['alignment' => 'center']);
        $s->addTextBreak();
        $s->addText(mb_strtoupper($ato->identificacao()), ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $s->addTextBreak();
        $s->addText($ato->ementa, ['italic' => true], ['alignment' => 'both', 'indentation' => ['left' => 4300]]);
        $s->addTextBreak();
        try {
            Html::addHtml($s, HtmlSeguro::limpar((string) $ato->conteudo), false, false);
        } catch (\Throwable) {
            foreach (preg_split("/\n+/", Variaveis::textoPuro($ato->conteudo)) as $par) {
                $s->addText($par, null, ['alignment' => 'both']);
            }
        }
        $v = self::valores($ato);
        $s->addTextBreak();
        $cidade = (string) ($ato->ug?->enderecoArray()['cidade'] ?? '');
        $s->addText(($cidade !== '' ? $cidade . ', ' : '') . $v['data_extenso'] . '.', null, ['alignment' => 'right']);
        $s->addTextBreak(2);
        $s->addText($v['autor'], ['bold' => true], ['alignment' => 'center']);
        if ($v['cargo'] !== '') {
            $s->addText($v['cargo'], null, ['alignment' => 'center']);
        }
        $s->addTextBreak();
        $s->addImage($qr, ['width' => 70, 'height' => 70, 'alignment' => 'center']);
        $s->addText('Verifique a autenticidade em ' . $v['verificacao'], ['size' => 8, 'color' => '555555'], ['alignment' => 'center']);
        IOFactory::createWriter($w, 'Word2007')->save($docx);
    }

    /** .docx → PDF/A-2b pelo LibreOffice (perfil próprio por conversão: dá para converter em paralelo). */
    private static function paraPdfA(string $docx, string $dir): string
    {
        $filtro = 'pdf:writer_pdf_Export:{"SelectPdfVersion":{"type":"long","value":"2"}}';
        $cmd = 'HOME=' . escapeshellarg($dir) . ' soffice -env:UserInstallation=file://' . $dir . '/perfil --headless --convert-to '
            . escapeshellarg($filtro) . ' --outdir ' . escapeshellarg($dir) . ' ' . escapeshellarg($docx) . ' 2>&1';
        exec($cmd, $saida, $codigo);
        $pdf = preg_replace('/\.docx$/', '.pdf', $docx);
        if ($codigo !== 0 || ! is_file($pdf)) {
            throw new RuntimeException('Não foi possível gerar o PDF/A do ato: ' . implode(' ', $saida));
        }

        return (string) file_get_contents($pdf);
    }
}
