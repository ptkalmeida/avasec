# Imagem de HML e PRD do AVASEC (Norma TI-SECEC C.3, C.11 e F.4).
#
# Uma imagem só por serviço, a MESMA nos dois ambientes: o que muda entre HML e PRD é a
# configuração (variáveis de ambiente, vindas do Infisical), nunca a imagem — é isso que
# permite "promover" a versão homologada, em vez de reconstruí-la (B §3.8).
#
# Dois alvos, a partir do repositório inteiro como contexto:
#   app  -> PHP-FPM. Também é a imagem dos containers `queue` e `scheduler` (só muda o comando).
#   web  -> Nginx, com os arquivos estáticos de `public/` e a configuração do repositório.
#
#   docker build -f docker/producao/Dockerfile --target app -t <harbor>/ava/app:<versão> .
#   docker build -f docker/producao/Dockerfile --target web -t <harbor>/ava/web:<versão> .
#
# Rascunho validado só localmente (plano 11, F4): o compose padrão da TI (F.29 item 2) ainda
# pode mudar nomes e redes.

# ---------------------------------------------------------------------------------------
# 1. Assets das telas Blade (Vite + Tailwind). Sem este passo as telas saem sem estilo:
#    `public/build` não é versionado.
# ---------------------------------------------------------------------------------------
FROM node:24-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.blade.config.ts tsconfig.json ./
COPY backend-laravel/resources backend-laravel/resources
# O manifesto é o que o `@vite` do Blade procura; sem ele toda tela dá erro 500.
RUN mkdir -p backend-laravel/public \
    && npm run build \
    && test -f backend-laravel/public/build/manifest.json

# ---------------------------------------------------------------------------------------
# 2. PHP com as extensões. Mesmas do espelho local (docker/php/Dockerfile); o comentário
#    sobre `opcache` e sobre as bibliotecas de execução está lá.
# ---------------------------------------------------------------------------------------
# Versão fixa, não flutuante: >= 8.5.12 corrige CVE-2026-109618 e CVE-2026-109619 (PHP2Shell, RCE).
FROM php:8.5.12-fpm-alpine AS base
RUN apk add --no-cache freetype libjpeg-turbo libpng libzip icu-libs \
    && apk add --no-cache --virtual .build-deps \
        freetype-dev libjpeg-turbo-dev libpng-dev libzip-dev icu-dev oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql bcmath intl zip \
    && apk del --no-network .build-deps \
    && php -m | grep -qx gd \
    && php -m | grep -qx pdo_mysql \
    && php -m | grep -qx intl \
    && php -m | grep -qx bcmath \
    && php -m | grep -qx zip
# Cadeia de limites: app ≤ upload_max_filesize ≤ post_max_size ≤ client_max_body_size (Nginx).
RUN { \
      echo 'upload_max_filesize = 20M'; \
      echo 'post_max_size = 21M'; \
      echo 'memory_limit = 256M'; \
      echo 'expose_php = Off'; \
      echo 'display_errors = Off'; \
      echo 'session.upload_progress.enabled = 0'; \
      echo 'opcache.validate_timestamps = 0'; \
    } > /usr/local/etc/php/conf.d/avasec.ini

# ---------------------------------------------------------------------------------------
# 3. Dependências do Composer, sem as de desenvolvimento.
# ---------------------------------------------------------------------------------------
FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/avasec/backend-laravel
COPY backend-laravel/composer.json backend-laravel/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY backend-laravel/ ./
# `storage/framework` e `bootstrap/cache` não vêm do contexto (.dockerignore): o `artisan` precisa
# deles para existir, senão morre com "Please provide a valid cache path".
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --optimize --no-dev --no-scripts --no-interaction \
    # `APP_ENV=local` só aqui, no build: em `production` o Laravel recusa subir sem o JWT_SECRET,
    # que de propósito não existe na imagem. `package:discover` só grava a lista de pacotes.
    && APP_ENV=local php artisan package:discover --no-interaction

# ---------------------------------------------------------------------------------------
# 4. app: o que roda em `app`, `queue` e `scheduler`.
# ---------------------------------------------------------------------------------------
FROM base AS app
WORKDIR /var/www/avasec/backend-laravel
COPY --from=vendor --chown=www-data:www-data /var/www/avasec/backend-laravel ./
COPY --from=assets --chown=www-data:www-data /build/backend-laravel/public/build ./public/build
COPY docker/producao/entrypoint.sh /usr/local/bin/avasec-entrypoint
# `storage` e `bootstrap/cache` são os únicos caminhos em que o PHP grava. Os uploads ficam
# fora da imagem (volume, ou MinIO quando a TI o entregar — F3).
RUN chmod +x /usr/local/bin/avasec-entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache /var/www/avasec/uploads/public /var/www/avasec/uploads/private \
    && chown -R www-data:www-data storage bootstrap/cache /var/www/avasec/uploads
USER www-data
ENTRYPOINT ["avasec-entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------------------
# 5. web: Nginx. Os `location` e os cabeçalhos são os arquivos do repositório, os mesmos que
#    o espelho local executa — nenhuma cópia divergente. TLS e HSTS ficam no ponto de
#    entrada da TI (o domínio e o certificado são do ambiente), não nesta imagem.
# ---------------------------------------------------------------------------------------
FROM nginx:1.27-alpine AS web
COPY docker/nginx/avasec-cabecalhos.conf /etc/nginx/avasec-cabecalhos.conf
COPY docker/nginx/avasec-locations.conf /etc/nginx/avasec-locations.conf
COPY docker/producao/web.conf /etc/nginx/conf.d/default.conf
COPY docker/producao/upstream.conf /etc/nginx/avasec-upstream.conf
# Só `public/`: o Nginx serve os estáticos e entrega o resto ao PHP-FPM, que tem o mesmo
# caminho no container `app`.
COPY --from=app /var/www/avasec/backend-laravel/public /var/www/avasec/backend-laravel/public
HEALTHCHECK --interval=15s --timeout=3s --retries=5 CMD wget -qO- http://127.0.0.1/health/live >/dev/null 2>&1 || exit 1
