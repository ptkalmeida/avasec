#!/bin/sh
# Prepara o Laravel e entrega a execução ao comando do container (php-fpm, queue ou scheduler).
#
# Só faz o que é seguro repetir a cada partida: cache de configuração, rotas e telas. NÃO roda
# `migrate`: alterar o banco é um passo deliberado do deploy (`php artisan migrate --force`,
# uma vez, por quem faz a entrega), e não um efeito colateral de reiniciar um container.
set -e

# A chave e o segredo do JWT têm de vir do ambiente (Infisical). Sem eles a aplicação
# subiria e emitiria sessão com segredo vazio.
for var in APP_KEY JWT_SECRET DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD; do
  eval "valor=\${$var:-}"
  if [ -z "$valor" ]; then
    echo "avasec-entrypoint: variável $var não definida" >&2
    exit 1
  fi
done

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

exec "$@"
