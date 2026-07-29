# Deploy de produção — AVASEC (Laravel + React)

Documento de referência para o **corte final** da migração Node → Laravel: como
colocar em produção a topologia decidida (Nginx serve o build estático do React +
proxy `/api` para PHP-FPM/Laravel). Escrito para ser executado no VPS, fora deste
ambiente de desenvolvimento.

> Contexto: este é o Passo 4 do plano de corte (ver `HARDENING.md`/histórico do
> projeto). Os Passos 1-3 (baseline de migrations, validação da API 100% Laravel,
> config de dev pós-corte) já foram concluídos e validados localmente. O Passo 5
> (desligar o Node) é decisão sua, feita **depois** de validar este deploy.

## Arquitetura alvo

```
[Navegador] ──HTTPS──> [Nginx]
                          ├── /              -> serve dist/ (build estático do Vite/React)
                          ├── /uploads/*     -> serve uploads/public/ (arquivos estáticos)
                          └── /api/*         -> proxy_pass -> PHP-FPM (Laravel, unix socket ou 127.0.0.1:9000)
                                                                  └── MySQL 8
```

Uma única origem (mesmo domínio) para tudo — front e API. Isso mantém o cookie de
sessão `ava_session` (HttpOnly, SameSite=Lax) funcionando sem precisar de CORS
cross-origin, exatamente como hoje.

## 1. Preparar o servidor

Pacotes necessários (Ubuntu/Debian; adapte para sua distro):

```bash
sudo apt update
sudo apt install -y nginx php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-fileinfo php8.3-gd \
  mysql-server composer nodejs npm certbot python3-certbot-nginx
```

Ajuste a versão do PHP conforme disponível (8.2+ é o mínimo usado pelo projeto).
O `php8.3-gd` é preventivo para o dompdf (PDF de certificados, ADR 09): o fluxo
atual funciona sem ele (QR em SVG), mas qualquer logo raster futura no template
exigiria a extensão.

## 2. Banco de dados

- MySQL 8 já deve existir (ou suba um novo). Crie o banco e o usuário de produção:

```sql
CREATE DATABASE avasec CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'avasec'@'localhost' IDENTIFIED BY '<senha-forte-gerada>';
GRANT ALL PRIVILEGES ON avasec.* TO 'avasec'@'localhost';
FLUSH PRIVILEGES;
```

- **Se for um banco novo** (sem os dados atuais): rode as migrations do Laravel
  normalmente — elas criam o schema inteiro do zero, já que a baseline foi gerada
  por introspection do schema real:

  ```bash
  php artisan migrate --force
  php artisan db:seed --force   # opcional: só se quiser os dados de demonstração
  ```

- **Se for migrar o banco de dados atual** (com alunos/cursos reais já cadastrados):
  faça um `mysqldump` do banco atual e restaure no MySQL de produção. **Não rode
  `php artisan migrate`** nesse caso — as tabelas já existem; em vez disso, rode
  `php artisan migrate:install` (cria só a tabela de controle `migrations`) e marque
  a baseline como aplicada, do mesmo jeito que foi feito em dev (ver histórico do
  projeto — Passo 1 do corte). Confirme com `php artisan migrate:status`.

## 3. Deploy do Laravel (`backend-laravel/`)

```bash
cd /var/www/avasec/backend-laravel
composer install --no-dev --optimize-autoloader
cp .env.example .env   # ajuste os valores abaixo
php artisan key:generate

# .env de produção — valores obrigatórios:
#   APP_ENV=production
#   APP_DEBUG=false
#   APP_URL=https://seu-dominio.com
#   JWT_SECRET=<mesmo formato do Node: >=32 caracteres, aleatório>
#   BCRYPT_ROUNDS=10   (mantém compatibilidade com hashes já emitidos)
#   DB_CONNECTION=mysql / DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD
#   SESSION_DRIVER=file (ou redis, se preferir)
#   CACHE_STORE=file
#   QUEUE_CONNECTION=sync
#   UPLOADS_ROOT=/var/www/avasec/uploads   (pasta compartilhada, ver seção 5)
#   UPLOAD_MAX_SIZE_MB=15

php artisan config:cache
php artisan route:cache
php artisan optimize
```

**Checklist de segurança do `.env` de produção** (mesmas exigências já aplicadas no
backend Node — ver `HARDENING.md`):
- `JWT_SECRET` com no mínimo 32 caracteres, gerado aleatoriamente — nunca o valor de
  desenvolvimento.
- `APP_DEBUG=false` (nunca expor stack trace).
- `BCRYPT_ROUNDS=10` — mudar isso invalida a comparação com hashes antigos só se você
  também mudar o algoritmo; manter em 10 preserva compatibilidade.

## 4. Build do frontend (React/Vite)

```bash
cd /var/www/avasec
npm ci
npm run build   # gera dist/
```

O `dist/` é o que o Nginx serve como estático. Rode o build a cada deploy (CI/CD ou
manual).

## 5. Uploads compartilhados

A pasta `uploads/` (com `public/` e `private/`) deve existir num caminho estável e
ser apontada:
- No `.env` do Laravel: `UPLOADS_ROOT=/var/www/avasec/uploads`. Para o QR dos
  PDFs de certificado, `CERT_VERIFICATION_BASE_URL` é opcional (default `APP_URL`
  — em produção, garanta `APP_URL=https://seu-dominio.com`).
- No Nginx: `location /uploads/` aponta para `uploads/public/` (ver config abaixo).
  Arquivos **privados** nunca são servidos estaticamente — só via
  `GET /api/files/:id` (autorizado, através do PHP-FPM).

Garanta permissão de escrita para o usuário do PHP-FPM (`www-data` por padrão):

