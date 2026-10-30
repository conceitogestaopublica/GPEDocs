<?php

declare(strict_types=1);

namespace App\Support;

/**
 * XLSX mínimo (uma aba, cabeçalho em negrito, números como número) gerado com o ZipArchive do PHP,
 * sem biblioteca de planilha (TR 4.29.5).
 */
class Planilha
{
    /** @param list<string> $cabecalho @param list<list<scalar|null>> $linhas */
    public static function xlsx(string $aba, array $cabecalho, array $linhas): string
    {
        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $col = function (int $i): string {
            $s = '';
            for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
                $s = chr(65 + ($i - 1) % 26) . $s;
            }

            return $s;
        };
        $celula = function ($v, string $ref, bool $negrito = false) use ($esc) {
            if (is_int($v) || is_float($v) || (is_string($v) && is_numeric($v) && ! preg_match('/^0\d/', $v))) {
                return "<c r=\"{$ref}\"><v>{$v}</v></c>";
            }
            $s = $negrito ? ' s="1"' : '';

            return $v === null || $v === '' ? "<c r=\"{$ref}\"{$s}/>" : "<c r=\"{$ref}\" t=\"inlineStr\"{$s}><is><t xml:space=\"preserve\">{$esc($v)}</t></is></c>";
        };
        $xml = '';
        foreach (array_merge([$cabecalho], $linhas) as $r => $linha) {
            $xml .= '<row r="' . ($r + 1) . '">';
            foreach (array_values($linha) as $c => $v) {
                $xml .= $r === 0 ? $celula((string) $v, $col($c) . '1', true) : $celula($v, $col($c) . ($r + 1));
            }
            $xml .= '</row>';
        }
        $nomeAba = $esc(mb_substr(preg_replace('#[\\\\/?*\[\]:]#', ' ', $aba) ?: 'Dados', 0, 31));

        $arquivo = tempnam(sys_get_temp_dir(), 'xlsx');
        $z = new \ZipArchive();
        $z->open($arquivo, \ZipArchive::OVERWRITE);
        $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $z->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $nomeAba . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $z->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf fontId="0"/><xf fontId="1" applyFont="1"/></cellXfs></styleSheet>');
        $z->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $xml . '</sheetData></worksheet>');
        $z->close();
        $conteudo = (string) file_get_contents($arquivo);
        @unlink($arquivo);

        return $conteudo;
    }
}
