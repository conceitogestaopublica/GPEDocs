<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Assinatura PAdES por atualização incremental: cada assinatura é acrescentada
 * ao FINAL do PDF, sem alterar nenhum byte já existente — as assinaturas
 * anteriores continuam cobrindo exatamente o que cobriam e seguem válidas.
 *
 * O que é acrescentado (ISO 32000-1 §7.5.6 e §12.8):
 *   - o dicionário /Sig, com /ByteRange e /Contents reservados;
 *   - o campo/widget da assinatura (invisível: o visual vai na cópia para
 *     impressão — desenhar na página seria alteração não permitida e
 *     invalidaria as anteriores);
 *   - novas versões da página, do /AcroForm e do catálogo só para referenciar
 *     o campo;
 *   - seção de referência cruzada com /Prev para a anterior (tabela clássica
 *     ou stream, conforme o arquivo já usa).
 *
 * Antes da primeira assinatura o PDF passa uma vez pelo qpdf (sem object
 * streams, sem criptografia): estrutura simples para as seções seguintes.
 * Arquivo que já tem assinatura nunca é reprocessado.
 *
 * O objeto do PDF é lido pelo qpdf (--json=2), que entende também objetos
 * comprimidos em object streams.
 */
final class PadesIncremental
{
    /** Espaço do /Contents em bytes (CMS com cadeia ICP de 3 níveis cabe com folga). */
    public const TAMANHO_CMS = 20000;

    private const DICIONARIOS = [
        'pbad'  => ['PBAD_PAdES', 'PBAD.PAdES'],
        'etsi'  => ['Adobe.PPKLite', 'ETSI.CAdES.detached'],
        'adobe' => ['Adobe.PPKLite', 'adbe.pkcs7.detached'],
    ];

    private const BR_LARGURA = 10; // dígitos de cada número do /ByteRange reservado

    /**
     * PDF com o espaço da próxima assinatura reservado no fim.
     *
     * @param  array{razao?: string, local?: string, contato?: string, nome?: string, momento?: int}  $info
     * @return array{0: string, 1: array{0:int,1:int,2:int,3:int}}  [pdf, byteRange]
     */
    public static function preparar(string $caminhoPdf, array $info = []): array
    {
        $base = self::base($caminhoPdf);
        $estrutura = self::lerEstrutura($base);

        $proximo = $estrutura['max'] + 1;
        $idSig    = $proximo++;
        $idWidget = $proximo++;

        $objetos = [];

        // Dicionário /Sig — /ByteRange e /Contents preenchidos depois
        [$filtro, $subfiltro] = self::DICIONARIOS[config('icp_brasil.pades_dicionario', 'etsi')] ?? self::DICIONARIOS['etsi'];
        $momento = $info['momento'] ?? time();
        $sig = '<< /Type /Sig /Filter /' . $filtro . ' /SubFilter /' . $subfiltro
            . ' /ByteRange [0 ' . str_repeat('9', self::BR_LARGURA) . ' ' . str_repeat('9', self::BR_LARGURA) . ' ' . str_repeat('9', self::BR_LARGURA) . ']'
            . ' /Contents <' . str_repeat('0', self::TAMANHO_CMS * 2) . '>'
            . ' /M ' . self::texto('D:' . gmdate('YmdHis', $momento) . "+00'00'");
        foreach (['nome' => 'Name', 'razao' => 'Reason', 'local' => 'Location', 'contato' => 'ContactInfo'] as $chave => $campo) {
            if (! empty($info[$chave])) {
                $sig .= ' /' . $campo . ' ' . self::texto((string) $info[$chave]);
            }
        }
        $objetos[$idSig] = $sig . ' >>';

        // Campo + widget invisível na 1ª página (F 132 = Print | Locked)
        $nomeCampo = 'Assinatura' . ($estrutura['assinaturas'] + 1) . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
        $objetos[$idWidget] = '<< /Type /Annot /Subtype /Widget /FT /Sig /T ' . self::texto($nomeCampo)
            . ' /V ' . $idSig . ' 0 R /F 132 /Rect [0 0 0 0] /P ' . $estrutura['pagina'] . ' >>';

        // Página: /Annots ganha o widget
        $objetos += self::acrescentarEmArray($estrutura, $estrutura['pagina'], '/Annots', $idWidget . ' 0 R');

        // /AcroForm: /Fields ganha o campo, /SigFlags 3
        $catalogo = $estrutura['objetos'][$estrutura['raiz']];
        $acro = $catalogo['/AcroForm'] ?? null;
        if (is_string($acro) && self::ehRef($acro)) {
            $dicAcro = $estrutura['objetos'][$acro] ?? [];
            $dicAcro['/SigFlags'] = 3;
            $objetos += self::acrescentarEmArray($estrutura, $acro, '/Fields', $idWidget . ' 0 R', $dicAcro);
        } else {
            $dicAcro = is_array($acro) ? $acro : [];
            $dicAcro['/SigFlags'] = 3;
            $campos = $dicAcro['/Fields'] ?? [];
            if (is_string($campos) && self::ehRef($campos)) {
                $objetos += self::acrescentarEmArray($estrutura, $campos, null, $idWidget . ' 0 R');
            } else {
                $dicAcro['/Fields'] = [...(array) $campos, $idWidget . ' 0 R'];
            }
            $catalogo['/AcroForm'] = $dicAcro;
            $objetos[self::num($estrutura['raiz'])] = self::valor($catalogo);
        }

        ksort($objetos);
        $pdf = self::anexar($base, $estrutura, $objetos);

        return self::fecharByteRange($pdf);
    }

