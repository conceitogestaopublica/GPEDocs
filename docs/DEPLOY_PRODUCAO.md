# Deploy GPE Docs em Produção

**Sistema:** GPE Docs — Plataforma Digital Integrada
**Repositório:** https://github.com/conceitogestaopublica/GPEDocs
**Servidor atual:** Docker (nginx + PHP-FPM 8.4 + PostgreSQL 16 + MariaDB 10.3)

---

## Objetivo

Disponibilizar o sistema GPE Docs em dois subdomínios independentes:

| Subdomínio | Banco | Cliente |
|---|---|---|
| `paraguacu.gpedocs.com.br` | `docsparagucu` | Município de Paraguaçu/MG |
| `arinos.gpedocs.com.br` | `docsarimos` | Município de Arinos/MG |

Ambos compartilham o **mesmo PostgreSQL** (`postgres-gpedocs`) e o **mesmo PHP-FPM** (`php84-fpm`), mas com **bancos** e **pastas de código** separados para isolamento.

---

## Estado atual do servidor

`docker-compose.yml` (em `/home/admin/`):

```yaml
version: "3.8"

services:
  nginx:
    image: nginx:alpine
    container_name: nginx-laravel
    ports: ["80:80", "443:443"]
    volumes:
      - /home/admin/gpe2:/var/www/html
      - /home/admin/gpedocs:/var/www/gpedocs
      - /home/admin/nginx:/etc/nginx/conf.d:ro
      - /home/admin/certbot/conf:/etc/letsencrypt
      - /home/admin/certbot/www:/var/www/certbot

  php84:
    container_name: php84-fpm
    volumes:
      - /home/admin/gpe2:/var/www/html
      - /home/admin/gpedocs:/var/www/gpedocs

  postgres-gpedocs:
    image: postgres:16
    container_name: postgres-gpedocs
    environment:
      POSTGRES_DB: gpedocs
      POSTGRES_USER: gpedocs
      POSTGRES_PASSWORD: C0nc3it0
    volumes:
      - postgres_gpedocs_data:/var/lib/postgresql/data
```

**Credenciais do PostgreSQL:**
- Container: `postgres-gpedocs`
- Usuário: `gpedocs`
- Senha: `C0nc3it0`

---

## Problema atual

Erro ao acessar `paraguacu.gpedocs.com.br`:

```
SQLSTATE[08006] FATAL: password authentication failed for user "gpedocs"
```

Causa: o servidor tem só **uma pasta de código** (`/home/admin/gpedocs`) e **um banco** (`gpedocs`). Os dois subdomínios precisam apontar para bancos diferentes (`docsparagucu` e `docsarimos`), o que exige duas pastas independentes com `.env` próprios.

---

## Passo a passo do deploy

### 1. Atualizar código no servidor

Na pasta atual `/home/admin/gpedocs`, fazer pull do GitHub para garantir a versão mais recente:

```bash
cd /home/admin/gpedocs
git pull origin main
```

### 2. Criar duas cópias do código

```bash
cp -r /home/admin/gpedocs /home/admin/gpedocs-paraguacu
cp -r /home/admin/gpedocs /home/admin/gpedocs-arinos

# Ajustar permissoes
chown -R www-data:www-data /home/admin/gpedocs-paraguacu
chown -R www-data:www-data /home/admin/gpedocs-arinos
chmod -R 775 /home/admin/gpedocs-paraguacu/storage /home/admin/gpedocs-paraguacu/bootstrap/cache
chmod -R 775 /home/admin/gpedocs-arinos/storage /home/admin/gpedocs-arinos/bootstrap/cache
```

### 3. Criar os bancos no PostgreSQL

```bash
docker exec -it postgres-gpedocs psql -U gpedocs -d gpedocs \
  -c "CREATE DATABASE docsparagucu OWNER gpedocs;"

docker exec -it postgres-gpedocs psql -U gpedocs -d gpedocs \
  -c "CREATE DATABASE docsarimos OWNER gpedocs;"
```

