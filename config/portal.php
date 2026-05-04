<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Portal Cidadao — Carta de Servicos
    |--------------------------------------------------------------------------
    |
    | Domain pattern usado pelo roteamento por subdominio. O placeholder {ug}
    | sera substituido pelo `portal_slug` da Unidade Gestora.
    |
    | Exemplos:
    |   PORTAL_DOMAIN={ug}.gpedocs.com.br        (producao)
    |   PORTAL_DOMAIN={ug}.lvh.me                 (desenvolvimento — lvh.me resolve para 127.0.0.1)
    |   PORTAL_DOMAIN={ug}.ged.test               (Laragon com virtual hosts wildcard)
    |
    | Laravel compara apenas o hostname (sem porta), entao nao inclua :8000 aqui.
    |
    */
    'domain' => env('PORTAL_DOMAIN', '{ug}.lvh.me'),
];
