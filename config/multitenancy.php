<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Template da URL pública do tenant
    |--------------------------------------------------------------------------
    |
    | Use {subdomain} e {domain} como placeholders — substituídos em tempo de
    | execução por `Tenant::url()` pelas colunas homônimas do tenant.
    |
    | Produção:  https://{subdomain}.{domain}   (ex: https://paraguacu.gpedocs.com.br)
    | Dev local: não usado — tenant com domain ':<porta>' vira http://localhost:<porta>
    |
    */
    'url_template' => env('TENANT_URL_TEMPLATE', '{subdomain}.{domain}'),

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
    | URL do painel landlord (super-admin) — vive SÓ no gpe2
    |--------------------------------------------------------------------------
    |
    | Este projeto não tem painel landlord: /landlord/* e tenant não encontrado
    | redirecionam para cá. Também é o destino do botão "voltar ao painel" do
    | banner SSO. Sem valor, esses casos viram 404.
    |
    | Produção:  https://admin.maatgpecloud.com.br
    | Dev local: http://localhost:8080   (web-gpe do stack gpe2)
    |
    */
    'landlord_url' => env('LANDLORD_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Domínio base dos tenants do GPEDocs
    |--------------------------------------------------------------------------
    |
    | O landlord é compartilhado com o gpe2 — o que separa os tenants do GPEDocs
    | é a coluna `domain`. Os comandos console (trait TenantAware — tenant:migrate,
    | etc.) operam SÓ nos tenants cuja coluna `domain` casa com este valor (LIKE),
    | assim nunca tocam bancos do gpe2. Também identifica o apex no ResolveTenant.
    |
    | Produção: TENANT_DOMINIO_BASE=gpedocs.com.br
    | Dev local: ':<APP_PORT>' (default ':8090') — a porta em que o nginx-docs escuta
    |
    */
    'dominio_base' => env('TENANT_DOMINIO_BASE', ':8090'),

];
