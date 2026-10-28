<?php

declare(strict_types=1);

namespace App\Support;

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * FPDI/TCPDF com espaço maior reservado no /Contents da assinatura.
 *
 * O padrão do TCPDF (11742 hex = ~5,8 KB) não cabe um CMS com o certificado
 * ICP-Brasil do signatário + duas ACs intermediárias. 40000 hex = 20 KB.
 */
class FpdiAssinatura extends Fpdi
{
    protected $signature_max_length = 40000;
}
