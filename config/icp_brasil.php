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

    /*
     * Revogação: cada nível da cadeia é conferido na LCR publicada pela AC
     * (ponto de distribuição do próprio certificado). Sem acesso à LCR a
     * assinatura é recusada, a menos que ICP_BRASIL_EXIGIR_LCR=false.
     */
    'verificar_revogacao' => env('ICP_BRASIL_VERIFICAR_LCR', true),
    'exigir_lcr'          => env('ICP_BRASIL_EXIGIR_LCR', true),
    'timeout_lcr'         => (int) env('ICP_BRASIL_TIMEOUT_LCR', 45),

    /*
     * Política das assinaturas em PDF: PA_PAdES_AD_RB v1.3 (vigente desde
     * 23/07/2025; a v1.2 foi revogada nessa data).
     *
     * Dois hashes diferentes — não confundir:
     *  - hash: o que vai no sigPolicyId da assinatura. É o campo signPolicyHash
     *    gravado no fim do próprio .der da política = SHA-256 de
     *    (signPolicyHashAlg + signPolicyInfo). O verificador do ITI recusa
     *    ("resumo criptográfico da política está incorreto") com qualquer outro.
     *  - hash_arquivo: SHA-256 do arquivo .der inteiro, o publicado na
     *    LPA_PAdES (http://politicas.icpbrasil.gov.br/LPA_PAdES.der); serve só
     *    para conferir que o arquivo baixado é o oficial.
     * Conferidos em 2026-10-28 (e o primeiro no validar.iti.gov.br). Atualize
     * tudo junto quando o ITI publicar nova versão.
     */
    'politica_pades' => [
        'oid'  => '2.16.76.1.7.1.11.1.3',
        'hash' => '23e4be4b9b362172e4ebb0e72b86a133ece5aad843d8651c6e38a0ba3f08fc60',
        'hash_arquivo' => '23da544aef71f7a75dc85fa6e17a83875741e4baef41ec178258a5c86ace54dd',
        'nome' => 'PA_PAdES_AD_RB v1.3',
        'url'  => 'http://politicas.icpbrasil.gov.br/PA_PAdES_AD_RB_v1_3.der',
    ],

    /*
     * /Filter e /SubFilter do dicionário de assinatura no PDF:
     *   pbad  → PBAD_PAdES / PBAD.PAdES (exigido pela PA_PAdES_AD_RB do ITI)
     *   etsi  → Adobe.PPKLite / ETSI.CAdES.detached (PAdES ETSI, lido pelo Adobe)
     *   adobe → Adobe.PPKLite / adbe.pkcs7.detached (legado)
     */
    'pades_dicionario' => env('ICP_BRASIL_PADES_DICIONARIO', 'etsi'),

    'politica_padrao' => [
        'oid'  => '2.16.76.1.7.1.1.2.3',
        'nome' => 'AD-RB v2 (Assinatura Digital de Referência Básica)',
        'doc'  => 'DOC-ICP-15.03',
    ],

    /*
     * ACs Raiz da ICP-Brasil aceitas como âncora para ASSINATURA de documentos
     * (repositório do ITI: gov.br/iti → Repositório → AC Raiz).
     *
     * O sha256 fixa cada raiz: o instalador recusa arquivo diferente e a
     * validação só usa como âncora o que bate com esta lista — arquivo largado
     * na pasta raiz/ não vira confiável. O ITI não publica as impressões; estas
     * foram conferidas em 2026-10-28 baixando por dois caminhos (HTTP no
     * container e HTTPS no host) com resultado idêntico.
     *
     * Fora de propósito: v10 (só certificados SSL) e v11 (assinatura de
     * código). v1/v3/v8/v9 vencidas ou revogadas. A v2 venceu em 2023 e fica
     * só para conferir documentos assinados antes disso.
     */
    'raiz' => [
        ['nome' => 'AC Raiz Brasileira v2',  'arquivo' => 'raizicpbrasilv2.pem',  'url' => 'https://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv2.crt',
            'sha256' => 'fb47d92a9909fd4fa9bec02737543e1f3514ced747407a8d9cfa397b0915067c'],
        ['nome' => 'AC Raiz Brasileira v4',  'arquivo' => 'raizicpbrasilv4.pem',  'url' => 'https://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv4.crt',
            'sha256' => 'f0c15afd258fb674e7a96e1a50ff873149364b9ec70d4d93c7a9f1eb6060d020'],
        ['nome' => 'AC Raiz Brasileira v5',  'arquivo' => 'raizicpbrasilv5.pem',  'url' => 'https://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv5.crt',
            'sha256' => 'caa53fc6091c6951887c976e378f6ef89aa6377c55d97b6475422b71ed7e9b17'],
        ['nome' => 'AC Raiz Brasileira v6',  'arquivo' => 'raizicpbrasilv6.pem',  'url' => 'https://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv6.crt',
            'sha256' => '3bdb9b509352f1d3d71c2bf64d9a38a4e6cebda27809d77f7ac476cbde6e314a'],
        ['nome' => 'AC Raiz Brasileira v7',  'arquivo' => 'raizicpbrasilv7.pem',  'url' => 'https://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv7.crt',
            'sha256' => '5657e70580eb678983f3ed7dfce091d84cae6549389a47fccda8d0e4dc2cf576'],
        ['nome' => 'AC Raiz Brasileira v12', 'arquivo' => 'raizicpbrasilv12.pem', 'url' => 'https://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv12.crt',
            'sha256' => 'd8478e37ce19c690cf657381e68fe600e4e1a042536830f06847e03e554c4b01'],
        ['nome' => 'AC Raiz Brasileira v13', 'arquivo' => 'raizicpbrasilv13.pem', 'url' => 'https://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv13.crt',
            'sha256' => '2b07d0bc02c4a6e0478ed22d0d99e8f97e1827b269097696a7feb6aad30c3ac8'],
    ],

    /*
     * Raízes extras aceitas (SHA-256), além das acima. Só para ambiente de
     * teste com AC própria — em produção fica vazio.
     */
    'raizes_extras_sha256' => array_filter(explode(',', (string) env('ICP_BRASIL_RAIZES_EXTRAS_SHA256', ''))),

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