Confirmar:

```bash
docker exec -it postgres-gpedocs psql -U gpedocs -d gpedocs -c "\l"
```

Deve listar `docsparagucu` e `docsarimos`.

### 4. Restaurar os backups SQL

Os arquivos estão em `database/backups/` no repositório. Para o servidor:

```bash
# Copiar do código para dentro do container Postgres
docker cp /home/admin/gpedocs-paraguacu/database/backups/docsparagucu_inicial_20260512_100334.sql postgres-gpedocs:/tmp/
docker cp /home/admin/gpedocs-arinos/database/backups/arinos_inicial_20260512_105842.sql postgres-gpedocs:/tmp/

# Restaurar
docker exec -it postgres-gpedocs psql -U gpedocs -d docsparagucu -f /tmp/docsparagucu_inicial_20260512_100334.sql
docker exec -it postgres-gpedocs psql -U gpedocs -d docsarimos -f /tmp/arinos_inicial_20260512_105842.sql
```

Validar:

```bash
docker exec -it postgres-gpedocs psql -U gpedocs -d docsparagucu -c "SELECT count(*) FROM users; SELECT id, nome FROM ugs;"
docker exec -it postgres-gpedocs psql -U gpedocs -d docsarimos -c "SELECT count(*) FROM users; SELECT id, nome FROM ugs;"
```

### 5. Configurar os arquivos `.env`

**`/home/admin/gpedocs-paraguacu/.env`:**

```env
APP_NAME="GPE Docs - Paraguaçu"
APP_ENV=production
APP_KEY=               # rodar php artisan key:generate depois
APP_DEBUG=false
APP_URL=https://paraguacu.gpedocs.com.br

APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
APP_FAKER_LOCALE=pt_BR

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=postgres-gpedocs
DB_PORT=5432
DB_DATABASE=docsparagucu
DB_USERNAME=gpedocs
DB_PASSWORD=C0nc3it0

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

CACHE_STORE=file
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
BROADCAST_CONNECTION=log

MAIL_MAILER=log
```

**`/home/admin/gpedocs-arinos/.env`:**

```env
APP_NAME="GPE Docs - Arinos"
APP_ENV=production
APP_KEY=               # rodar php artisan key:generate depois
APP_DEBUG=false
APP_URL=https://arinos.gpedocs.com.br

APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
APP_FAKER_LOCALE=pt_BR

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=postgres-gpedocs
DB_PORT=5432
DB_DATABASE=docsarimos
DB_USERNAME=gpedocs
DB_PASSWORD=C0nc3it0

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

CACHE_STORE=file
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
BROADCAST_CONNECTION=log

MAIL_MAILER=log
```

### 6. Atualizar `docker-compose.yml`

Editar `/home/admin/docker-compose.yml` adicionando os volumes das novas pastas:

```yaml
services:
  nginx:
    image: nginx:alpine
    container_name: nginx-laravel
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - /home/admin/gpe2:/var/www/html
      - /home/admin/gpedocs-paraguacu:/var/www/gpedocs-paraguacu
      - /home/admin/gpedocs-arinos:/var/www/gpedocs-arinos
      - /home/admin/nginx:/etc/nginx/conf.d:ro
      - /home/admin/certbot/conf:/etc/letsencrypt
      - /home/admin/certbot/www:/var/www/certbot
    depends_on:
      - php84
    networks:
      - laravel

  php84:
    build:
      context: /home/admin/php84
    container_name: php84-fpm
    restart: unless-stopped
    working_dir: /var/www
    volumes:
      - /home/admin/gpe2:/var/www/html
      - /home/admin/gpedocs-paraguacu:/var/www/gpedocs-paraguacu
      - /home/admin/gpedocs-arinos:/var/www/gpedocs-arinos
    depends_on:
      mariadb:
        condition: service_healthy
      postgres-gpedocs:
        condition: service_started
    networks:
      - laravel

  mariadb:
    image: mariadb:10.3
    container_name: mariadb10
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: r00tC0nc31t0
      MYSQL_DATABASE: gpdparaguacu
      MYSQL_USER: laravel
      MYSQL_PASSWORD: LaRaVeLC0nc31t0
    volumes:
      - debian_mariadb10_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      retries: 5
    networks:
      - laravel

  postgres-gpedocs:
    image: postgres:16
    container_name: postgres-gpedocs
    restart: unless-stopped
    environment:
      POSTGRES_DB: gpedocs
      POSTGRES_USER: gpedocs
      POSTGRES_PASSWORD: C0nc3it0
    volumes:
      - postgres_gpedocs_data:/var/lib/postgresql/data
    networks:
      - laravel

networks:
  laravel:
    driver: bridge

volumes:
  debian_mariadb10_data:
  postgres_gpedocs_data:
```

