# AVASEC — Relatório de Hardening de Prontidão para Produção

Executado em 2026-07-22, em 10 etapas ordenadas, cada uma com migration reversível,
testes e commit próprio (`hardening(N/10)` no histórico do git). Nenhuma funcionalidade
nova foi criada e o frontend não foi redesenhado — apenas endurecido.

**Resultado dos testes: 34/34 passando** (`npm test`) — 18 da suite de segurança original
(atualizada) + 16 novos em `tests/hardening.test.ts`. `tsc --noEmit` limpo e build de
produção OK após cada etapa.

---

## Etapa 1 — FKs reais (userId / instructorId / enrollmentId)

**Estratégia**: expand-and-contract sem big bang. Colunas FK **anuláveis** adicionadas ao lado
dos campos `*Name` (que foram preservados para exibição); backfill por nome+papel na própria
migration; validação de **0 órfãos em 13 verificações** antes das FKs; `ON DELETE SET NULL`
(registro histórico nunca é apagado em cascata).

- Migration: `prisma/migrations/20260721190000_real_foreign_keys_expand/` (+`down.sql`).
- Tabelas: Course(instructorId), StudentEnrollment(id/userId), StudentProgress(userId,
  enrollmentId), Certificate(userId, enrollmentId), QuizSubmission, ExerciseSubmission,
  AcademicRequest, AdmissionRequest, ChatMessage(senderUserId), DirectMessage(studentUserId,
  senderUserId), ForumMessage(senderUserId).
- **Dual-write**: todo INSERT novo grava a FK (`src/server/utils/identity.ts`).
- **Autorização FK-first**: linhas com FK são comparadas por `userId === token.sub`; o
  fallback por nome só vale para linhas legadas com FK nula (`ownRowsWhere`/`ownsRow`) —
  homônimos não vazam dados entre si (testado).
- Seed associa FKs automaticamente (`linkForeignKeys()` em `prisma/seedData.ts`).

**Pendência planejada (contract)**: tornar as FKs NOT NULL e remover os campos `*Name` da
autorização é uma migração futura, após período de convivência — decisão consciente para
manter rollback trivial.

## Etapa 2 — Dados Gerenciais só via endpoints auditados

- Novo `src/utils/managementExport.ts`: os botões "Baixar Todas as 5 Bases" e "Exportar esta
  Base" do AdminDashboard buscam os dados **exclusivamente** em `GET /api/export/:dataset`
  (admin-only, rate limit 10/h, auditoria de quem/quando/qual base) e convertem em CSV.
- CSVs agora usam os **ids reais** (userId/enrollmentId) da etapa 1, sem datas fabricadas.
- As tabelas de pré-visualização na tela continuam locais (somente exibição).
- `exportService` ganhou `areaTematica`/`minAttendance`/`instructorId` no dataset `courses`.

## Etapa 3 — Uploads corrigidos

- **Autenticação**: as 2 chamadas de upload do InstructorDashboard usavam `fetch` sem token
  (falhavam com 401) → `authFetch`.
- **Simulador substituído**: o "Simular Upload de PDF/DOC" do aluno virou um input de arquivo
  real com envio ao servidor.
- **Público vs privado**: `uploads/public` (materiais, servidos estaticamente) vs
  `uploads/private` (entregas — NUNCA servidos estaticamente). Nova tabela `StoredFile`
  (migration `20260721191500_stored_files` + down.sql) registra dono/visibilidade/nome.
- **Download autorizado**: `GET /api/files/:id` — dono, instrutor ou admin; outros alunos 403;
  anônimo 401; `Content-Disposition: attachment` (arquivo de terceiro nunca renderiza no
  contexto da aplicação). Links `<a href>` de anexos viraram download via `authFetch`+blob
  (`src/utils/fileDownload.ts`), pois `<a>` não envia credencial de header.

## Etapa 4 — Feature flags ponta a ponta

- Novo middleware `requireFeature` (`src/server/middlewares/featureGate.ts`): cada domínio da
  API é montado atrás da sua flag em `src/server/app.ts` — flag OFF = **404 FEATURE_DISABLED
  na rota**, não botão escondido. Fonte única: `src/config/features.ts` (compartilhado).
- Frontend: fetch inicial pula rotas de flags OFF; renders sem gate corrigidos (CourseForum ×2,
  LiveClassroom ×2, superfícies de mensagens diretas ×3, aba Eventos & Webinars).
