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

> **Mudou em 08/10/2026 (ADR 017).** O Laravel passou a ser a porta de entrada.
> Antes o Nginx tinha a raiz em `dist/` e o Laravel ficava pendurado em `/api`;
> agora a raiz é `backend-laravel/public`, e é o Laravel que decide, rota a rota,
> se entrega uma tela em Blade ou o SPA React. Isso é o que torna possível migrar
> uma tela de cada vez sem reconfigurar o servidor a cada fase — ver
> `.ai/planejamento/16-migracao-mpa-blade.md`.

```
[Navegador] ──HTTPS──> [Nginx]
                          ├── /assets/*      -> serve dist/assets/ (JS e CSS do build React)
                          ├── /uploads/*     -> serve uploads/public/ (arquivos estáticos)
                          └── /*             -> PHP-FPM (Laravel) ─── tabela de rotas
                                   │                                        │
                                   │                       ┌────────────────┴────────────────┐
                                   │                       ▼                                 ▼
                                   │                 rota registrada                 nada bateu
                                   │                 -> view Blade                   -> fallback
                                   │                                                 -> dist/index.html
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
#   LOG_CHANNEL=stack
#   LOG_STACK=daily        (um arquivo por dia, nunca um laravel.log único crescendo)
#   LOG_DAILY_DAYS=14      (retenção: arquivos mais antigos são descartados)
#   LOG_LEVEL=warning      (em produção; debug só em desenvolvimento)
#   Em container (Norma TI-SECEC C.7.2): LOG_STACK=stderr_json e LOG_APLICACAO=ava —
#   uma linha JSON por registro, com app e env, coletada pelo Grafana Alloy.

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
- **Log com rotação** (`LOG_STACK=daily` + `LOG_DAILY_DAYS`). Com o canal `single`, o
  `laravel.log` cresce sem limite: no ambiente de desenvolvimento chegou a 955 MB, a
  ~100 MB por dia, com um único usuário testando. Disco cheio em produção derruba a
  aplicação inteira. As negativas de acesso (401/403) já são gravadas como `warning`
  sem stack trace (`bootstrap/app.php`), o que corta a maior parte do volume; a rotação
  é a segunda barreira. Se preferir o `logrotate` do sistema, aponte-o para
  `storage/logs/*.log` e mantenha `LOG_STACK=single`.

## 4. Build do frontend (React/Vite)

```bash
cd /var/www/avasec
npm ci
npm run build   # gera dist/
```

O `dist/assets/` é servido direto pelo Nginx; o `dist/index.html` é lido pelo Laravel
e devolvido nas rotas que ainda não migraram para Blade (ADR 017). Rode o build a cada
deploy (CI/CD ou manual).

**Enquanto a migração durar, o build continua obrigatório.** Sem `dist/index.html` o
Laravel responde 503 nas telas não migradas, em vez de uma página quebrada — mas 503
é indisponibilidade, não "ainda não migrei". Isto deixa de valer na fase M7, quando o
React sair.

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

    # A raiz é o Laravel (ADR 017): ele decide, rota a rota, Blade ou React.
    root /var/www/avasec/backend-laravel/public;
    index index.php;

    client_max_body_size 20m; # >= UPLOAD_MAX_SIZE_MB, com folga

    # ---- Cabeçalhos de segurança (equivalente ao helmet do backend Node) ----
    # CSP espelhando a política de produção anterior: bundle próprio, estilos inline
    # (o app injeta <style> de acessibilidade), imagens/vídeos de catálogo em https
    # (Unsplash/CDNs) e embeds do YouTube nas aulas.
    add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; media-src 'self' https:; frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com; connect-src 'self'; font-src 'self' data:; object-src 'none'; frame-ancestors 'self'" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Arquivos públicos enviados pelos usuários (materiais, biblioteca).
    # IMPORTANTE: um bloco `location` com `add_header` próprio DESCARTA todos os
    # add_header herdados do server (inclusive os `always`). Como este conteúdo é
    # controlado por terceiros e servido na MESMA origem do SPA (onde vive o cookie
    # ava_session), os cabeçalhos de segurança precisam ser repetidos aqui — sem o
    # `nosniff`, um arquivo malicioso viraria XSS same-origin. Servir como attachment
    # (download) em vez de inline reduz ainda mais o risco de renderização no navegador.
    location /uploads/ {
        alias /var/www/avasec/uploads/public/;
        add_header Content-Security-Policy "default-src 'none'" always;
        add_header X-Content-Type-Options "nosniff" always;
        add_header Referrer-Policy "no-referrer" always;
        add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
        add_header Content-Disposition "attachment" always;
    }

    # Arquivos do build do React (JS, CSS, imagens com hash no nome).
    #
    # Continuam no `dist/`, servidos direto pelo Nginx: passá-los pelo PHP seria
    # desperdiçar um processo por arquivo estático. O `index.html` do `dist/` NÃO
    # é servido aqui — quem o entrega é o fallback do Laravel, porque é ele que
    # sabe se a rota já migrou para Blade.
    location /assets/ {
        alias /var/www/avasec/dist/assets/;
        access_log off;
        expires 1y;
        add_header Cache-Control "public, immutable";
        add_header Content-Security-Policy "default-src 'none'" always;
        add_header X-Content-Type-Options "nosniff" always;
        add_header Referrer-Policy "no-referrer" always;
        add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    }

    # Tudo o mais vai para o Laravel: API, saúde, status, telas já migradas para
    # Blade e — pelo fallback de routes/web.php — o SPA React no que ainda não
    # migrou. Não há mais um bloco `location` por área: a tabela de rotas do
    # Laravel é quem decide, e ela está versionada no repositório.
    location / {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
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
- [ ] `curl -i https://seu-dominio.com/health/ready` retorna **JSON** com status 200 (se vier
      HTML do React, o Nginx está sem o bloco de saúde) e `/sistema/status` abre a página.
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

O Node foi descartado em 26/08/2026 (código removido do repositório — ver
`MIGRACAO_LARAVEL.md`), então **não existe mais rollback para o backend Node**.
O rollback hoje é: restaurar o backup do banco e reverter o deploy do
Nginx/Laravel para a versão anterior.

## 10. Pendências conhecidas (fora do escopo deste corte)

- **`POST /api/dev/reset`** (reset do banco para o seed, só em dev): ferramenta do
  Node/Prisma, não migrada — não faz parte da API de produção. Se precisar do
  equivalente em Laravel, criar um `php artisan db:seed --class=...` dedicado.
- **Redis** (Norma TI-SECEC C.4): com mais de uma instância atrás do balanceador, o
  limite de tentativas de login em cache `file` deixa de ser compartilhado. A troca é
  só de configuração — `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `REDIS_PREFIX` no
  padrão `secec:ava:<ambiente>:` (já é o padrão do código) e `CACHE_PREFIX=cache:`. Ver
  `.env.example`. Sessão em Redis não se aplica: não há sessão no servidor (JWT).
- **Plano de implantação vs. Protocolo TI-SECEC**: este guia descreve um VPS com
  PHP-FPM. A norma pede Docker, imagem versionada no Harbor e a mesma imagem de HML
  para PRD (C.11, F.4). A reescrita deste guia está no plano futuro
  (`.ai/planejamento/11`, item F9) e depende da topologia definida pela TI.
- **Serviço de vídeo dedicado**: continua fora do escopo do backend (Cloudflare
  Stream/Bunny), como já documentado desde o início do projeto.
