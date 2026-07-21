# Guia de Deploy do AVASEC em um VPS

Este guia assume um VPS Linux (Ubuntu/Debian) limpo, com acesso root/sudo, e um domínio
já apontando para o IP do servidor.

## 1. Dependências do servidor

```bash
sudo apt update && sudo apt install -y nginx docker.io docker-compose-plugin git
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
sudo npm install -g pm2
```

> O projeto usa Vite 6, que requer Node 20+. Se o VPS tiver Node 18, atualize antes de seguir.

## 2. Clonar o projeto e instalar

```bash
sudo mkdir -p /var/www/avasec && sudo chown $USER:$USER /var/www/avasec
git clone <url-do-seu-repositorio> /var/www/avasec
cd /var/www/avasec
npm install
```

## 3. Banco de dados (MySQL via Docker)

O `docker-compose.yml` do repositório sobe um MySQL 8 com volume persistente — o mesmo usado
em desenvolvimento serve para produção num VPS pequeno (~500 alunos):

```bash
docker compose up -d
```

Crie o `.env` de produção (NÃO reutilize o `.env` de desenvolvimento):

```bash
cp .env.example .env
```

Edite `.env` e defina:
- `DATABASE_URL` — se usar o `docker-compose.yml` do repo como está, mantenha
  `mysql://avasec:avasec@localhost:3306/avasec`, mas troque a senha do usuário/root para algo
  forte antes de ir ao ar (edite `docker-compose.yml` e `db/init/01-grant-shadow-db.sql`).
- `JWT_SECRET` — gere um valor aleatório forte, por exemplo: `openssl rand -base64 48`.

Aplique as migrations e popule os dados iniciais:

```bash
npx prisma migrate deploy
npx prisma db seed
```

> `migrate deploy` (não `migrate dev`) é o comando correto para produção — ele só aplica
> migrations já existentes, sem tentar criar/alterar interativamente.

## 4. Build do frontend + backend

```bash
npm run build
```

Isso gera `dist/` (frontend estático) e `dist/server.cjs` (backend compilado).

## 5. Subir o servidor com PM2

```bash
mkdir -p logs uploads
pm2 start deploy/ecosystem.config.cjs
pm2 save
pm2 startup   # siga a instrução impressa para o PM2 iniciar sozinho no boot
```

O servidor Node escuta em `127.0.0.1:3000` — não é exposto diretamente à internet, apenas
através do Nginx (passo seguinte).

## 6. Nginx + HTTPS

```bash
sudo cp deploy/nginx.conf /etc/nginx/sites-available/avasec
sudo sed -i 's/seu-dominio.com.br/SEU_DOMINIO_REAL/' /etc/nginx/sites-available/avasec
sudo ln -s /etc/nginx/sites-available/avasec /etc/nginx/sites-enabled/avasec
sudo nginx -t && sudo systemctl reload nginx

sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d SEU_DOMINIO_REAL
```

O Certbot configura HTTPS automaticamente e agenda a renovação do certificado.

## 7. Verificação pós-deploy

- `curl https://SEU_DOMINIO_REAL/api/health` deve responder `{"status":"ok",...}`.
- Acesse o site pelo domínio, faça login com um usuário do seed (ver `prisma/seedData.ts`)
  e confirme que os cursos aparecem.
- Reinicie o processo (`pm2 restart avasec`) e confirme que os dados continuam lá (prova de
  que a persistência é real, não mais em memória).
- Envie um PDF de teste em "Adicionar Material" no painel do instrutor e confirme que o
  arquivo aparece em `/var/www/avasec/uploads/`.

## 8. Backups

O dado que importa está no volume do Docker (`avasec_mysql_data`) e na pasta `uploads/`.
Rotina mínima recomendada (cron diário):

```bash
docker exec avasec-mysql mysqldump -uavasec -pavasec avasec > /var/backups/avasec-$(date +%F).sql
tar czf /var/backups/avasec-uploads-$(date +%F).tar.gz /var/www/avasec/uploads
```

## 9. Vídeo das aulas

Este servidor NÃO deve hospedar os vídeos das aulas (custo de banda/disco). Contrate um
serviço de vídeo dedicado (Cloudflare Stream ou Bunny Stream), faça upload dos vídeos lá, e
salve apenas a URL/ID de reprodução retornado no campo `videoUrl` de cada aula (já suportado
pelo modelo de dados e pelo player do frontend). Isso é uma etapa manual/operacional, não uma
mudança de código.