- Mapa flag→rota: forum→/api/forum; atividadesPraticasAvancadas→/api/exercises+submissions;
  uploadArquivos→/api/upload+/api/files; mensagensDiretas→/api/dms; liveClassroom→/api/chat;
  eventosWebinars→/api/webinars; solicitacoesAcademicas→/api/academic-requests;
  matricula→/api/enrollments+admissions; quizSimples, progresso, certificados,
  catalogoCursos, materiaisComplementares, dadosGerenciais → rotas correspondentes.
- **Mudança de comportamento intencional**: DMs, chat ao vivo e fórum estavam parcialmente
  visíveis apesar das flags OFF — agora ficam de fato indisponíveis até a flag ligar.
- Flags sem funcionalidade implementada (gamificacao, internacionalizacao, historicoAvancado,
  acompanhamentoParticipacao, trilhasAvancadas, dossieAcademico) permanecem documentais.

## Etapa 5 — Sem access token para blocked/pending_confirmation

- Login com senha **correta** de conta não-ativa → **403 institucional**
  (`ACCOUNT_BLOCKED`/`ACCOUNT_PENDING_CONFIRMATION`), **sem token**. Credencial inválida segue
  com mensagem genérica (nada vaza).
- Registro público (pending) não recebe token; provisionamento por admin não entrega a
  credencial da conta de terceiro. Frontend mostra mensagem institucional em vez de auto-login.
- Defesa em profundidade mantida: token emitido **antes** de um bloqueio continua barrado em
  toda rota interna pelo `requireActiveAccount` (reconsulta o status a cada request; testado).
- Corrigido bug pré-existente: o frontend lia `data.error` (boolean) como mensagem de erro.

## Etapa 6 — Fonte única do percentual mínimo (fim do 70/75)

- `src/config/constants.ts`: `DEFAULT_MIN_ATTENDANCE = 70` + `courseMinAttendance()` (usa `??`,
  então curso explicitamente configurado com 0% é respeitado).
- 10 ocorrências unificadas — incluindo o `|| 75` do CSV do AdminDashboard e um `>= 70`
  hardcoded no InstructorDashboard. Backend e frontend decidem pelo MESMO número.
- Os `>= 70` de **nota de quiz** são outro conceito e foram mantidos.

## Etapa 7 — Penalidade de rematrícula: flag + servidor

- Novos endpoints do próprio aluno: `POST /api/enrollments/self/{enroll,drop,complete}`
  (identidade do token; rate limit no enroll).
  - **drop**: o servidor conta os dias reais desde `enrolledAt` persistido; restrição de 30
    dias só com `features.penalidadesCancelamento` ligada. O "simulador de dias" do frontend
    foi removido — o cliente não decide mais a regra.
  - **enroll**: recusa penalidade ativa (flag-aware) e matrícula dupla (409).
  - **complete**: exige o critério de frequência do curso (server-side).
- Corrige bug real: o cancelamento self do aluno falhava com 403 na rota PUT de gestão
  (restrita a instrutor/admin).
- Constantes da regra em `constants.ts` (5 dias de tolerância / 30 dias de restrição).
- UI de penalidade (cards/textos) gated pela flag.

## Etapa 8 — Auditoria ≠ telemetria

- Nova tabela `ClientEvent` (migration `20260721193000_client_event_telemetry` + down.sql) e
  rota `/api/telemetry`: eventos de UI do frontend (navegação, narração) saem da tabela de
  auditoria.
- **`POST /api/security-logs` foi extinto** — a trilha de auditoria (SecurityLog) agora é
  escrita exclusivamente pelo servidor (`auditService.logAudit`) a partir das ações reais;
  leitura/limpeza admin-only. Telemetria grava a identidade do token (nunca a alegada no
  corpo) e tem leitura admin paginada.
- Dados antigos de SecurityLog foram preservados (nenhuma remoção).

## Etapa 9 — JWT em cookie HttpOnly

- **Auditoria**: o token vivia em `localStorage` (legível por qualquer script; XSS = roubo de
  sessão).
- Agora: login seta cookie `ava_session` (**HttpOnly, SameSite=Lax, Secure em produção,
  12h** — alinhado à expiração do JWT); `POST /api/auth/logout` limpa; o frontend **não
  persiste mais o token** (localStorage guarda apenas o perfil público para exibição, validado
  no load via `GET /api/auth/me`).
