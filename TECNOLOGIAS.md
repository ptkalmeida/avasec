# AVASEC — Stack de Tecnologias

Detalhamento das tecnologias usadas na plataforma, conforme `package.json` e
`backend-laravel/composer.json`. Atualizado em 2026-07-24, após a migração do
backend para Laravel (histórico completo em `MIGRACAO_LARAVEL.md`).

## Visão geral

Aplicação **Laravel + MySQL** com telas em **Blade, Livewire e Tailwind** (MPA, ADR 017
e 020) e uma API REST sob `/api`, com autenticação JWT em cookie HttpOnly e camada de
segurança completa (rate limit, validação, RBAC, feature flags no servidor,
security headers). Em produção, o Nginx faz proxy para o PHP-FPM (`DEPLOY_LARAVEL.md`).
O React foi removido na fase M7 (ADR 020).

## Frontend

| Tecnologia | Versão | Papel |
|---|---|---|
| **TypeScript** | ~5.8 | Tipagem estática (verificação via `tsc --noEmit`) |
| **Livewire** | 4 | Interatividade das telas (traz o Alpine) |
| **Vite** | 6 | Build dos assets Blade (`npm run build`) |
| **Tailwind CSS** | 4 | Estilização por classes utilitárias |
| **lucide-react** | 0.546 | Ícones |
| **motion** (Framer Motion) | 12 | Animações e transições |
| **recharts** | 3 | Gráficos do dashboard administrativo |

## Backend (`backend-laravel/`)

| Tecnologia | Versão | Papel |
|---|---|---|
| **PHP** | 8.5 (mínimo 8.4) | Runtime do servidor. O `composer.lock` exige >= 8.4.1; 8.3 **não roda** — o `platform_check` aborta antes do Laravel carregar |
| **Laravel** | 13.35 | Framework HTTP / API REST |
| **Eloquent** | (Laravel) | ORM — models mapeiam o schema; migrations versionadas |
| **MySQL** | 8 | Banco de dados relacional (via Docker em dev) |
| **firebase/php-jwt** | 7.2 | JWT HS256 (sessão em cookie HttpOnly `ava_session`) |

Arquitetura em camadas: `routes → controllers (finos) → services (regra de
negócio) → Eloquent`, com middlewares (`jwt`, `active`, `role`, `feature`,
`SecurityHeaders`) e helpers em `app/Support/` (`Identity` para autorização
FK-first, `BusinessRules`, `Jwt`).

## Segurança

| Mecanismo | Implementação |
|---|---|
| Autenticação | JWT HS256 em cookie HttpOnly SameSite=Lax (12h), lockout 5 tentativas/15min |
| Senhas | bcrypt custo 10 via `password_hash`/`password_verify` nativos (compat. com hashes legados `$2a$` — ver ADR 05 na pasta .ai) |
| RBAC + status de conta | Middlewares `role:` e `active` (status reconferido no banco a cada request) |
| Rate limiting | Por rota sensível (login 10/15min, registro, upload, export, matrícula...) |
| Upload | Validação de magic-bytes (`finfo`), nome gerado no servidor, split público/privado com download autorizado |
| Headers | `SecurityHeaders` middleware na API + CSP/HSTS no Nginx (produção) |
| Auditoria | `SecurityLog` gravado só pelo servidor; telemetria (`ClientEvent`) separada |
| Validação | Regras do Laravel Validator em todas as rotas de escrita, erro padronizado `{error, code, message}` |

## Qualidade e ferramentas de desenvolvimento

| Tecnologia | Papel |
|---|---|
| **PHPUnit** | 13 — 363 testes do backend (`npm run test:api`) |
| **Laravel Pint** | Lint PSR-12 (`./vendor/bin/pint --test`) |
| **PHPStan + Larastan** | Análise estática, nível 9 (máximo) como gate (`phpstan.neon`) |
| **Playwright** | 1.64 — 13 testes de tela montada, em navegador real (`npm run e2e`). Exige o Laravel de pé (`npm run api`) e `npm run build` feito |
| **Docker / docker-compose** | MySQL 8 local (`npm run db:up`) e espelho de produção Nginx + PHP-FPM + Redis (`npm run espelho:up`) |
| **Composer / npm** | Dependências (backend / frontend) |

