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
| Local (Laragon, porta 8090) | `localhost` | `:8090` | `127.0.0.1`, porta `5433` (container `db-gpe`) |
| Produção | `santoantoniodoamparo` | `gpedocs.com.br` | `postgres-gpe`, porta `5432` |

Os arquivos ficam em `tenants/<db_name>/…` no disco `documentos` — o nome do
banco é o mesmo nos dois ambientes, então banco e arquivos viajam juntos.

## Montar do zero (local)

`.env` do GPEDocs (o original ficou em `.env.bak-antes-docs-santoantonio`):

```
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=docs_santoantoniodoamparo
LANDLORD_DB_DATABASE=gpe_registry
TENANT_DOMINIO_BASE=:8090
APP_URL=http://localhost:8090
# origem da estrutura: banco do município no gpe2
GPE_LEGADO_DRIVER=pgsql
GPE_LEGADO_HOST=127.0.0.1
GPE_LEGADO_PORT=5433
GPE_LEGADO_DATABASE=gpesantoantoniodoamparo
```

1. Tabelas de fila do GPEDocs no catálogo (uma vez):
   `php artisan migrate --database=landlord --path=database/migrations/landlord --force`
2. Linha do tenant no `gpe_registry.tenants` (subdomain/domain da tabela acima,
   `driver=pgsql`, `db_name=docs_santoantoniodoamparo`, `cnpj` só dígitos).
3. Banco, schema, estrutura base e super admins do gpe2:
   `php artisan docs:template-restore --tenant=<id> --legado=gpesantoantoniodoamparo --force`
4. Estrutura real (UGs, organograma, usuários ativos com a mesma senha do gpe2):
   `php artisan docs:importar-estrutura --tenant=<id> --legado=gpesantoantoniodoamparo`
5. Conteúdo de demonstração (opcional — só em banco recém-criado):
   `php artisan docs:demonstracao --tenant=<id> --ug=UG-002`
6. Subir: `php artisan serve --port=8090` → http://localhost:8090

Refazer tudo: apagar `storage/app/private/tenants/docs_santoantoniodoamparo`
(o template-restore recria o banco, mas não limpa os arquivos) e repetir 3 a 5.

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
2. Extrair o `.tar.gz` dentro da raiz do disco `documentos`
   (`DOCUMENTOS_ROOT`, padrão `storage/app/private`).
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
