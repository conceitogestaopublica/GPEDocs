# Instalação do GPE Docs (ambiente local)

## 1. Pré-requisitos
- Docker com Compose v2.
- Stack do **gpe2** no ar. O GPE Docs não tem banco próprio: usa o `postgres-gpe` do gpe2.

## 2. Verificar a rede do gpe2
```bash
docker network ls | grep gpe_gpe
docker network inspect gpe_gpe --format '{{range .Containers}}{{.Name}} {{end}}'
```
A primeira linha precisa listar `gpe_gpe`, e a segunda precisa mostrar `postgres-gpe`. Se não mostrar,
suba o gpe2 antes (`docker compose up -d` na pasta do gpe2).

## 3. Configurar o `.env`
```bash
cp .env.example .env
```

| Variável | Valor |
|---|---|
| `LANDLORD_DB_PASSWORD` | Igual ao `LANDLORD_DB_PASSWORD` do `.env` do gpe2 |
| `LANDLORD_DB_HOST` | `postgres-gpe` (já vem assim) |
| `APP_PORT` / `TENANT_DOMINIO_BASE` | `8090` / `:8090`. Se trocar a porta, troque os dois. |
| `VITE_PORT` | `5174`. Troque só se a porta estiver ocupada. |
| `GPE_LEGADO_*` | Acesso ao MariaDB legado, usado no passo 6 |
| `APP_KEY` | Deixe vazia. O passo 4 mostra o valor. |

## 4. Subir
```bash
docker compose up -d --build
docker logs docs-app
```
Na primeira vez, o log mostra `APP_KEY=base64:...`. Cole essa linha no `.env` e rode
`docker compose up -d`.

## 5. Tabelas da fila (uma vez por máquina)
```bash
docker exec docs-app php artisan landlord:migrate
```

## 6. Criar o município
1. Cadastre o tenant. O comando pergunta o que faltar e sugere os padrões
   (domínio do `TENANT_DOMINIO_BASE`, servidor e login do landlord):
   ```bash
   docker exec -it docs-app php artisan tenant:create
   ```
   Ou tudo na linha: `--nome="Paraguaçu" --uf=MG --db-name=docs_paraguacu`.
   Também dá para cadastrar pelo painel do gpe2, com `subdomain=localhost`, `domain=:8090` e `driver=pgsql`.
2. Crie o banco:
   ```bash
   docker exec -it docs-app php artisan docs:template-restore --tenant=<id>
   ```
   Sem acesso ao legado, restaure o dump de um município recebido da equipe:
   ```bash
   docker exec -i postgres-gpe createdb -U root docs_<municipio>
   docker exec -i postgres-gpe pg_restore -U root --no-owner --no-privileges -d docs_<municipio> < arquivo.dump
   docker exec docs-app php artisan tenant:migrate --tenant=<id>
   ```
3. (Opcional) Importe as UGs, o organograma e os usuários reais do gpe2/GPD. O comando pergunta o tenant,
   o banco de origem (servidor e login de `GPE_LEGADO_*`) e a UG principal:
   ```bash
   docker exec -it docs-app php artisan docs:importar-estrutura
   ```

Acesse `http://localhost:8090`.

## Problemas comuns
| Sintoma | Solução |
|---|---|
| `network gpe_gpe ... could not be found` | Suba o gpe2 (passo 2) |
| `docs-app` reiniciando | Falta a `APP_KEY` (passo 4) |
| `Vite manifest not found` | O `docs-vite` ainda instala dependências: `docker logs -f docs-vite` |
| Redireciona para o painel do gpe2 | Falta o tenant com `domain` = `:<APP_PORT>` (`tenant:create`, passo 6) |
| Mudança no `.env` não pegou na fila | `docker compose restart queue` |
