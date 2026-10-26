# Entrega do AVASEC — Docker → Harbor → HML → PRD

Guia de referência para colocar o AVASEC em homologação (HML) e produção (PRD), no fluxo que o
Protocolo TI-SECEC pede (Documento B §3.7–3.8; Documento F, F.4 e F.12; Norma C.3 e C.11).

> **Reescrito em 26/10/2026** (plano 11, item F9). A versão anterior descrevia um VPS montado à
> mão (Nginx e PHP-FPM instalados na máquina), que **conflita com a norma**: ela pede imagem
> versionada no Harbor e **a mesma imagem** indo de HML para PRD. O guia de VPS segue no histórico
> do git (`git log -- DEPLOY_LARAVEL.md`) para quem precisar.
>
> **Estado:** rascunho **validado só localmente**. Os nomes, as redes e a entrega das variáveis
> podem mudar quando a TI definir o compose padrão (F.29 item 2). O desenho não muda.

## Arquitetura

O Laravel é a única aplicação (MPA — ADR 017 e 020): não há SPA, `dist/` nem fallback; endereço que
nenhuma rota atende é 404 em Blade.

```
[Navegador] ─HTTPS─> [ponto de entrada da TI: domínio, TLS, HSTS]
                              │
                              ▼
                        ava-<amb>-web  (Nginx)
                          ├── /uploads/*  -> arquivos públicos (volume; MinIO no futuro)
                          ├── /build/*    -> estáticos de public/ (CSS e JS das telas)
                          └── /*          -> FastCGI
                              │
                              ▼
                        ava-<amb>-app   (PHP-FPM + Laravel) ──> MySQL 8 (base própria do ambiente)
                        ava-<amb>-queue      (mesma imagem, `queue:work`)
                        ava-<amb>-scheduler  (mesma imagem, `schedule:work`)
```

Uma origem só para tudo (tela e API): o cookie `ava_session` (HttpOnly, SameSite=Lax) funciona sem
CORS.

## O que existe no repositório

| Arquivo | Para quê |
|---|---|
| `docker/producao/Dockerfile` | Imagens `app` (PHP-FPM, queue e scheduler) e `web` (Nginx). Compila os assets (Vite + Tailwind), instala o Composer sem dev e **falha o build** se uma extensão PHP não carregar ou se o manifesto de assets não existir |
| `docker/producao/entrypoint.sh` | Guarda a partida: recusa subir sem `APP_KEY`, `JWT_SECRET` e as variáveis do banco; faz o cache de config, rotas e telas. **Não roda migrations** |
| `docker/producao/web.conf`, `upstream.conf` | O que é do ambiente no Nginx: raiz e destino do FastCGI |
| `docker/nginx/avasec-locations.conf` | Os `location` — **o mesmo arquivo** do espelho local e da imagem |
| `docker/nginx/avasec-cabecalhos.conf` | CSP e cabeçalhos de segurança — também compartilhado |
| `docker-compose.hml.yml` | Os quatro containers, com nomes `ava-<ambiente>-<serviço>` (F.14) |
| `docker/producao/hml.env.example` | Molde das variáveis. O real vem do Infisical (F.11) e nunca entra no repositório |
| `docker-compose.espelho.yml` | Espelho **local** de produção (`npm run espelho:up`, porta 8080). Não é homologação |

## 1. Construir e publicar a imagem

A imagem é construída **uma vez**, a partir da versão aprovada, e é a mesma em HML e PRD.

```bash
VERSAO=2026.10.26-1
docker build -f docker/producao/Dockerfile --target app -t <harbor>/ava/app:$VERSAO .
docker build -f docker/producao/Dockerfile --target web -t <harbor>/ava/web:$VERSAO .
docker push <harbor>/ava/app:$VERSAO
docker push <harbor>/ava/web:$VERSAO
```

Requisitos do runtime: **PHP 8.4 ou superior** (o `composer.lock` exige 8.4.1; em 8.3 a aplicação
não sobe) com `gd`, `pdo_mysql`, `bcmath`, `intl` e `zip`. A imagem usa PHP 8.5.

## 2. Variáveis de ambiente

Tudo vem de variáveis, sem credencial em código. Molde em `docker/producao/hml.env.example`.

Checklist de segurança do ambiente:

- `JWT_SECRET` com **32 caracteres ou mais**, aleatório e **diferente em cada ambiente**. Trocá-lo
  derruba todas as sessões.