## Scripts principais (`package.json` da raiz)

| Comando | O que faz |
|---|---|
| `npm run db:up` / `db:down` | Sobe / derruba o MySQL via Docker |
| `npm run api` | Sobe o backend Laravel em `http://127.0.0.1:8000` |
| `npm run dev` | Vite de desenvolvimento dos assets Blade (porta 5174) |
| `npm run build` | Compila os assets das telas (`backend-laravel/public/build`) |
| `npm run test:api` | Testes do backend (PHPUnit) |
| `npm run e2e` | Testes de tela em navegador real (Playwright). **Exige `npm run api` de pé e `npm run build` feito** |
| `npm run e2e:relatorio` | Abre o relatório HTML da última execução do Playwright |
| `npm run test:api:coverage` | Cobertura do backend (PHPUnit + Xdebug, relatório em texto) |
| `npm run db:migrate` | Migrations do Laravel (`php artisan migrate`) |
| `npm run lint` | Type-check do frontend (`tsc --noEmit`) |

## Legado e observações

- **Backend Node/Express/Prisma anterior**: removido do repositório em
  26/08/2026, junto com as dependências npm dele e o `deploy/` (PM2). A rota de
  rollback foi descartada — não há intenção de voltar ao Node. O histórico está
  em `MIGRACAO_LARAVEL.md` e o código, no histórico do git.
- **Vídeo das aulas**: URLs externas (YouTube/CDN), validadas e canonicalizadas
  no backend com provider derivado por parsing (`app/Support/VideoSource.php` ↔
  `src/utils/videoSource.ts`, ADR 08). Se confidencialidade virar requisito, o
  degrau é serviço dedicado (Cloudflare Stream, Bunny) — não integrado.
- **Uploads**: disco local (`uploads/public` e `uploads/private`). Se o volume
  crescer, migrar para storage com URL assinada (S3 ou compatível).
- **Node**: 22 LTS no Git Bash (nvm) e 24 LTS no sistema — atende o requisito
  do Vite 6 (20+). Build de produção validado no 22.
- **Cobertura do backend**: medida via Xdebug (DLL em `C:\php 8.4\ext`, carregada
  só sob demanda pelo script `test:api:coverage`). Baseline em 27/07/2026:
  **49,8% de linhas** — a suíte cobre a superfície HTTP; ramos internos dos
  services são a lacuna registrada.

## Política de versões (09/10/2026)

Decisão: **mais nova estável**, com uma distinção que vale enquanto a migração
para MPA durar (ADR 017).

**Backend — atualizar sempre.** Ele sobrevive à migração. Em 09/10 subiu tudo
para a última estável, incluindo os majors: Laravel 13.35, PHPUnit 13,
firebase/php-jwt 7.2, Pint 1.32, Larastan 3.13, PHP 8.5.11.

**Frontend — só correções e minors.** O código React **será apagado** na fase M7.
Subir major de `react`, `vite`, `lucide-react` ou `motion` é trabalho que vai
junto com ele — em especial os 131 ícones do Lucide, que não serão migrados.

**Exceção à regra acima: vulnerabilidade.** O Vitest 3 arrastava duas falhas
**críticas** de RCE no `tinypool` (GHSA-5gmw-xhrv-c9v3 e GHSA-85c8-ppgw-ccpr).
Mesmo sendo dependência de desenvolvimento, foi subido para o Vitest 5 — os 716
testes passaram sem nenhuma alteração, e o `npm audit` zerou.

**Majors do frontend deixados para trás de propósito:** `typescript` 7,
`vite` 8, `@vitejs/plugin-react` 6, `jsdom` 30, `@types/node` 26,
`lucide-react` 1.54, `motion` 14. Nenhum tem advisory aberto.