- Middleware aceita cookie OU header `Authorization` (clientes de API/testes); o fallback de
  header para tokens legados no navegador é transitório.
- CSRF: mitigado por SameSite=Lax + CORS restrito por env + corpo JSON. Um token anti-CSRF
  dedicado fica como evolução futura se surgirem formulários cross-site.

## Etapa 10 — Testes ampliados

`npm test` → **34/34**:
- Suite original (18, atualizada): status de conta, RBAC, ownership, export admin-only,
  matrícula duplicada, certificado por critério, validação, erros padronizados.
- Novos (16): cookie HttpOnly (emissão/uso/logout); flags no backend; upload público vs
  privado (dono/intruso/instrutor/anônimo; estático não serve privado); **homônimos não vazam
  dados** (prova da etapa 1); certificado com userId/enrollmentId + idempotência; datasets de
  exportação; telemetria separada (rota de escrita na auditoria extinta; identidade do token
  vence a alegada); matrícula self (409; flag OFF sem penalidade; conclusão sem frequência 403).

---

## Rollback

Cada etapa é um commit isolado (`git revert <hash>`). Migrations são aditivas e cada uma tem
`down.sql` documentado no próprio diretório (`prisma/migrations/*/down.sql`) — aplicar o SQL e
remover a linha correspondente de `_prisma_migrations`. Nenhuma migration remove coluna ou
linha pré-existente; os campos `*Name` originais estão intactos.

| Migration | Conteúdo | Down |
|---|---|---|
| `20260721190000_real_foreign_keys_expand` | colunas FK + backfill | drop FKs+colunas |
| `20260721191500_stored_files` | tabela StoredFile | drop tabela |
| `20260721193000_client_event_telemetry` | tabela ClientEvent | drop tabela |

## Riscos e pontos de atenção

1. **Fase de transição de identidade**: linhas legadas sem FK ainda caem no fallback por nome.
   O backfill zerou os órfãos no ambiente atual; em produção, rode as queries de validação
   (contagem de `userId IS NULL`) após o deploy e antes de qualquer migração "contract".
2. **Comportamento alterado por flags**: DMs/chat/fórum ficaram genuinamente indisponíveis com
   as flags OFF (antes vazavam na UI). Se a escola esperava o chat de tutoria ativo, ligue
   `mensagensDiretas`/`liveClassroom` em `features.ts`.
3. **Token no corpo do login**: mantido para clientes de API não-navegador; o navegador usa
   exclusivamente o cookie. Se a API nunca for consumida fora do navegador, remover o campo é
   um follow-up simples.
4. **Rate limits em memória**: por instância de processo. Com PM2 em cluster ou múltiplos nós,
   migrar para um store compartilhado (Redis) antes de escalar horizontalmente.
5. **Storage local de uploads**: `uploads/` no disco do servidor — incluir no backup e, se o
   volume crescer, migrar para storage com URL assinada (S3/compatível).
6. **Instrutor/admin podem baixar qualquer entrega privada**: consistente com o fluxo de
   correção atual; escopo fino por curso do instrutor é evolução futura.
7. **Flags documentais** (gamificacao etc.) continuam sem código — decidir entre implementar
   ou remover do arquivo.

## Arquivos alterados (resumo)

- **Novos (backend)**: `middlewares/featureGate.ts`, `utils/identity.ts`,
  rotas self de matrícula, `fileRouter` em `upload.ts`, telemetria em auditRoutes/Controller/
  auditLogService, cookie em `middlewares/auth.ts`+`authController`.
- **Novos (frontend/compartilhados)**: `src/config/constants.ts`, `src/utils/managementExport.ts`,
  `src/utils/fileDownload.ts`.
- **Novos (infra/testes)**: 3 migrations com down.sql, `tests/hardening.test.ts`.
- **Alterados**: schema.prisma, seedData.ts, todos os services de domínio (dual-write/FK-first),
  app.ts, enrollmentRoutes/Controller/Service, authService/Controller/Routes, upload.ts,
  exportService, LMSContext.tsx, App.tsx, StudentDashboard.tsx, InstructorDashboard.tsx,
  AdminDashboard.tsx, tests/security.test.ts, package.json (cookie-parser).