- `APP_DEBUG=false`. `APP_ENV=production` também em HML (é a mesma arquitetura, F.2): é com ele que
  o cookie de sessão sai com `Secure`, o que **exige o domínio com TLS**.
- `BCRYPT_ROUNDS=10`: mudar não invalida hashes antigos, mas mantém a compatibilidade.
- Logs em container: `LOG_STACK=stderr_json`, `LOG_LEVEL=warning`, `LOG_APLICACAO=ava` — uma linha
  JSON por registro, na saída de erro, para o Grafana Alloy coletar (C.7.2).
- `UPLOADS_ROOT=/var/www/avasec/uploads` e `UPLOAD_MAX_SIZE_MB=15`. A cadeia de limites é
  `app (15M) ≤ upload_max_filesize (20M) ≤ post_max_size (21M) ≤ client_max_body_size (20m)`; a
  imagem e o Nginx já a respeitam.
- `APP_URL` com o domínio público: sai impresso no QR dos certificados
  (`CERT_VERIFICATION_BASE_URL` é opcional e, vazio, usa o `APP_URL`).
- `SESSION_DRIVER=file`, `CACHE_STORE=file` e `QUEUE_CONNECTION=sync` — **não** `database`: as tabelas
  `sessions`, `cache` e `jobs` não existem nas migrations, e com `database` toda tela responde 500
  (defeito encontrado ao subir estas imagens, 26/10/2026). Em arquivo o estado é de cada container:
  vale para **uma** instância `app`; com mais de uma, ou para os contadores de login (limitadores e
  bloqueio por rede, ADR 022) sobreviverem a um reinício, use o Redis.
- Redis (F1) e MinIO (F3), quando a TI entregar, entram só nestas variáveis. `REDIS_PREFIX` no padrão
  `secec:ava:<ambiente>:` — HML e PRD nunca iguais.

## 3. Banco de dados

MySQL 8, **base própria do ambiente**, fora do container da aplicação (F.2, F.7). `utf8mb4` /
`utf8mb4_unicode_ci`.

- **Banco novo (o caso do HML):**
  ```bash
  docker compose -f docker-compose.hml.yml run --rm app php artisan migrate --force
  ```
  As migrations criam o schema inteiro. **Seeds de demonstração**
  (`php artisan db:seed --force`) só em HML, e só com a decisão do responsável sobre os dados; nunca
  em PRD. As contas de demonstração têm senhas rotacionadas (08/09/2026) e as atuais não estão no
  repositório: forneça as credenciais de teste à escola por canal seguro.
- **Migrar um banco existente (PRD com dados reais):** restaure o `mysqldump` e **não** rode
  `migrate` do zero — as tabelas já existem. Rode `php artisan migrate:install` e marque a baseline
  como aplicada; confirme com `php artisan migrate:status`. Antes de qualquer `migrate` em dados
  reais: backup (F.7) e ensaio numa cópia.
- As migrations só acrescentam (ADR 12): nada é apagado, e o `down()` não remove coluna de dado
  histórico.

**Norma C.2.5:** depois de HML/PRD no ar, **não se altera o tipo de coluna**. As migrations deste
ciclo são aditivas.

## 4. Subir

```bash
export AMBIENTE=hml REGISTRY=<harbor>/ava VERSAO=2026.10.26-1 ENV_FILE=/caminho/do/hml.env
docker compose -f docker-compose.hml.yml pull
docker compose -f docker-compose.hml.yml run --rm app php artisan migrate --force   # só quando houver migration
docker compose -f docker-compose.hml.yml up -d
```

O `web` expõe HTTP puro na porta `WEB_PORT` (padrão `127.0.0.1:8090`). **TLS, domínio e HSTS são do
ponto de entrada da TI**, na frente do `web`. O HSTS
(`Strict-Transport-Security: max-age=31536000; includeSubDomains`) é adicionado lá, e não na imagem,
porque só vale com HTTPS.

## 5. Cabeçalhos de segurança e a CSP

`docker/nginx/avasec-cabecalhos.conf` é a fonte única; o espelho local e a imagem o incluem.

- **`script-src 'self' 'unsafe-eval'`**: o Alpine.js (embutido no Livewire 4) compila as expressões
  das telas com `new Function`. Sem `'unsafe-eval'`, o menu, as perguntas da Ajuda e a prévia do
  editor **param de funcionar atrás do Nginx** (e funcionam no servidor de desenvolvimento, que não
  tem Nginx — foi assim que o defeito apareceu). **Não há `'unsafe-inline'`**: script embutido segue
  bloqueado. Retirar o `'unsafe-eval'` exige trocar o Alpine pelo build CSP e reescrever as
  expressões das 21 telas; é melhoria registrada, e a troca é uma linha neste arquivo.