### 7. Configuração nginx

Criar `/home/admin/nginx/gpedocs.conf`:

```nginx
# Redirect HTTP -> HTTPS
server {
    listen 80;
    server_name paraguacu.gpedocs.com.br arinos.gpedocs.com.br;

    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }

    location / {
        return 301 https://$host$request_uri;
    }
}

# paraguacu.gpedocs.com.br
server {
    listen 443 ssl;
    http2 on;
    server_name paraguacu.gpedocs.com.br;
    root /var/www/gpedocs-paraguacu/public;
    index index.php index.html;

    ssl_certificate /etc/letsencrypt/live/paraguacu.gpedocs.com.br/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/paraguacu.gpedocs.com.br/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass php84:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_read_timeout 300;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }

    access_log /var/log/nginx/paraguacu_access.log;
    error_log  /var/log/nginx/paraguacu_error.log;
}

# arinos.gpedocs.com.br
server {
    listen 443 ssl;
    http2 on;
    server_name arinos.gpedocs.com.br;
    root /var/www/gpedocs-arinos/public;
    index index.php index.html;

    ssl_certificate /etc/letsencrypt/live/arinos.gpedocs.com.br/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/arinos.gpedocs.com.br/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass php84:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_read_timeout 300;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }

    access_log /var/log/nginx/arinos_access.log;
    error_log  /var/log/nginx/arinos_error.log;
}
```

### 8. Gerar certificados SSL (Let's Encrypt)

Antes de aplicar a config nginx com HTTPS, gerar os certificados:

```bash
# Subir apenas nginx com configuração HTTP (sem SSL) temporariamente
# OU usar certbot standalone parando o nginx temporariamente

docker run -it --rm \
  -v /home/admin/certbot/conf:/etc/letsencrypt \
  -v /home/admin/certbot/www:/var/www/certbot \
  certbot/certbot certonly --webroot \
  -w /var/www/certbot \
  -d paraguacu.gpedocs.com.br \
  -d arinos.gpedocs.com.br \
  --email seu@email.com \
  --agree-tos --no-eff-email
```

**Pré-requisito DNS:** ambos os subdomínios já devem apontar (registro A) para o IP do servidor antes de gerar os certificados.

### 9. Aplicar tudo

```bash
cd /home/admin

# Recriar containers com novos volumes
docker-compose down
docker-compose up -d

# Gerar APP_KEY em cada deploy
docker exec -it php84-fpm sh -c "cd /var/www/gpedocs-paraguacu && php artisan key:generate"
docker exec -it php84-fpm sh -c "cd /var/www/gpedocs-arinos && php artisan key:generate"

# Limpar caches em cada deploy
docker exec -it php84-fpm sh -c "cd /var/www/gpedocs-paraguacu && php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear"
docker exec -it php84-fpm sh -c "cd /var/www/gpedocs-arinos && php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear"

# Otimizar (produção)
docker exec -it php84-fpm sh -c "cd /var/www/gpedocs-paraguacu && php artisan config:cache && php artisan route:cache && php artisan view:cache"
docker exec -it php84-fpm sh -c "cd /var/www/gpedocs-arinos && php artisan config:cache && php artisan route:cache && php artisan view:cache"

# Build dos assets (Vite) — uma vez em cada pasta, se necessario
docker exec -it php84-fpm sh -c "cd /var/www/gpedocs-paraguacu && npm install && npm run build"
docker exec -it php84-fpm sh -c "cd /var/www/gpedocs-arinos && npm install && npm run build"

# Recarregar nginx
docker exec -it nginx-laravel nginx -t
docker exec -it nginx-laravel nginx -s reload
```

