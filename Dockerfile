# ──────────────────────────────────────────────
# Stage 1 — Build frontend (Node + Vite)
# ──────────────────────────────────────────────
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json vite.config.js ./
RUN npm ci --prefer-offline

COPY resources/ resources/
COPY public/ public/

RUN npm run build

# ──────────────────────────────────────────────
# Stage 2 — PHP application (FPM)
# ──────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS app

RUN apk add --no-cache \
        curl-dev \
        freetype-dev \
        git \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libxml2-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
        # qpdf: regrava PDFs com object streams (PDF 1.5+, ex.: exportados do Word),
        # que o parser gratuito do FPDI não lê — ver App\Support\PdfCompativel.
        qpdf \
        # OCR de anexos escaneados e imagens para a busca (TR 4.15.4): Tesseract em
        # português + pdftoppm (poppler) para rasterizar as páginas — App\Services\Ocr.
        tesseract-ocr \
        tesseract-ocr-data-por \
        poppler-utils \
        # Atos oficiais (TR 4.42.3): o LibreOffice converte o .docx do ato em PDF/A-2b —
        # App\Support\Atos::paraPdfA. Fontes Liberation/DejaVu para acentos e métricas do Word.
        libreoffice-writer \
        font-liberation \
        font-dejavu \
        # Backup do banco do município (TR 4.26.13): pg_dump na mesma versão do servidor — App\Support\Backup.
        postgresql18-client \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        curl \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        pgsql \
        xml \
        zip \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Instala dependências PHP primeiro (cache layer)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copia o código e os assets compilados
COPY . .
COPY --from=frontend /app/public/build public/build

RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ──────────────────────────────────────────────
# Stage 3 — Nginx (serve static + proxy FPM)
# ──────────────────────────────────────────────
FROM nginx:alpine AS nginx

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public

EXPOSE 80
