<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Template da URL pública do tenant
    |--------------------------------------------------------------------------
    |
    | Use {domain} como placeholder do subdomain do tenant — substituído em
    | tempo de execução por `Tenant::url()`.
    |
    | Produção:  https://{domain}.maatgpecloud.com.br
    | Dev local: http://{domain}.localhost:8080  (exige *.localhost no host
    |            ou DNS local que resolva subdomínios)
    |
    */
    'url_template' => env('TENANT_URL_TEMPLATE', 'https://{domain}.gpedocs.com.br'),

    /*
    |--------------------------------------------------------------------------
    | Tempo de vida do token SSO (segundos)
    |--------------------------------------------------------------------------
    |
    | Janela curta de propósito: o token sai do landlord, viaja em URL e é
    | consumido em até 1–2 segundos no fluxo normal. 30s acomoda redes lentas
    | e ainda é seguro contra replay.
    |
    */
    'sso_token_ttl' => (int) env('TENANT_SSO_TOKEN_TTL', 30),

    /*
    |--------------------------------------------------------------------------
    | URL do painel landlord (super-admin)
    |--------------------------------------------------------------------------
    |
    | Domínio raiz onde vive o painel de super-admin (CRUD de tenants). Usado
    | pelo banner "Operando via SSO" e pelas telas de erro de SSO para o botão
    | "voltar ao painel". O landlord roda fora do subdomain de qualquer tenant.
    |
    */
    'landlord_url' => env('LANDLORD_URL', ''),
    'dev_landlord_url' => env('DEV_LANDLORD_URL', ''),
    'dev_landlord_port' => env('DEV_LANDLORD_PORT', ''),
    'tenant_default_domain' => env('TENANT_DEFAULT_DOMAIN', ''),

];