    // ------------------------------------------------------------------
    // Leitura
    // ------------------------------------------------------------------

    /** Bytes do PDF base: o próprio arquivo se já assinado; senão a versão normalizada pelo qpdf. */
    private static function base(string $caminhoPdf): string
    {
        $bytes = (string) file_get_contents($caminhoPdf);
        if (! str_starts_with(ltrim(substr($bytes, 0, 1024)), '%PDF')) {
            throw new RuntimeException('Arquivo não é um PDF.');
        }
        if (preg_match('/\/ByteRange\s*\[/', $bytes)) {
            return $bytes;
        }

        $saida = tempnam(sys_get_temp_dir(), 'pades_base_');
        try {
            $p = Process::timeout(120)->run(['qpdf', '--object-streams=disable', '--decrypt', $caminhoPdf, $saida]);
            // código 3 = avisos (arquivo recuperado), ainda assim gera a saída
            if (! in_array($p->exitCode(), [0, 3], true) || ! is_file($saida) || filesize($saida) === 0) {
                throw new RuntimeException('Não foi possível preparar o PDF para assinatura: ' . trim($p->errorOutput() ?: $p->output()));
            }
            return (string) file_get_contents($saida);
        } finally {
            @unlink($saida);
        }
    }

    /**
     * @return array{objetos: array<string, mixed>, raiz: string, info: ?string, id: ?array, max: int,
     *               pagina: string, startxref: int, xref_stream: bool, assinaturas: int}
     */
    private static function lerEstrutura(string $pdf): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pades_ler_');
        file_put_contents($tmp, $pdf);
        try {
            $p = Process::timeout(120)->run(['qpdf', '--json=2', '--json-key=qpdf', '--json-key=pages', $tmp]);
            if (! in_array($p->exitCode(), [0, 3], true)) {
                throw new RuntimeException('PDF ilegível: ' . trim($p->errorOutput()));
            }
            // Objetos JSON preservados: {} (dicionário vazio) e [] (array vazio)
            // viram a mesma coisa num decode associativo
            $json = self::paraArray(json_decode($p->output(), false, 512, JSON_BIGINT_AS_STRING));
        } finally {
            @unlink($tmp);
        }
        if (! is_array($json) || empty($json['qpdf'][1])) {
            throw new RuntimeException('Não foi possível ler a estrutura do PDF.');
        }

        $objetos = [];
        foreach ($json['qpdf'][1] as $chave => $obj) {
            $ref = $chave === 'trailer' ? 'trailer' : preg_replace('/^obj:/', '', $chave);
            $objetos[$ref] = $obj['value'] ?? ($obj['stream']['dict'] ?? null);
        }
        $trailer = $objetos['trailer'] ?? [];
        $raiz = $trailer['/Root'] ?? null;
        if (! is_string($raiz) || ! isset($objetos[$raiz])) {
            throw new RuntimeException('PDF sem catálogo (/Root).');
        }
        $pagina = $json['pages'][0]['object'] ?? null;
        if (! $pagina) {
            throw new RuntimeException('PDF sem páginas.');
        }

