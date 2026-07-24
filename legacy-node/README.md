# legacy-node — Backend Node/Express/Prisma (ARQUIVADO)

Este diretório contém o backend original do AVASEC (Node.js + Express + Prisma),
**arquivado no corte final da migração para Laravel** (ver `../MIGRACAO_LARAVEL.md`).
O backend ativo agora é o Laravel em `../backend-laravel/`.

## O que está aqui

| Item | O que era |
|---|---|
| `server.ts` | Entrypoint: Express + Vite middleware (dev) / estáticos (prod) + proxy da migração |
| `src/server/` | API completa: rotas, controllers, services, middlewares, validators Zod |
| `prisma/` | Schema, migrations e seed do Prisma (o schema agora é das migrations do Laravel) |
| `tests/` | Suíte Vitest do backend (50 testes — espelhados 1:1 nos 55 testes PHPUnit do Laravel) |
| `vitest.backend.config.ts` | Config do projeto de testes do backend |

## Por que não foi deletado

Decisão do time: manter como referência e rota de rollback de curto prazo. Os arquivos
**não compilam mais no lugar atual** (os imports relativos apontavam para `src/config/`
e `src/types.ts` da raiz) — isso é esperado; eles existem para leitura e para rollback,
não para execução daqui.

## Rollback (voltar a rodar o backend Node)

1. Mover tudo de volta ao lugar original:
   ```bash
   mv legacy-node/server.ts server.ts
   mv legacy-node/src/server src/server
   mv legacy-node/prisma prisma
   mv legacy-node/tests/*.test.ts legacy-node/tests/setup.ts tests/
   mv legacy-node/vitest.backend.config.ts vitest.backend.config.ts
   ```
2. Restaurar os scripts do `package.json` (ver histórico do git: `dev`, `build`, `start`,
   `db:migrate`, `db:seed`, `db:generate`, `db:studio`, `db:reset`, `test:backend` e o
   bloco `"prisma"`), o projeto `backend` no `vitest.config.ts` e remover o `exclude`
   de `legacy-node` no `tsconfig.json`.
3. `npm run dev` volta a subir o full-stack Node como antes.

As dependências npm do backend (express, prisma, bcryptjs, etc.) foram **mantidas** no
`package.json` justamente para o rollback ser só "mover de volta" — removê-las é uma
limpeza futura, a fazer quando o time não precisar mais desta rota de segurança.