```bash
sudo mkdir -p /var/www/avasec/uploads/{public,private}
sudo chown -R www-data:www-data /var/www/avasec/uploads
sudo chmod -R 750 /var/www/avasec/uploads/private
sudo chmod -R 755 /var/www/avasec/uploads/public
```

## 6. Configuração do Nginx

```nginx
server {
    listen 80;
    server_name seu-dominio.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name seu-dominio.com;

    ssl_certificate     /etc/letsencrypt/live/seu-dominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/seu-dominio.com/privkey.pem;

    root /var/www/avasec/dist;
    index index.html;

    client_max_body_size 20m; # >= UPLOAD_MAX_SIZE_MB, com folga

    # ---- Cabeçalhos de segurança (equivalente ao helmet do backend Node) ----
    # CSP espelhando a política de produção anterior: bundle próprio, estilos inline
    # (o app injeta <style> de acessibilidade), imagens/vídeos de catálogo em https
    # (Unsplash/CDNs) e embeds do YouTube nas aulas.
    add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; media-src 'self' https:; frame-src 'self' https://www.youtube.com; connect-src 'self'; font-src 'self' data:; object-src 'none'; frame-ancestors 'self'" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Arquivos públicos enviados pelos usuários (materiais, biblioteca).
    location /uploads/ {
        alias /var/www/avasec/uploads/public/;
        add_header Content-Disposition "inline";
    }

    # API — encaminha para o PHP-FPM (Laravel).
    location /api/ {
        root /var/www/avasec/backend-laravel/public;
        try_files $uri /index.php?$query_string;

        location ~ \.php$ {
            fastcgi_pass unix:/run/php/php8.3-fpm.sock;
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        }
    }

    # SPA fallback: qualquer rota que não seja arquivo real cai no index.html do React.
    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

Ative o site e emita o certificado:

```bash
sudo ln -s /etc/nginx/sites-available/avasec /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d seu-dominio.com
```

## 7. PHP-FPM

Configuração padrão do pool (`/etc/php/8.3/fpm/pool.d/www.conf`) costuma servir bem
para ~500 alunos. Ajuste `pm.max_children` conforme a memória disponível do VPS.

**Limites de upload do PHP (obrigatório)**: o php.ini padrão limita uploads a
**2 MB** (`upload_max_filesize`) e o corpo do POST a **8 MB** (`post_max_size`) —
abaixo dos 15 MB da aplicação (`UPLOAD_MAX_SIZE_MB`) e dos 20 MB do Nginx
(`client_max_body_size`). Sem este ajuste, uploads acima de 2 MB falham em produção
mesmo com Nginx e aplicação corretos. Em `/etc/php/8.3/fpm/php.ini` (ou um drop-in
`conf.d/99-avasec.ini`):

```ini
upload_max_filesize = 16M   ; >= UPLOAD_MAX_SIZE_MB da aplicação
post_max_size = 20M         ; >= upload_max_filesize + folga do multipart
```

A cadeia deve manter `app (15M) <= upload_max_filesize <= post_max_size <=
client_max_body_size (20m)`. Reinicie o serviço após alterar (`sudo systemctl
restart php8.3-fpm`).

Garanta que o serviço sobe no boot:

```bash
sudo systemctl enable php8.3-fpm --now
```

Sem PM2, sem processo Node em produção — o PHP-FPM é gerido pelo systemd como
qualquer outro serviço padrão do Linux.

## 8. Verificação pós-deploy (checklist)

- [ ] `curl https://seu-dominio.com/` retorna o HTML do React.
- [ ] `curl https://seu-dominio.com/api/health-laravel` retorna `{"status":"ok","database":"ok"}`.
- [ ] Login funciona no navegador e o cookie `ava_session` aparece como HttpOnly/Secure
      (inspecionar em DevTools → Application → Cookies).
- [ ] Upload de um arquivo público funciona e a URL retornada carrega via `/uploads/...`.
- [ ] Upload privado: dono baixa (200), outro aluno não (403).
- [ ] `php artisan migrate:status` mostra tudo `Ran` — nenhuma migration pendente.
- [ ] Testes automatizados (`php artisan test` no `backend-laravel/`) rodam limpos
      contra o banco de produção **antes** de liberar tráfego real (ou contra uma
      cópia do banco, nunca direto em produção com dados reais).
- [ ] Logs do Laravel (`storage/logs/laravel.log`) e do Nginx sem erros recorrentes
      nas primeiras horas.

## 9. Rollback

Como o Passo 5 (desligar o Node) é a única parte irreversível do corte, o rollback
mais simples **antes** de descartar o Node é: apontar o Nginx de volta para o
processo Node (`server.ts` + PM2, como documentado em `HARDENING.md`) enquanto
investiga o problema no lado Laravel. Depois de desligar o Node de vez, o rollback
passa a ser: restaurar o backup do banco (feito antes do corte) e reverter o deploy
do Nginx/Laravel para a versão anterior.

## 10. Pendências conhecidas (fora do escopo deste corte)

- **`POST /api/dev/reset`** (reset do banco para o seed, só em dev): ferramenta do
  Node/Prisma, não migrada — não faz parte da API de produção. Se precisar do
  equivalente em Laravel, criar um `php artisan db:seed --class=...` dedicado.
- **Redis para rate limiting em cluster**: se um dia rodar múltiplas instâncias de
  PHP-FPM atrás de um load balancer, o rate limiter baseado em cache `file` deixa de
  ser preciso — trocar para `CACHE_STORE=redis` nesse cenário.
- **Serviço de vídeo dedicado**: continua fora do escopo do backend (Cloudflare
  Stream/Bunny), como já documentado desde o início do projeto.
