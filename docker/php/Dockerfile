# PHP-FPM do espelho local de produção.
#
# A imagem NÃO é a de produção — a de produção virá do Harbor quando a TI o
# instalar (item F5 do plano 11). Esta serve para executar localmente o caminho
# Nginx -> PHP-FPM -> Laravel, que o fluxo de desenvolvimento com Vite não
# exercita.
#
# 8.4, e não 8.3, e a diferença não é gosto: o `composer.json` declara
# `"php": "^8.3"`, mas o `composer.lock` resolvido exige **>= 8.4.1**, e o
# `vendor/composer/platform_check.php` aborta antes de o Laravel carregar. Isto
# foi descoberto AQUI, na primeira subida do espelho: o DEPLOY_LARAVEL.md mandava
# instalar php8.3-fpm, e um servidor montado por aquele manual responderia 500 em
# toda requisição. O manual foi corrigido junto.
#
# Fica a divergência a resolver: `composer.json` ainda anuncia ^8.3, que o lock
# não permite. Mudar a declaração é mexer no stack e precisa de decisão humana.
FROM php:8.4-fpm-alpine

# Bibliotecas de EXECUÇÃO: ficam na imagem. As de compilação vão num pacote
# virtual e são removidas depois.
#
# A separação não é zelo: a primeira versão deste arquivo instalava só os
# `-dev` e os apagava no fim, e o `gd` compilava mas não CARREGAVA —
# "Unable to load dynamic library 'gd' ... libpng16.so.16: No such file".
# O `apk del` levou junto a biblioteca de runtime que o `-dev` trouxera.
RUN apk add --no-cache freetype libjpeg-turbo libpng libzip icu-libs \
    && apk add --no-cache --virtual .build-deps \
        freetype-dev libjpeg-turbo-dev libpng-dev libzip-dev icu-dev oniguruma-dev \
    # gd: dompdf (certificados em PDF) e chillerlan/php-qrcode.
    # pdo_mysql: o banco. bcmath e intl: formatação e cálculo de notas.
    # zip: usado pelo Composer ao instalar pacotes.
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql bcmath intl zip opcache \
    && apk del --no-network .build-deps \
    # Prova que as extensões carregam de verdade. Sem isto, um erro de
    # biblioteca só apareceria na primeira requisição que precisasse dela —
    # no caso do gd, na emissão de um certificado.
    && php -m | grep -qx gd \
    && php -m | grep -qx pdo_mysql \
    && php -m | grep -qx intl

# Limites equivalentes aos do `client_max_body_size` do Nginx: se o PHP aceitasse
# menos que o Nginx, o upload morreria no meio com erro sem mensagem útil.
RUN { \
      echo 'upload_max_filesize = 20M'; \
      echo 'post_max_size = 21M'; \
      echo 'memory_limit = 256M'; \
      echo 'expose_php = Off'; \
    } > /usr/local/etc/php/conf.d/avasec.ini

WORKDIR /var/www/avasec/backend-laravel
