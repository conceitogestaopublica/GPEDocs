<?php

declare(strict_types=1);

namespace App\Support;

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * FPDI/TCPDF ajustado para a assinatura PAdES:
 *
 *  - espaço maior no /Contents: o padrão do TCPDF (11742 hex = ~5,8 KB) não
 *    cabe o certificado ICP-Brasil + duas ACs. 40000 hex = 20 KB;
 *  - /Filter e /SubFilter conforme icp_brasil.pades_dicionario:
 *      pbad  → /PBAD_PAdES + /PBAD.PAdES (o que a PA_PAdES_AD_RB do ITI pede)
 *      etsi  → /Adobe.PPKLite + /ETSI.CAdES.detached (PAdES ETSI)
 *      adobe → /Adobe.PPKLite + /adbe.pkcs7.detached (o que o TCPDF gera)
 */
class FpdiAssinatura extends Fpdi
{
    protected $signature_max_length = 40000;

    private const DICIONARIOS = [
        'pbad'  => ['PBAD_PAdES', 'PBAD.PAdES'],
        'etsi'  => ['Adobe.PPKLite', 'ETSI.CAdES.detached'],
        'adobe' => ['Adobe.PPKLite', 'adbe.pkcs7.detached'],
    ];

    protected function _putsignature()
    {
        $inicio = $this->bufferlen;
        parent::_putsignature();

        [$filtro, $subfiltro] = self::DICIONARIOS[config('icp_brasil.pades_dicionario', 'etsi')] ?? self::DICIONARIOS['etsi'];
        if ($subfiltro === 'adbe.pkcs7.detached' || $this->bufferlen === $inicio) {
            return;
        }

        // Só o objeto /Sig recém-escrito é trocado; o offset dos objetos
        // seguintes ainda não foi registrado, então o xref continua certo.
        $trecho = substr($this->buffer, $inicio);
        $trecho = str_replace(
            ' /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached',
            ' /Filter /' . $filtro . ' /SubFilter /' . $subfiltro,
            $trecho,
        );
        $this->buffer = substr($this->buffer, 0, $inicio) . $trecho;
        $this->bufferlen = strlen($this->buffer);
    }
}
