<?php

declare(strict_types=1);

/**
 * Configuração da cadeia ICP-Brasil.
 *
 * Os certificados sao publicados pelo ITI em https://estrutura.iti.gov.br
 * Atualize a versao da AC Raiz quando o ITI emitir uma nova (a V10 esta
 * vigente desde 2024 e expira em 2034).
 *
 * O comando `php artisan icp-brasil:install-truststore` baixa cada URL
 * abaixo e armazena em storage/app/private/icp-brasil/{raiz,intermediarias}.
 */
return [

    'truststore_path' => storage_path('app/private/icp-brasil'),

    'politica_padrao' => [
        'oid'  => '2.16.76.1.7.1.1.2.3',
        'nome' => 'AD-RB v2 (Assinatura Digital de Referência Básica)',
        'doc'  => 'DOC-ICP-15.03',
    ],

    /*
     * AC Raiz da ICP-Brasil — geração vigente.
     * Formato esperado: DER (.crt) ou PEM (.pem). O comando converte automaticamente.
     */
    'raiz' => [
        // Apenas a V10 (vigente desde 2024, valida ate 2032-07-01) e publicada
        // ativamente pelo ITI. As geracoes anteriores foram retiradas do servidor
        // mas seus certs emitidos antes da migracao podem ainda ser validos.
        // Coloque manualmente eventuais .pem antigos em raiz/ se precisar
        // suportar assinaturas legadas.
        [
            'nome' => 'AC Raiz da ICP-Brasil v10',
            'url'  => env('ICP_BRASIL_RAIZ_V10_URL', 'https://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv10.crt'),
            'arquivo' => 'raizicpbrasilv10.pem',
        ],
    ],

    /*
     * ACs intermediarias mais comuns no Brasil. O admin pode acrescentar outras
     * por env (ICP_BRASIL_AC_EXTRAS, JSON com lista de URLs) ou colocar os PEMs
     * direto em storage/app/private/icp-brasil/intermediarias/.
     */
    'intermediarias' => [
        // As intermediarias vivem sob a AC Raiz V10 — uma lista nao exaustiva
        // que cobre os principais emissores de e-CPF/e-CNPJ no Brasil. O ITI
        // mantém a relacao oficial em https://estrutura.iti.gov.br/.
        // Adicione aqui as ACs que seus signatarios tipicamente usam.
    ],

];
