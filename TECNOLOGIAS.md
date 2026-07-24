# AVASEC — Stack de Tecnologias

Detalhamento das tecnologias usadas na plataforma, conforme o `package.json` e a
arquitetura atual do projeto. Gerado em 2026-07-23.

## Visão geral

Aplicação **full-stack TypeScript** servida por um único processo Node:
**React + Vite + Tailwind** no frontend, **Express + Prisma + MySQL** no backend,
com autenticação JWT em cookie HttpOnly e uma camada de segurança (helmet, CORS,
rate limit, validação Zod, RBAC).

## Linguagem base

| Tecnologia | Versão | Papel |
|---|---|---|
| **TypeScript** | ~5.8 | Tipagem estática em todo o projeto (frontend, backend, testes). Verificação via `tsc --noEmit`. |
| **Node.js** | 18 | Runtime do servidor. *Recomenda-se Node 20 LTS antes de produção (Vite 6 pede Node 20+).* |

## Frontend

| Tecnologia | Versão | Papel |
|---|---|---|
| **React** | 19 | Biblioteca de UI (componentes) |
| **Vite** | 6 | Build tool + dev server com hot reload |
| **Tailwind CSS** | 4 | Estilização por classes utilitárias |
| **lucide-react** | 0.546 | Ícones (monocromáticos) |
| **motion** (Framer Motion) | 12 | Animações e transições |
| **recharts** | 3 | Gráficos do dashboard administrativo |

## Backend

| Tecnologia | Versão | Papel |
|---|---|---|
| **Express** | 4 | Framework HTTP / API REST |
| **Prisma** | 6 | ORM (acesso ao banco, migrations, seed) |
| **MySQL** | 8 | Banco de dados relacional (via Docker) |
| **Zod** | 4 | Validação de entrada das rotas |

Arquitetura em camadas no backend: `routes → controllers → services`, com
middlewares (auth, RBAC, rate limit, feature flags) e validators separados
(pasta `src/server/`).

## Segurança

| Tecnologia | Papel |
|---|---|
| **jsonwebtoken** | Autenticação por JWT (sessão em cookie HttpOnly, SameSite=Lax) |
| **bcryptjs** | Hash de senha |
| **helmet** | Cabeçalhos de segurança HTTP + Content Security Policy |
| **cors** | Controle de origens permitidas (por variável de ambiente) |
| **express-rate-limit** | Limite de tentativas (login, upload, exportação, etc.) |
| **cookie-parser** | Leitura do cookie de sessão |
| **multer** + **file-type** | Upload de arquivos com validação real de tipo (magic bytes) |

## Infraestrutura e ferramentas de desenvolvimento

| Tecnologia | Papel |
|---|---|
| **Docker / docker-compose** | Sobe o MySQL 8 local para desenvolvimento |
| **tsx** | Executa o servidor TypeScript direto em dev (`npm run dev`) |
| **esbuild** | Empacota o servidor para produção (`npm run build`) |
| **Vitest** + **Supertest** | Testes automatizados (34 testes de segurança / regras de negócio) |
| **dotenv** | Variáveis de ambiente (`.env`) |

## Scripts principais (`package.json`)

| Comando | O que faz |
|---|---|
| `npm run dev` | Sobe o servidor de desenvolvimento (Express + Vite) em `http://localhost:3000` |
| `npm run build` | Gera o build de produção (frontend com Vite + servidor com esbuild) |
| `npm run start` | Roda o build de produção |
| `npm test` | Executa a suíte de testes (Vitest) |
| `npm run db:up` / `db:down` | Sobe / derruba o MySQL via Docker |
| `npm run db:migrate` | Aplica as migrations do Prisma |
| `npm run db:seed` | Popula o banco com os dados iniciais |
| `npm run db:studio` | Abre o Prisma Studio (visualizador do banco) |

## Observações

- **`@google/genai`** está instalado (herança do template original do Google AI
  Studio), mas **não está em uso** na plataforma atual — pode ser removido para
  enxugar as dependências.
- **Vídeo das aulas**: hoje são URLs externas (YouTube / CDN). Para produção, o
  recomendado é um **serviço de vídeo dedicado** (Cloudflare Stream, Bunny), ainda
  não integrado.
- **Uploads de arquivos** (materiais, entregas) são gravados em disco local
  (`uploads/public` e `uploads/private`). Se o volume crescer, migrar para storage
  com URL assinada (S3 ou compatível).
