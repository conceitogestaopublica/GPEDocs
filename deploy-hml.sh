#!/usr/bin/env bash
# Deploy HML do GPE Docs — git pull + dependências + build do front + cache + permissões.
# Mesmo roteiro do deploy-hml.sh do gpe2, adaptado a este projeto.
#
# Pode ser rodado por QUALQUER usuário com acesso ao docker (root ou não), de
# qualquer diretório: o script se localiza sozinho e conserta no fim o dono dos
# arquivos que o pull/artisan gravaram. Uso:
#
#   ./deploy-hml.sh              # deploy normal (com migrations)
#   MIGRAR=0 ./deploy-hml.sh     # pula as migrations (filas no landlord e bancos dos tenants)
#
# Atomicidade: o site entra em manutenção (artisan down) ANTES do pull e só volta (up) no
# fim, depois de dependências, build, migrations e cache. Se algo falhar no meio, ele FICA
# em manutenção — código novo sobre vendor/build/banco velhos é pior que fora do ar — e as
# permissões são corrigidas de qualquer jeito (trap). Dois deploys ao mesmo tempo: o
# segundo é recusado (flock).
#
# Diferenças do gpe2:
#   - o php84-fpm é COMPARTILHADO e cada instalação fica numa pasta própria dentro dele
#     (ex.: /var/www/gpedocs-paraguacu), não em /var/www/html — a pasta é descoberta
#     pelos volumes do container (ou informada em APP_DIR);
#   - vendor/ não é versionado aqui: roda `composer install`;
#   - sem Jasper; em compensação há worker de fila, que precisa de `queue:restart`.
set -euo pipefail

PHP_CONTAINER="${PHP_CONTAINER:-php84-fpm}"
NODE_IMAGE="${NODE_IMAGE:-node:22}"
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_DIR"

falhar() { echo "ERRO: $*" >&2; exit 1; }

# Um deploy por vez nesta instalação (o lock fica em /tmp: não depende de permissão no projeto).
exec 9>"/tmp/deploy-gpedocs-$(printf '%s' "$PROJECT_DIR" | md5sum | cut -c1-12).lock"
flock -n 9 || falhar "já há um deploy rodando para $PROJECT_DIR."

echo "==> verificando ambiente"
[ -f artisan ] || falhar "$PROJECT_DIR não é a raiz do projeto (artisan não encontrado)."
docker inspect -f '{{.State.Running}}' "$PHP_CONTAINER" 2>/dev/null | grep -q true \
    || falhar "container '$PHP_CONTAINER' não está rodando (veja 'docker ps')."
git diff --quiet && git diff --cached --quiet \
    || falhar "há alterações locais não commitadas. Rode 'git status' e resolva antes do deploy."