        if (! preg_match_all('/startxref\s+(\d+)\s+%%EOF/', $pdf, $sx)) {
            throw new RuntimeException('PDF sem startxref.');
        }
        $startxref = (int) end($sx[1]);

        return [
            'objetos'     => $objetos,
            'raiz'        => $raiz,
            'info'        => $trailer['/Info'] ?? null,
            'id'          => $trailer['/ID'] ?? null,
            'max'         => max((int) ($json['qpdf'][0]['maxobjectid'] ?? 0), (int) ($trailer['/Size'] ?? 1) - 1),
            'pagina'      => $pagina,
            'startxref'   => $startxref,
            'xref_stream' => ! str_starts_with(ltrim(substr($pdf, $startxref, 20)), 'xref'),
            'assinaturas' => preg_match_all('/\/ByteRange\s*\[/', $pdf),
        ];
    }

    /**
     * Nova versão do objeto $ref com $item no fim do array $chave (ou do próprio
     * objeto, se ele é o array). Segue uma referência indireta para o array.
     *
     * @return array<int, string>
     */
    private static function acrescentarEmArray(array $estrutura, string $ref, ?string $chave, string $item, ?array $dic = null): array
    {
        $atual = $dic ?? $estrutura['objetos'][$ref] ?? null;

        if ($chave === null) { // $ref é o próprio array
            return [self::num($ref) => self::valor([...(array) $atual, $item])];
        }

        $valor = $atual[$chave] ?? [];
        if (is_string($valor) && self::ehRef($valor)) {
            $novos = self::acrescentarEmArray($estrutura, $valor, null, $item);
            if ($dic !== null) { // o dicionário também mudou (ex.: /SigFlags)
                $novos[self::num($ref)] = self::valor($atual);
            }
            return $novos;
        }
        $atual[$chave] = [...(array) $valor, $item];
        return [self::num($ref) => self::valor($atual)];
    }

    // ------------------------------------------------------------------
    // Escrita
    // ------------------------------------------------------------------

    /** @param  array<int, string>  $objetos  número => corpo do objeto */
    private static function anexar(string $base, array $estrutura, array $objetos): string
    {
        $pdf = rtrim($base, "\r\n") . "\n";
        $offsets = [];
        foreach ($objetos as $num => $corpo) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $num . " 0 obj\n" . $corpo . "\nendobj\n";
        }

        $tamanho = max(array_keys($objetos)) + 1;
        $trailer = ['/Root' => $estrutura['raiz'], '/Prev' => $estrutura['startxref']];
        if ($estrutura['info']) {
            $trailer['/Info'] = $estrutura['info'];
        }
        if ($estrutura['id']) {
            $trailer['/ID'] = $estrutura['id'];
        }

        if (! $estrutura['xref_stream']) {
            $inicioXref = strlen($pdf);
            $pdf .= "xref\n";
            foreach ($offsets as $num => $off) {
                $pdf .= $num . " 1\n" . sprintf('%010d 00000 n', $off) . "\r\n";
            }
            $pdf .= 'trailer' . "\n" . self::valor(['/Size' => max($tamanho, $estrutura['max'] + 1)] + $trailer) . "\n";
        } else {
            // O arquivo já usa xref stream: a seção nova também é stream (sem filtro)
            $numXref = $tamanho;
            $inicioXref = strlen($pdf);
            $offsets[$numXref] = $inicioXref;
            ksort($offsets);
            $dados = '';
            $indice = [];
            foreach ($offsets as $num => $off) {
                $indice[] = $num;
                $indice[] = 1;
                $dados .= chr(1) . pack('N', $off) . pack('n', 0);
            }
            $dic = ['/Type' => '/XRef', '/Size' => max($numXref + 1, $estrutura['max'] + 1), '/Index' => $indice,
                '/W' => [1, 4, 2], '/Length' => strlen($dados)] + $trailer;
            $pdf .= $numXref . " 0 obj\n" . self::valor($dic) . "\nstream\n" . $dados . "\nendstream\nendobj\n";
        }

        return $pdf . "startxref\n" . $inicioXref . "\n%%EOF\n";
    }

    /** Escreve o /ByteRange real (mesma largura do reservado) e devolve-o. */
    private static function fecharByteRange(string $pdf): array
    {
        $reservado = '/ByteRange [0 ' . str_repeat('9', self::BR_LARGURA) . ' ' . str_repeat('9', self::BR_LARGURA) . ' ' . str_repeat('9', self::BR_LARGURA) . ']';
        $posBr = strrpos($pdf, $reservado);
        $posContents = strpos($pdf, '/Contents <', (int) $posBr);
        if ($posBr === false || $posContents === false) {
            throw new RuntimeException('Espaço da assinatura não encontrado no PDF montado.');
        }
        $inicioHex = $posContents + strlen('/Contents ');           // posição do '<'
        $fimHex    = $inicioHex + self::TAMANHO_CMS * 2 + 2;        // depois do '>'
        $br = [0, $inicioHex, $fimHex, strlen($pdf) - $fimHex];

        // Mesma largura do reservado: completa com espaços antes do ']'
        $real = str_pad(sprintf('/ByteRange [0 %d %d %d', $br[1], $br[2], $br[3]), strlen($reservado) - 1, ' ') . ']';

        return [substr_replace($pdf, $real, $posBr, strlen($reservado)), $br];
    }

    // ------------------------------------------------------------------
    // Valores qpdf JSON v2 → sintaxe PDF
    // ------------------------------------------------------------------

    /** Marca de dicionário vazio (ver paraArray). */
    private const DIC = '__dicionario__';

    /** stdClass → array associativo; dicionário vazio ganha a marca DIC. */
    private static function paraArray(mixed $v): mixed
    {
        if ($v instanceof \stdClass) {
            $a = [];
            foreach (get_object_vars($v) as $k => $item) {
                $a[$k] = self::paraArray($item);
            }
            return $a === [] ? [self::DIC => true] : $a;
        }
        return is_array($v) ? array_map([self::class, 'paraArray'], $v) : $v;
    }

    private static function valor(mixed $v): string
    {
        if ($v === null) {
            return 'null';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_int($v)) {
            return (string) $v;
        }
        if (is_float($v)) {
            return rtrim(rtrim(sprintf('%.6F', $v), '0'), '.') ?: '0';
        }
        if (is_string($v)) {
            if (self::ehRef($v)) {
                return $v;
            }
            if (str_starts_with($v, '/')) {
                return $v;                                  // nome
            }
            if (str_starts_with($v, 'n:')) {
                return substr($v, 2);                       // nome com escapes
            }
            if (str_starts_with($v, 'u:')) {
                return self::texto(substr($v, 2));
            }
            if (str_starts_with($v, 'b:')) {
                return '<' . substr($v, 2) . '>';
            }
            if (is_numeric($v)) {
                return $v;                                  // número grande vindo como string
            }
            return self::texto($v);
        }
        if (is_array($v)) {
            if (array_is_list($v) && ! isset($v[self::DIC])) {
                return '[' . implode(' ', array_map([self::class, 'valor'], $v)) . ']';
            }
            $s = '<<';
            foreach ($v as $k => $item) {
                if ($k === self::DIC) {
                    continue;
                }
                $s .= ' ' . (str_starts_with((string) $k, 'n:') ? substr((string) $k, 2) : $k) . ' ' . self::valor($item);
            }
            return $s . ' >>';
        }
        throw new RuntimeException('Valor PDF não suportado.');
    }

    /** String de texto PDF: literal se ASCII imprimível, senão UTF-16BE em hex. */
    private static function texto(string $s): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $s)) {
            return '(' . strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']) . ')';
        }
        return '<FEFF' . strtoupper(bin2hex(mb_convert_encoding($s, 'UTF-16BE', 'UTF-8'))) . '>';
    }

    private static function ehRef(string $v): bool
    {
        return (bool) preg_match('/^\d+ \d+ R$/', $v);
    }

    private static function num(string $ref): int
    {
        return (int) strtok($ref, ' ');
    }
}
