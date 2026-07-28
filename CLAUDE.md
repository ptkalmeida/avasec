# AVASEC — Ambiente Virtual de Aprendizagem

LMS da Escola Estadual da Cultura. Frontend React 19 + Vite 6 + Tailwind 4
(TypeScript) consumindo API REST em **Laravel** (`backend-laravel/`, PHP 8.4) com
MySQL 8. O backend Node/Express/Prisma anterior está **arquivado** em
`legacy-node/` (rota de rollback — ver README de lá; não é código vivo).

## Governança (.ai)

Este projeto segue a padronização da pasta `c:\Users\patrick.rosa\Desktop\.ai`:
- **Skills obrigatórias** (`.ai/skills/obrigatorias/`): 01-segurança, 02-DRY/SOLID,
  03-layout, 04-cores, 05-protocolo, 06-controle de mudanças.
- **Planejamento e ADRs**: `.ai/planejamento/` (visão geral, arquitetura, fases,
  estrutura de pastas, status) e `.ai/planejamento/adrs/` (decisões 01–07).
- Mudança de schema, dependência nova ou decisão arquitetural → sinalizar antes,
  registrar ADR quando estrutural (skill 06).

## Fluxo de desenvolvimento

```bash
npm run db:up      # MySQL 8 via Docker (container avasec-mysql; user/senha/db = avasec)
npm run api        # Laravel em http://127.0.0.1:8000
npm run dev        # Vite em http://localhost:5173 (proxy /api e /uploads -> Laravel)
```

Logins demo (teclado numérico): Admin Superior **9999**, Gestor de Conteúdos
**5678**, alunos **1234**.

## Gates de qualidade (todos precisam passar antes de commit)

```bash
npm run test:api                                   # 90 testes PHPUnit (exigem o MySQL de dev)
npm test                                           # testes de frontend (Vitest + RTL)
npm run lint                                       # tsc --noEmit
cd backend-laravel && ./vendor/bin/pint --test     # PSR-12
cd backend-laravel && ./vendor/bin/phpstan analyse --memory-limit=1G   # nível 9, 0 erros
```

Cobertura do backend (opcional, Xdebug sob demanda): `npm run test:api:coverage`.

## Invariantes críticos (não regredir)

- **Senhas**: `password_verify`/`password_hash` NATIVOS (bcrypt custo 10). NUNCA
  trocar para o Hash facade — ele rejeita os hashes `$2a$` legados do bcryptjs
  (ADR 05). `JWT_SECRET` precisa de ≥32 chars.
- **Schema**: pertence às migrations do Laravel (baseline por introspection,
  ADR 06). Prisma está extinto; nunca editar tabela sem migration.
- **Feature flags**: `backend-laravel/config/features.php` é réplica manual de
  `src/config/features.ts` — o teste `FeatureFlagParityTest` falha se divergirem.
  Flag desligada = rota 404, não botão escondido.
- **Fronteira HTTP tipada**: controllers usam os helpers do trait
  `ApiRequestHelpers` (`stringField`/`optionalString`/`stringList`/`requester`)
  para narrowing do payload validado. Nunca repassar `array<string, mixed>` cru
  a um service com shape, nem acessar `auth_user` direto dos attributes.
- **Camadas**: rotas → controllers finos → services (toda regra de negócio) →
  Eloquent. Erros sempre `{error, code, message}`. Identidade autenticada nunca
  vem do corpo da request.
- **Regras numéricas**: frequência mínima usa `??` (0% explícito é respeitado);
  penalidade de cancelamento 5/30 dias atrás de flag. Fonte:
  `config/constants.php` ↔ `src/config/constants.ts`.

## Documentos de referência

- `MIGRACAO_LARAVEL.md` — histórico completo da migração Node→Laravel.
- `DEPLOY_LARAVEL.md` — deploy de produção (Nginx + PHP-FPM + systemd).
- `TECNOLOGIAS.md` — stack detalhada.