# Raiz do projeto DENTRO do container: o volume cuja origem contém $PROJECT_DIR.
# Casa o mais específico (o bind da própria pasta vence o de /var/www inteiro).
if [ -z "${APP_DIR:-}" ]; then
    APP_DIR=""
    melhor=0
    while IFS='|' read -r origem destino; do
        [ -n "$origem" ] || continue
        case "$PROJECT_DIR/" in
            "${origem%/}/"*)
                if [ "${#origem}" -gt "$melhor" ]; then
                    melhor=${#origem}
                    APP_DIR="${destino%/}${PROJECT_DIR#"${origem%/}"}"
                fi
                ;;
        esac
    done < <(docker inspect -f '{{range .Mounts}}{{.Source}}|{{.Destination}}{{println}}{{end}}' "$PHP_CONTAINER")
fi
[ -n "$APP_DIR" ] || falhar "não achei $PROJECT_DIR entre os volumes de '$PHP_CONTAINER'. Informe a pasta: APP_DIR=/var/www/<pasta> ./deploy-hml.sh"
docker exec "$PHP_CONTAINER" test -f "$APP_DIR/artisan" \
    || falhar "'$APP_DIR' dentro de '$PHP_CONTAINER' não tem o artisan — APP_DIR errado?"

# Usuário dos workers do php-fpm, lido do próprio pool — não chutar. É quem
# precisa escrever em storage/ (log, cache, sessões, arquivos dos documentos).
# Pool fora do caminho da imagem oficial: informe FPM_USER=<usuário>.
if [ -z "${FPM_USER:-}" ]; then
    FPM_USER="$(docker exec "$PHP_CONTAINER" sh -c \
        "grep -hE '^user *=' /usr/local/etc/php-fpm.d/*.conf 2>/dev/null | tail -1 | cut -d= -f2 | tr -d ' \r'" \
        || true)"
fi
[ -n "$FPM_USER" ] || falhar "não consegui ler o usuário do php-fpm em '$PHP_CONTAINER'. Informe: FPM_USER=www-data ./deploy-hml.sh"
echo "    projeto: $PROJECT_DIR | container: $PHP_CONTAINER:$APP_DIR | usuário do fpm: $FPM_USER"

# artisan/composer sempre na pasta desta instalação (o container é compartilhado).
artisan() { docker exec -w "$APP_DIR" "$PHP_CONTAINER" php artisan "$@"; }

# O `git pull` grava como o usuário do host e o composer/`optimize` gravam como root
# dentro do container: os arquivos que mudaram deixam de pertencer ao $FPM_USER.
corrigir_permissoes() {
    echo "==> ajustando permissões (storage e bootstrap/cache -> $FPM_USER)"
    docker exec -u root "$PHP_CONTAINER" chown -R "$FPM_USER:$FPM_USER" \
        "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
    docker exec -u root "$PHP_CONTAINER" chmod -R u+rwX,g+rwX \
        "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
}

# Sai de qualquer jeito (erro no meio inclusive): permissões sempre corrigidas. Com erro, o
# site fica em manutenção de propósito, e a mensagem diz como sair dela.
concluido=0
ao_sair() {
    rc=$?
    [ "$concluido" = 1 ] && return
    corrigir_permissoes || true
    if [ "$rc" -ne 0 ]; then
        echo "ERRO no deploy (código $rc): o site CONTINUA EM MANUTENÇÃO." >&2
        echo "  Corrija e rode ./deploy-hml.sh de novo, ou reabra como está:" >&2
        echo "  docker exec -w $APP_DIR $PHP_CONTAINER php artisan up" >&2
    fi
}
trap ao_sair EXIT

echo "==> entrando em manutenção (artisan down)"
artisan down --retry=15

echo "==> git pull"
git pull --ff-only

echo "==> dependências PHP (composer install --no-dev)"
docker exec -w "$APP_DIR" "$PHP_CONTAINER" \
    composer install --no-dev --optimize-autoloader --no-interaction --no-progress

echo "==> build do front (npm ci && npm run build)"
# --user: sem isto o node roda como root e deixa node_modules/ e public/build/ root:root
docker run --rm --user "$(id -u):$(id -g)" -v "$PROJECT_DIR":/app -w /app "$NODE_IMAGE" \
    sh -lc "npm ci && npm run build"

echo "==> removendo public/hot (força o modo manifest)"
rm -f public/hot

if [ "${MIGRAR:-1}" = "1" ]; then
    # landlord:migrate só cria as filas gpedocs_* (idempotente) — o schema do landlord é do gpe2.
    echo "==> migrations: filas no landlord"
    artisan landlord:migrate
    echo "==> migrations: bancos dos tenants do GPE Docs"
    artisan tenant:migrate --tenant=ALL --no-interaction
fi

echo "==> recriando cache do Laravel"
artisan optimize

# Workers de fila carregam o código uma vez: sem isto seguem rodando a versão antiga.
echo "==> reiniciando workers de fila (queue:restart)"
artisan queue:restart

# Depois de tudo que escreve (pull, composer, optimize).
corrigir_permissoes

echo "==> saindo da manutenção (artisan up)"
artisan up
concluido=1

echo "==> conferindo escrita em storage/"
docker exec -u "$FPM_USER" "$PHP_CONTAINER" sh -c \
    "T=\"$APP_DIR/storage/app/.deploy_wtest\"; touch \"\$T\" && rm -f \"\$T\"" \
    || falhar "o usuário '$FPM_USER' não consegue escrever em $APP_DIR/storage — uploads e logs vão falhar."

echo "==> ok"