- **`fonts.googleapis.com` e `fonts.gstatic.com`**: o layout usa Inter e Playfair Display do Google
  Fonts. Hospedá-las no servidor tira essa dependência.
- **`Referrer-Policy: strict-origin-when-cross-origin`**: `no-referrer` quebrava o player do YouTube.

O bloco `/uploads/` repete os cabeçalhos de propósito: um `add_header` dentro de `location` descarta
os herdados, e o conteúdo é de terceiros, na mesma origem do cookie.

## 6. Uploads

Pasta `uploads/` (`public/` e `private/`) num **volume** compartilhado entre `app` (escreve) e `web`
(serve `public/` em `/uploads/`). Arquivos **privados** nunca saem estaticamente: só por
`GET /api/files/{id}`, autorizado, pelo PHP. Quando o MinIO (F3) chegar, o volume sai e a troca é de
disco no Laravel Filesystem. Faça backup do volume (F.7).

## 7. Verificação pós-deploy

Rode **antes** de entregar à escola para aceite:

- [ ] `curl -i https://<dominio>/` retorna a página inicial em HTML do Blade (sem `id="root"`) e traz
      `Content-Security-Policy`, `X-Content-Type-Options` e `Strict-Transport-Security`.
- [ ] `/health/live` e `/health/ready` respondem **JSON** 200 (HTML = o Nginx está sem o bloco de
      saúde). `/sistema/status` abre a página.
- [ ] `/api/health-laravel` retorna `{"status":"ok","database":"ok"}`.
- [ ] No navegador, **os botões funcionam**: menu lateral, perguntas da Ajuda, prévia do editor de
      aula. (Se não, a CSP está bloqueando; ver o console.)
- [ ] Login por perfil (aluno por CPF; gestão e admin por e-mail) e o cookie `ava_session` como
      HttpOnly e Secure (DevTools → Application → Cookies).
- [ ] "Sair" encerra a sessão de verdade: reapresentar o token antigo dá 401 (ADR 022).
- [ ] Upload público por instrutor funciona e carrega em `/uploads/...`; aluno recebe 403 no público;
      upload privado: dono baixa (200), outro aluno não (403).
- [ ] `php artisan migrate:status` com tudo `Ran`.
- [ ] Logs dos quatro containers sem erro recorrente nas primeiras horas.
- [ ] A suíte de testes (`php artisan test` e `npx playwright test`) rodou limpa **contra o espelho
      local** (`npm run espelho:up`, `AVASEC_URL=http://localhost:8080`) antes de pedir o aceite. Nunca
      contra um banco com dados reais.

## 8. Promoção de HML para PRD

A versão implantada em produção é a **homologada** (B §3.8): promova a **mesma tag** de imagem, sem
reconstruir. O que muda é só o arquivo de variáveis (credenciais, domínio, capacidade). Registre a
aceitação da escola (B §3.7) antes.

## 9. Rollback

O backend Node foi descartado em 26/08/2026 e o React em 22/10/2026 (ADR 020): não há retorno para
eles. O rollback é **a tag anterior da imagem** (`VERSAO=<anterior>` e `up -d`) mais, se a versão
tiver migration, o backup do banco restaurado — as migrations só acrescentam, então a imagem
anterior costuma rodar sobre o schema novo, mas confirme caso a caso.

## 10. Pendências conhecidas

- **Compose padrão da TI** (F.29 item 2) e entrega das variáveis pelo Infisical (F.11.2): este
  rascunho ainda não foi validado com a TI.
- **Redis, MinIO, Loki/Prometheus, ProxySQL** (F1, F3, F7, F11): dependem da TI; a aplicação está
  pronta para cada um por configuração. Com mais de uma instância, o contador de erros de login em
  cache de banco/arquivo deixa de ser compartilhado — Redis resolve.
- **Queue e scheduler**: os containers existem pela norma (F.4.4), mas hoje nada usa fila nem agenda
  tarefa.
- **Fontes hospedadas no servidor** e **Alpine em build CSP**: melhorias de segurança da seção 5.
- **Serviço de vídeo dedicado**: fora do escopo do backend (Cloudflare Stream/Bunny).
