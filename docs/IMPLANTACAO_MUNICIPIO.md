# Implantação de município no GPEDocs

Monta o banco de um município a partir do gpe2 (estrutura real) e, se quiser,
com conteúdo de demonstração para prova de conceito. Exemplo real: Santo
Antônio do Amparo, montado em 06/10/2026.

## Como o GPEDocs acha o município

O catálogo de tenants é o **mesmo do gpe2** (`gpe_registry.tenants`, conexão
`landlord`). O GPEDocs só enxerga as linhas com `domain` igual a
`TENANT_DOMINIO_BASE`. Cada município tem **banco próprio** (`docs_<município>`)
no PostgreSQL do stack gpe — o `docker-compose.yml` do GPEDocs não tem banco.

| Ambiente | subdomain | domain | db_host |
| --- | --- | --- | --- |
| Local (Docker, porta 8090) | `localhost` | `:8090` | `db-gpe`, porta `5432` (container do gpe2, rede `gpe_gpe`) |
| Produção | `santoantoniodoamparo` | `gpedocs.com.br` | `postgres-gpe`, porta `5432` |

Os arquivos ficam em `tenants/<db_name>/…` no disco `documentos` — o nome do
banco é o mesmo nos dois ambientes, então banco e arquivos viajam juntos.

## Montar do zero (local, em Docker)

O GPEDocs roda no mesmo Docker do gpe2: o stack do GED (`app-docs`, `nginx-docs`,
`queue-docs`) entra na rede `gpe_gpe` e usa o banco do container `db-gpe`.
O stack do gpe2 precisa estar no ar.

`.env` do GPEDocs (as versões anteriores ficaram em `.env.bak-antes-docs-santoantonio`
e `.env.bak-antes-docker`):

```
APP_PORT=8090
APP_URL=http://localhost:8090
DB_HOST=db-gpe
DB_PORT=5432
DB_DATABASE=docs_santoantoniodoamparo
LANDLORD_DB_DATABASE=gpe_registry
TENANT_DOMINIO_BASE=:8090
# origem da estrutura: banco do município no gpe2
GPE_LEGADO_DRIVER=pgsql
GPE_LEGADO_HOST=db-gpe
GPE_LEGADO_PORT=5432
GPE_LEGADO_DATABASE=gpesantoantoniodoamparo
```

Não deixar `public/hot` na pasta do projeto: ele faz o navegador buscar o
JavaScript num servidor Vite (o container `vite` só sobe se for pedido).

Telas (JSX/CSS): o app e o nginx leem o `public/build` da pasta do projeto, como no
servidor. Depois de alterar o front, rodar `npm run build` no host — não é preciso
reconstruir a imagem.

1. Subir: `docker compose up -d --build app nginx queue` → http://localhost:8090
2. Tabelas de fila do GPEDocs no catálogo (uma vez):
   `docker exec app-docs php artisan migrate --database=landlord --path=database/migrations/landlord --force`
3. Linha do tenant no `gpe_registry.tenants` (subdomain/domain/db_host da tabela acima,
   `driver=pgsql`, `db_name=docs_santoantoniodoamparo`, `cnpj` só dígitos).
4. Banco, schema, estrutura base e super admins do gpe2:
   `docker exec app-docs php artisan docs:template-restore --tenant=<id> --legado=gpesantoantoniodoamparo --force`
5. Estrutura real (UGs, organograma, usuários ativos com a mesma senha do gpe2):
   `docker exec app-docs php artisan docs:importar-estrutura --tenant=<id> --legado=gpesantoantoniodoamparo`
6. Conteúdo de demonstração (opcional — só em banco recém-criado):
   `docker exec app-docs php artisan docs:demonstracao --tenant=<id> --ug=UG-002`

Os arquivos ficam no volume Docker `gpedocs_storage`, em
`/var/www/html/storage/app/private/tenants/<db_name>` dentro do container.
Refazer tudo: `docker exec app-docs rm -rf storage/app/private/tenants/docs_santoantoniodoamparo`
(o template-restore recria o banco, mas não limpa os arquivos) e repetir 4 a 6.

## O que entra

| Origem | Destino |
| --- | --- |
| gpe2 `gestora` | UGs (endereço normalizado, CNPJ). Sem e-mail na origem → `ug-NNN@email-nao-informado.invalid`, corrigir no cadastro |
| gpe2 `orgao` / `unidade` / `departamento` | organograma de 3 níveis por UG |
| gpe2 `usuario` + `pessoa` (só **ativos**) | usuários com o mesmo hash de senha; super admin → super admin + Administrador; admin → Administrador; demais → Usuário padrão |
| demonstração | 6 usuários `@gpedocs.demo`, documentos, assinaturas, processos, comunicações, portal e os sistemas integrados `gpe2` e `tributario` |

Lotação: o gpe2 de Santo Antônio não tem `usuario_departamento`; os servidores
reais entram **sem lotação** e precisam ser lotados pela tela de usuários para
usar a caixa do setor.

## Levar para produção

Arquivos gerados (fora do Git, em `database/backups/`):

- `docs_santoantoniodoamparo_<data>.dump` — `pg_dump -Fc`
- `docs_santoantoniodoamparo_arquivos_<data>.tar.gz` — pasta `tenants/docs_santoantoniodoamparo`

No servidor:

1. `createdb docs_santoantoniodoamparo` no postgres-gpe e
   `pg_restore --no-owner --no-privileges -d docs_santoantoniodoamparo <dump>`.
2. Extrair o `.tar.gz` dentro da raiz do disco `documentos` (`DOCUMENTOS_ROOT`,
   padrão `storage/app/private`). Em Docker: `docker cp` para dentro do container `app-docs`
   e `chown -R www-data:www-data` na pasta.
3. Linha no `gpe_registry.tenants` de produção: `subdomain=santoantoniodoamparo`,
   `domain=gpedocs.com.br`, `db_host=postgres-gpe`, `db_port=5432`, credenciais
   do banco de produção.
4. `php artisan tenant:migrate --tenant=santoantoniodoamparo` para garantir as
   migrations mais novas.
5. **Antes de uso real:** apagar os usuários `@gpedocs.demo` e o cidadão de
   demonstração (ou trocar as senhas), trocar a senha do `admin@ged.local` e
   **regenerar os tokens** dos sistemas integrados na tela de Sistemas Integrados
   — os tokens locais não devem ir para produção.

## Pendências conhecidas

- Portal do cidadão por subdomínio não abre localmente (`{ug}.lvh.me` colide com
  a resolução de tenant por subdomínio); em produção depende do DNS do portal.
- O seeder base cria `admin@ged.local` com senha aleatória mostrada uma vez.