### 10. Validar acesso

Abrir no navegador:

- https://paraguacu.gpedocs.com.br
- https://arinos.gpedocs.com.br

**Credenciais de teste:**

| Sistema | E-mail | Senha |
|---|---|---|
| Paraguaçu | `joeljardim@gmail.com` | `admin123` |
| Arinos | `joeljardim@gmail.com` | `admin123` |
| Ambos | `admin@ged.local` | `admin123` |

Usuários importados do GPE Cloud (gpdparaguacu e gpdarinos) mantêm as **senhas originais** que já utilizavam no sistema legado.

---

## Atualizações futuras

Quando houver release nova no GitHub, atualizar **as duas pastas** independentemente:

```bash
cd /home/admin/gpedocs-paraguacu
git pull origin main
docker exec -it php84-fpm sh -c "cd /var/www/gpedocs-paraguacu && composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan config:cache && php artisan view:cache && npm install && npm run build"

cd /home/admin/gpedocs-arinos
git pull origin main
docker exec -it php84-fpm sh -c "cd /var/www/gpedocs-arinos && composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan config:cache && php artisan view:cache && npm install && npm run build"
```

---

## Backup periódico recomendado

Adicionar ao crontab do host (`crontab -e`):

```bash
# Diário às 02:00 - dump dos bancos
0 2 * * * docker exec postgres-gpedocs pg_dump -U gpedocs docsparagucu > /home/admin/backups/docsparagucu_$(date +\%Y\%m\%d).sql
0 2 * * * docker exec postgres-gpedocs pg_dump -U gpedocs docsarimos > /home/admin/backups/docsarimos_$(date +\%Y\%m\%d).sql

# Semanal aos domingos 03:00 - limpar dumps com mais de 30 dias
0 3 * * 0 find /home/admin/backups -name "*.sql" -mtime +30 -delete
```

---

## Troubleshooting

### Erro `password authentication failed for user "gpedocs"`
- Verificar que `DB_PASSWORD` no `.env` é exatamente `C0nc3it0`
- Rodar `docker exec -it php84-fpm sh -c "cd <pasta> && php artisan config:clear"` após editar o `.env`

### Erro `database "docsparagucu" does not exist`
- Verificar se o banco foi criado: `docker exec -it postgres-gpedocs psql -U gpedocs -d gpedocs -c "\l"`
- Se não existir, repetir o passo 3

### Erro `Class "Inertia\Inertia" not found`
- Rodar `composer install` dentro do container PHP na pasta correspondente

### Página 500 sem detalhes
- Setar `APP_DEBUG=true` no `.env` temporariamente
- Ver logs: `docker exec -it php84-fpm tail -f /var/www/gpedocs-paraguacu/storage/logs/laravel.log`

### Sessão não persiste após login
- Confirmar `SESSION_DOMAIN=null` e `APP_URL` igual ao subdomínio que está sendo acessado
- Limpar sessões: `rm -rf storage/framework/sessions/*`

### Cookies bloqueados em HTTPS
- Confirmar `SESSION_SECURE_COOKIE=true` apenas em HTTPS
- Verificar certificado SSL válido

---

## Contatos

**Desenvolvedor:** Joel Gonçalves Jardim — joeljardim@gmail.com
**Empresa:** Conceito Gestão Pública
**Repositório:** https://github.com/conceitogestaopublica/GPEDocs

---

*Documento gerado em maio de 2026 — versão 1.0*
