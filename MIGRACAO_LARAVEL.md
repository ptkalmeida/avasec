# Migração incremental do backend AVASEC: Node/Express/Prisma → Laravel (PHP)

Relatório final da migração de backend, feita em 6 etapas (strangler pattern: Laravel
e Node rodaram lado a lado durante todo o processo, cada módulo migrado e testado
antes do próximo). Motivo da migração: padronização — o time trabalha com PHP/Laravel
em todos os outros ambientes, e o AVASEC era a única exceção em Node.

## Resultado

- **Backend inteiro reproduzido em Laravel** (`backend-laravel/`), com paridade de
  contrato JSON, regras de negócio e comportamento de segurança validada contra o
  Node em cada etapa.
- **55 testes PHPUnit** (Laravel) + **50 testes Vitest** (Node, inalterados) — ambos
  verdes ao final da migração.
- **Zero downtime durante o processo**: o Node nunca parou de funcionar; um proxy
  reverso (`LARAVEL_PROXY_PREFIXES`) permitiu ligar/desligar cada módulo migrado sem
  tocar no restante.
- **Interoperabilidade de autenticação comprovada nas duas direções**: um token
  emitido pelo Node é aceito pelo Laravel e vice-versa (mesmo segredo JWT); um hash
  de senha `bcryptjs` (Node, `$2a$`) é validado por `password_verify` (PHP) e
  vice-versa (`$2y$` validado por `bcryptjs`).
- **Nada foi commitado automaticamente** — cabe ao time revisar e commitar quando
  julgar pronto.

## Etapas executadas

| Etapa | Escopo | Testes |
|---|---|---|
| 0 | Skeleton Laravel + proxy de desenvolvimento | rota de saúde |
| 1 | Módulo piloto: Biblioteca Digital + Eventos/Webinars | 6 |
| 2 | Autenticação (login, registro, lockout, RBAC, sessão em cookie) | 15 |
| 3 | Núcleo de negócio: Cursos, Matrículas/Admissões, Certificados | 16 |
| 4 | Uploads (público/privado, validação de magic-bytes) | 5 |
| 5 | Módulos restantes: quizzes, fórum, exercícios, chat, DMs, solicitações acadêmicas, configurações, auditoria/telemetria, export | 12 |
| 6 | Corte final: baseline de migrations, validação 100% Laravel, dev pós-corte, doc de produção | — |

## Decisões técnicas tomadas (e por quê)

1. **Autenticação: JWT em cookie, não Sanctum SPA.** Optou-se por reproduzir
   exatamente o mecanismo do Node (`firebase/php-jwt`, mesmo segredo HS256, mesmo
   cookie `ava_session`) em vez de migrar para sessão+CSRF do Sanctum. Motivo: os
   testes de segurança já existentes descreviam o comportamento exato a reproduzir;
   trocar o mecanismo de sessão no meio da migração adicionaria uma variável de risco
   desnecessária.

2. **Hash de senha via funções nativas do PHP, não o `Hash` facade do Laravel.**
   O `Hash::check` do Laravel rejeita hashes com prefixo `$2a$` (gerados pelo
   `bcryptjs` do Node). A correção foi usar `password_verify`/`password_hash`
   nativos do PHP, que lidam com `$2a$`/`$2b$`/`$2y$` de forma intercambiável — isso
   foi **validado empiricamente antes** de qualquer código de autenticação ser
   escrito (ver Etapa 2).

3. **Segredo JWT precisou crescer para ≥32 caracteres.** A lib `firebase/php-jwt`
   exige chaves HS256 de no mínimo 32 bytes; o `jsonwebtoken` do Node aceitava
   segredos menores em desenvolvimento (a produção já exigia ≥32, por guarda
   explícita em `env.ts`). O segredo de dev foi alinhado ao padrão de produção.

4. **Prisma continuou dono do schema durante toda a migração**, até o corte final.
   O Laravel só mapeou Eloquent models para as tabelas já existentes, sem gerar ou
   rodar migrations próprias, evitando duas ferramentas de schema mexendo no mesmo
   banco ao mesmo tempo. No corte final, uma **baseline de migrations foi gerada por
   introspection** do schema real (`kitloong/laravel-migrations-generator`), validada
   contra um banco novo (24 tabelas recriadas identicamente, zero diferença), e
   registrada como já aplicada no banco de desenvolvimento — sem tocar em nenhum
   dado existente.

5. **Proxy reverso por prefixo de rota** (`http-proxy-middleware` no `server.ts` do
   Node, controlado por `LARAVEL_PROXY_PREFIXES`), em vez de expor o Laravel numa
   origem separada. Isso manteve uma única origem para o navegador durante toda a
   migração — sem precisar resolver CORS/cookie cross-origin (`SameSite=None` +
   HTTPS obrigatório) até o corte final, quando deixa de ser necessário de vez (a
   topologia final também usa origem única: Nginx + PHP-FPM).

6. **Uploads gravados num diretório compartilhado** entre Node e Laravel durante a
   migração, para que arquivos públicos enviados por qualquer um dos dois backends
   continuassem acessíveis pela mesma rota estática.

## Arquivos e estrutura criados

- **`backend-laravel/`** — projeto Laravel 13 completo:
  - `app/Models/` — 15 Eloquent models mapeando as tabelas existentes (sem
    migrations próprias até o corte; `$incrementing=false`, `$keyType=string` para
    as PKs cuid/string do Prisma).
  - `app/Services/` — camada de regra de negócio (`AuthService`, `CourseService`,
    `EnrollmentService`, `CertificateService`, `UploadService`, `LearningService`,
    `MessagingService`, `RequestService`, `SettingsService`, `AuditLogService`,
    `ExportService`), espelhando 1:1 os services do Node.
  - `app/Http/Controllers/` — 12 controllers, sem regra de negócio (delegam aos
    services).
  - `app/Http/Middleware/` — `JwtAuthenticate`, `OptionalJwt`, `RequireActiveAccount`,
    `RequireRole`, `FeatureGate` — equivalentes aos middlewares do Node.
  - `app/Support/` — `Jwt` (emissão compatível com o Node), `Identity` (ownership
    FK-first com fallback por nome), `BusinessRules` (frequência mínima, penalidade).
  - `app/Exceptions/ApiException.php` — erro padronizado, serializado como
    `{error:true, code, message}` (mesmo contrato do Node).
  - `config/features.php`, `config/constants.php`, `config/uploads.php` — réplicas
    das fontes de verdade compartilhadas do Node (mantidas em sincronia manual).
  - `database/migrations/` — baseline gerada por introspection (40 arquivos).
  - `tests/Feature/` — 55 testes PHPUnit cobrindo todos os módulos.
- **`src/server/middlewares/laravelProxy.ts`** (Node) — proxy reverso por prefixo.
- **`vite.config.ts`** — proxy `/api` e `/uploads` para o Laravel (modo dev
  pós-corte).
- **`package.json`** — script `dev:laravel` (Vite standalone, sem o Express no meio).
- **`DEPLOY_LARAVEL.md`** — guia de deploy de produção (Nginx + PHP-FPM + MySQL).
- **`.env`** (Node) e **`backend-laravel/.env`** — `JWT_SECRET` alinhado (≥32 chars),
  `LARAVEL_URL`/`LARAVEL_PROXY_PREFIXES` (Node) para controlar o proxy.

## Riscos identificados e como foram tratados

| Risco | Tratamento |
|---|---|
| Hashes de senha incompatíveis entre bcryptjs e Laravel | Validado empiricamente antes de escrever código; uso de funções nativas do PHP |
| Cookie de sessão quebrar ao introduzir uma segunda origem | Proxy reverso manteve origem única durante toda a migração |
| Duas ferramentas de schema (Prisma + Laravel) divergindo | Prisma manteve exclusividade até o corte; Laravel só leu, nunca escreveu schema |
| Regressão silenciosa em regra de negócio (frequência, penalidade, ownership) | Cada service replicado com testes espelhando 1:1 os testes de negócio do Node |
| Migrations geradas por introspection não baterem com o schema real | Validado recriando o schema num banco novo e comparando tabela a tabela (0 diferenças) |
| `.docx` detectado como `application/zip` pelo `finfo` (não pelo MIME específico) | Ambos os MIMEs aceitos explicitamente na config de upload, documentado |

## Corte final — EXECUTADO

Após validação completa (55 testes PHPUnit + smoke test de todos os módulos via
proxy + **teste no navegador headless**: landing page, login admin com PIN e
dashboard administrativo carregando dados reais servidos pelo Laravel), o backend
Node foi primeiro arquivado em `legacy-node/` e, depois, **removido de vez**:

- `npm run dev` sobe o Vite standalone (proxy `/api`/`/uploads` → Laravel);
  `npm run api` sobe o Laravel; `npm run test:api` roda o PHPUnit;
  `npm run db:migrate` roda as migrations do Laravel. Scripts do Prisma removidos.
- `npm run build` gera apenas o `dist/` do frontend (o servidor de produção é o
  PHP-FPM/Nginx — ver `DEPLOY_LARAVEL.md`).

## Descarte do Node (rollback encerrado)

Em 26/08/2026, por decisão do time, a rota de rollback foi **descartada**: não há
intenção de voltar ao Node. Foram removidos:

- `legacy-node/` (82 arquivos: `server.ts`, `src/server/`, `prisma/` e a suíte
  Vitest do backend).
- `deploy/` (PM2 + Nginx apontando para a porta 3000 — o deploy atual é
  Nginx + PHP-FPM + systemd, ver `DEPLOY_LARAVEL.md` e ADR 07).
- As dependências npm do backend Node: `express`, `@prisma/client`, `prisma`,
  `bcryptjs`, `jsonwebtoken`, `cookie-parser`, `cors`, `helmet`,
  `express-rate-limit`, `multer`, `file-type`, `dotenv`, `zod`, `supertest`,
  `tsx`, `http-proxy-middleware`, `autoprefixer`, `esbuild` e os `@types`
  correspondentes.

Tudo permanece recuperável pelo histórico do git (os arquivos eram rastreados).
Nenhum dado do banco foi alterado.

## Pendências conhecidas (não migradas, de baixo risco)

- `POST /api/dev/reset`: ferramenta de conveniência do Prisma para resetar o seed em
  desenvolvimento — não faz parte da API de produção, não precisa de equivalente.
- Rate limiting distribuído (Redis) para múltiplas instâncias de PHP-FPM atrás de
  load balancer — só relevante se a escala crescer além de uma única instância.
- Serviço de vídeo dedicado (Cloudflare Stream/Bunny) — fora do escopo do backend
  desde o início do projeto.

## Testes — como rodar

```bash
# Frontend (Vitest + React Testing Library)
npm test

# Backend (PHPUnit/Laravel — exige o MySQL de dev no ar: npm run db:up)
npm run test:api
```

## Desenvolvimento local — fluxo pós-corte

```bash
npm run db:up   # MySQL (Docker)
npm run api     # Laravel em http://127.0.0.1:8000
npm run dev     # Vite em http://localhost:5173 (proxy /api e /uploads -> Laravel)
```
