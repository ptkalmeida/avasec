# AVASEC — Documentação Técnica e Funcional (estado atual do código)

> Documento gerado por inspeção direta do código-fonte em 2026-07-21. Nenhuma linha de código
> foi alterada para produzir este documento. Onde uma funcionalidade está desativada, simulada
> ou apresenta alguma inconsistência entre frontend e backend, isso é dito explicitamente.

---

## 1. O que é a plataforma e qual seu objetivo

AVASEC ("Escola da Cultura") é um **Ambiente Virtual de Aprendizagem (AVA)** institucional
voltado à formação e qualificação profissional em Cultura e Economia Criativa, oferecendo
**Cursos Livres** por meio de uma plataforma web (React no frontend, Node/Express + MySQL no
backend). Permite catálogo público de cursos, matrícula, consumo de aulas gravadas/ao vivo,
acompanhamento de progresso, emissão de certificado digital e gestão administrativa da escola.

## 2. Perfis existentes

Três papéis (`Role` no banco): **`student`** (aluno), **`instructor`** (instrutor/"Gestor de
Conteúdos") e **`admin`** (administrador/coordenação). O papel é definido no cadastro do usuário
(`User.role` no Prisma) e verificado em toda rota interna via JWT.

## 3. O que cada perfil pode acessar e fazer

### Aluno
- Navega pelo catálogo público, se matricula em cursos, assiste aulas gravadas/ao vivo.
- Marca aulas como concluídas e presença em sessões ao vivo (progresso).
- Emite certificado automaticamente ao atingir o percentual mínimo de frequência do curso.
- Acessa apenas os **próprios** dados: progresso, matrícula, certificados, submissões — o
  backend impede (403) tentativa de acessar dados de outro aluno.
- Envia solicitações acadêmicas (certificado, histórico, matrícula, outro) e mensagens diretas.

### Instrutor
- Cria/edita/exclui **apenas os cursos onde é o `instructorName`** (ownership verificado no
  backend, não só escondido no frontend).
- Lista **apenas os alunos matriculados/admitidos em seus próprios cursos** — não a base
  inteira de alunos da escola (`authService.listStudentsForInstructor`).
- Adiciona aulas, sessões ao vivo, documentos de aula, quizzes, exercícios práticos.
- Aprova/rejeita solicitações acadêmicas e admissões dos cursos que leciona; corrige entregas.
- **Não** acessa a exportação de Dados Gerenciais (403).

### Administrador
- Acesso irrestrito: qualquer curso, qualquer aluno/instrutor, configurações do sistema.
- Único perfil que pode: alterar `status` de conta de um usuário (`active`/`blocked`), excluir
  usuários, criar contas de instrutor/admin, exportar Dados Gerenciais, ver a trilha de
  auditoria completa (`/api/security-logs`), resetar o banco em ambiente de desenvolvimento.

## 4. Fluxo completo do aluno

1. **Catálogo** — página institucional pública (`GET /api/courses`, sem login) lista os cursos.
2. **Matrícula** — aluno logado envia uma solicitação de admissão (`POST /api/admissions`);
   matrícula duplicada pendente para o mesmo curso é bloqueada (`409 CONFLICT`). Ao ser aprovada
   por instrutor/admin, o backend já efetiva a matrícula (`StudentEnrollment`) na mesma
   transação da aprovação.
3. **Aulas** — dentro do curso, assiste aulas gravadas (`videoUrl`) ou entra em sessões ao vivo
   (`LiveSession`/`LiveClassroom`), acessa documentos complementares e responde quizzes.
4. **Progresso** — cada aula concluída/presença em live é registrada em `StudentProgress`,
   escopada por `studentName`+`courseId` (não há mais o bug de progresso global cruzando entre
   alunos que existia antes da migração para banco real).
5. **Certificado** — emitido quando a frequência calculada (aulas concluídas + lives assistidas
   ÷ total de atividades) atinge o `minAttendance` do curso (ou 70% padrão). **O cálculo e a
   decisão de emitir são feitos no servidor**, não no cliente — mesmo que o frontend calcule e
   exiba um valor, o backend recalcula do zero e rejeita (`403`) se o critério real não for
   atingido. Certificado é idempotente (não duplica por curso+aluno).
6. **Cancelamento/histórico** — aluno pode se desmatricular; se ficar mais de 5 dias
   matriculado antes de cancelar, recebe uma penalidade de 30 dias sem poder se rematricular
   (`dropOutPenaltyUntil`), visível no dashboard do aluno. Solicitações de histórico/documentos
   ficam registradas em `AcademicRequest` até aprovação.

## 5. Fluxo do instrutor

Login → painel do instrutor lista os cursos que leciona → gerencia aulas/sessões ao
vivo/documentos/quizzes/exercícios de cada curso → acompanha alunos matriculados nos próprios
cursos → aprova/rejeita solicitações de matrícula e acadêmicas relativas aos seus cursos →
corrige entregas de exercícios com nota/feedback → conduz aulas ao vivo (chat da sessão).

## 6. Fluxo do administrador

Login → painel administrativo com abas condicionadas a feature flags (cursos/trilhas,
documentos, exercícios práticos, Dados Gerenciais, configurações) → gerencia professores e
alunos (criação, exclusão, bloqueio de conta) → aprova solicitações → configura parâmetros do
sistema (mensagens diretas, chat global, matrícula aberta, auto-certificação, gravação de
aulas) → exporta bases de Dados Gerenciais → consulta logs de auditoria.

## 7. Funcionalidades ativas no MVP (`features.ts`)

`catalogoCursos`, `detalhesCurso`, `matricula`, `modulosAulas`, `materiaisComplementares`,
`quizSimples`, `progresso`, `certificados`, `dadosGerenciais`, `perfilBasico`, e
**`uploadArquivos`** (reativado — ver observação crítica na seção 16).

## 8. Funcionalidades desativadas por feature flags

Marcadas `false` em `src/config/features.ts`, preservadas no código: `forum`,
`atividadesPraticasAvancadas`, `graficosAvancados`, `acompanhamentoParticipacao`,
`historicoAvancado`, `internacionalizacao`, `gamificacao`, `trilhasAvancadas`,
`solicitacoesAcademicas`, `bibliotecaDigital`, `eventosWebinars`, `liveClassroom`,
`dossieAcademico`, `penalidadesCancelamento`, `mensagensDiretas`.

**Ressalva verificada no código** (importante para não gerar expectativa errada): nem toda
flag `false` esconde 100% do recurso correspondente:
- `LiveClassroom` e `CourseForum` (componentes) são **importados e renderizados diretamente**
  em `StudentDashboard.tsx`/`InstructorDashboard.tsx`, sem checagem de
  `features.liveClassroom`/`features.forum` nesses pontos específicos de renderização —
  embora a aba de navegação "Mensagens" (fórum) só apareça se `features.forum` for `true`.
- A lógica de penalidade de cancelamento (`dropOutPenaltyUntil`, regra dos 5 dias) **roda
  incondicionalmente** no `LMSContext.tsx`/`StudentDashboard.tsx`; a flag
  `penalidadesCancelamento` não é referenciada em nenhum componente.
- `bibliotecaDigital`, `eventosWebinars`, `dossieAcademico`, `gamificacao`,
  `internacionalizacao`, `historicoAvancado`, `acompanhamentoParticipacao`, `trilhasAvancadas`
  **não têm nenhuma referência** (`features.<flag>`) em nenhum componente — ou seja, essas
  flags hoje são apenas placeholders documentais, sem código condicionado a elas.

## 9. Regras de acesso por status da conta

`User.status` (enum): **`active`** (acesso liberado), **`blocked`** (suspenso pela
coordenação), **`pending_confirmation`** (cadastro público aguardando homologação).

- Middleware `requireActiveAccount` reconsulta o status no banco a cada requisição (não confia
  em token antigo) e bloqueia `blocked`/`pending_confirmation` com **403** e mensagem
  institucional (`ACCOUNT_BLOCKED` / `ACCOUNT_PENDING_CONFIRMATION`).
- **Login continua funcionando** mesmo com conta bloqueada/pendente (emite token), mas qualquer
  rota interna protegida recusa o acesso — isso permite ao frontend mostrar uma tela
  "aguardando confirmação"/"conta suspensa" em vez de um erro de senha genérico.
- Regra de provisionamento: cadastro público (sem admin logado) sempre nasce **`student`** +
  **`pending_confirmation`**; cadastro feito por um admin autenticado nasce **`active`** com o
  papel solicitado. Cadastro público tentando se auto-atribuir `instructor`/`admin` é rejeitado
  (403) — apenas um admin pode criar essas contas.

## 10. Regras de matrícula e restrição temporária

- Matrícula pendente duplicada para o mesmo curso é bloqueada (`409`).
- Aprovação de admissão matricula o aluno na mesma transação (evita solicitação "aprovada" sem
  matrícula efetiva).
- Cancelamento após >5 dias matriculado gera penalidade de **30 dias** sem poder se
  rematricular (`dropOutPenaltyUntil` = data atual + 1 mês), verificado no
  `StudentDashboard` antes de permitir nova matrícula.
- **Enrollment "matricula" aberta/fechada** é controlado por `systemSettings.openEnrollment`
  (config administrativa), consultado pelo AdminDashboard.

## 11. Regras de certificado

- Percentual mínimo configurável por curso: `Course.minAttendance` (campo opcional editável
  pelo instrutor/admin no cadastro do curso).
- **Padrão quando não configurado: 70%** — aplicado literalmente em
  `certificateService.issueCertificate` (`course.minAttendance ?? 70`).
  > Nota de precisão: a tela de exportação de Dados Gerenciais no AdminDashboard usa um
  > fallback cosmético diferente (`c.minAttendance || 75`) só para exibição/CSV — **não afeta**
  > a regra real de emissão, que está no backend com 70%.
- Frequência = (aulas concluídas + presenças em live) ÷ (total de aulas + total de lives) do
  curso, recalculada **inteiramente no servidor** a partir do progresso real salvo no banco.
- Emissão é **idempotente**: existe uma constraint única (`studentName`+`courseId`) — tentar
  emitir de novo para o mesmo aluno/curso apenas retorna o certificado já existente.
- Aluno só emite certificado em nome de si mesmo (403 caso contrário).

## 12. Área "Dados Gerenciais" — duas implementações distintas (importante)

Existem **dois mecanismos de exportação independentes** no código atual, e eles **não estão
conectados um ao outro**:

1. **Backend real** (`GET /api/export/:dataset`, criado na revisão de segurança): restrito a
   `admin`, com rate limit (10/hora) e auditoria (quem exportou, quando, qual base). Bases
   aceitas: `students`, `courses`, `enrollments`, `progress`, `certificates` — nunca retorna
   hash de senha. Hoje **não é chamado por nenhuma tela do frontend**.
2. **Frontend existente** (aba "Dados Gerenciais" do `AdminDashboard.tsx`): botão "Baixar
   Todas as 5 Bases (.csv)" que **monta os CSVs inteiramente no navegador**, a partir dos dados
   já carregados em memória no contexto React (`studentsList`, `courses`, `studentEnrollments`,
   `progress`, `certificates`) — gera `base_alunos.csv`, `base_cursos.csv`,
   `base_matriculas.csv`, `base_progresso_modulo.csv`, `base_certificados.csv` via
   `Blob`/`URL.createObjectURL`, sem chamar o endpoint acima e sem gerar um registro de
   auditoria dedicado ao ato de exportar.

Ou seja: a **funcionalidade visível para o admin** (o botão de exportar) já existe e funciona,
mas roda de forma client-side e não passa pelo endpoint seguro/auditado que foi construído.

## 13. Segurança implementada no backend

- **Autenticação**: JWT (12h de expiração) + bcrypt para hash de senha. Nunca senha em texto
  puro. Mensagens de erro de login genéricas ("Usuário ou senha inválidos.").
- **Rate limit** (`express-rate-limit`, todos com handler padronizado):
  login 10/15min, cadastro 10/hora, troca de senha 5/15min, solicitações 20/hora, matrícula
  20/15min, exportação 10/hora, verificação de certificado 30/15min, upload 30/15min, limite
  global de 300/min por IP em toda a API.
- **Bloqueio de conta por força-bruta**: após 5 falhas de login seguidas, a conta fica travada
  por 15 minutos (`429 ACCOUNT_LOCKED`), independente do IP — camada extra ao rate limit por IP.
- **Permissões (RBAC)**: `requireRole`, `requireSelfStudent` e checagens de ownership de curso
  aplicadas nas rotas, nunca só escondidas no frontend.
- **Validação de entrada**: Zod em praticamente todas as rotas (e-mail, senha, IDs, status,
  datas, percentuais, textos de justificativa, dados de curso/matrícula/quiz/exercício/chat).
- **CORS**: restrito às origens definidas em `CORS_ORIGIN` (env) — nunca `*` com credenciais.
- **Headers de segurança**: `helmet()` aplicado globalmente.
- **Logs de auditoria server-side** (`SecurityLog`, tabela própria): login, falha de login,
  alteração de status de conta, matrícula/cancelamento, emissão de certificado, aprovação de
  solicitação, alteração de curso, exclusão de curso/certificado, exportação de Dados
  Gerenciais (endpoint novo), correção de exercício, alteração de configurações — sempre
  gravados no servidor a partir da identidade do token, nunca confiando em dado enviado pelo
  cliente. Existe também um endpoint `POST /api/security-logs` que aceita eventos de UI de
  baixo risco vindos do cliente (navegação, narração por voz) — aceita anônimo, mas grava
  autor/IP reais do lado do servidor, não o que o cliente alega ser.
- **Erros padronizados**: toda resposta de erro segue `{ error: true, code, message }`; stack
  trace e detalhes internos nunca são expostos ao cliente (`errorHandler` central).
- **Upload de arquivo** (`src/server/upload.ts`): exige login+conta ativa; valida extensão E
  o conteúdo real do arquivo por assinatura binária (magic bytes, via `file-type`) — um `.pdf`
  falso ou um `.exe` renomeado são rejeitados; nome do arquivo salvo é gerado pelo servidor
  (nunca reaproveita o nome enviado); tamanho máximo configurável (`UPLOAD_MAX_SIZE_MB`).
  `.doc` legado não é aceito (só `.docx`), por não ter assinatura binária confiável de checar.

## 14. Performance

- **Paginação** (`page`/`pageSize`, máx. 100 itens) nas listagens de usuários, certificados e
  logs de auditoria — as demais listas (progresso, admissões, quizzes etc.) não são paginadas
  por serem, na escala atual da escola, pequenas.
- **Índices de banco** adicionados para os campos usados em filtro/relacionamento: papel+status
  do usuário, autor do curso, aluno/curso em progresso/certificado/matrícula/quiz/exercício,
  ator e ação nos logs de auditoria.
- **Sem N+1**: cursos usam `include` (uma única consulta com join) em vez de buscar aulas uma a
  uma.
- **`select` explícito** nas consultas de listagem/exportação de usuários — nunca retorna
  `passwordHash`.
- **Transactions** em: criação/atualização de curso (aulas+documentos+sessões sincronizados
  atomicamente), aprovação de admissão (+matrícula), reset de banco (dev).

## 15. Estrutura técnica do projeto

```
avasec-main/
├── server.ts                  # bootstrap: monta o app, Vite (dev) ou estáticos (prod), sobe a porta
├── src/
│   ├── App.tsx                 # shell da aplicação (landing pública + navegação por perfil)
│   ├── context/LMSContext.tsx  # estado global do frontend + chamadas à API (authFetch)
│   ├── components/              # AdminDashboard, InstructorDashboard, StudentDashboard,
│   │                            # ProfileView, LiveClassroom, CourseForum, CertificateTemplate
│   ├── config/features.ts      # feature flags
│   ├── data/mockData.ts        # dados iniciais (fonte do seed do banco)
│   ├── types.ts                # tipos compartilhados frontend/backend
│   └── server/                 # BACKEND (Express)
│       ├── app.ts               # monta o Express app (rotas + segurança), testável sem porta
│       ├── prisma.ts             # cliente Prisma singleton
│       ├── config/               # env.ts (valida .env), cors.ts
│       ├── middlewares/          # auth, accountStatus, rbac, validate, rateLimiters, errorHandler
│       ├── validators/           # schemas Zod por domínio
│       ├── controllers/          # parse de request + chamada ao service + resposta
│       ├── services/             # regra de negócio + acesso ao Prisma
│       ├── routes/               # roteadores Express por domínio
│       └── upload.ts             # endpoint de upload de arquivo
├── prisma/
│   ├── schema.prisma            # modelo de dados MySQL
│   ├── migrations/               # histórico de migrations
│   ├── seed.ts / seedData.ts     # popula o banco com dados de demonstração
├── tests/security.test.ts       # suite automatizada (vitest + supertest)
├── docker-compose.yml           # MySQL 8 local para desenvolvimento
└── deploy/                      # Nginx, PM2 (ver DEPLOY.md)
```

## 16. O que ainda é simulado, mockado ou depende de integração futura

- **Upload de anexo em entrega de exercício (aluno)** — em `StudentDashboard.tsx`, o botão de
  anexar arquivo é um **simulador puro**: dispara um `setTimeout` de 1s e atribui um nome de
  arquivo fixo (`trabalho_pratico_*.pdf`) com `url: '#'`. **Não envia nenhum arquivo real ao
  servidor**, apesar do backend já ter um endpoint de upload funcional.
- **Upload de documento de aula / item de biblioteca (instrutor)** — em
  `InstructorDashboard.tsx`, este SIM chama o endpoint real (`fetch('/api/upload', ...)`), mas
  usa `fetch` puro em vez do wrapper `authFetch` que anexa o token — como o endpoint agora
  exige autenticação (`requireAuth`), **essa chamada hoje falha com 401** no fluxo real
  logado (funcionaria apenas se o upload virasse público, o que não é recomendado). Ver ponto
  de atenção correspondente na seção 17.
- **Exportação de Dados Gerenciais** — como descrito na seção 12, o botão do AdminDashboard
  gera os CSVs a partir de dados já em memória no navegador, não do endpoint seguro/auditado.
- **Vídeo das aulas** — URLs externas validadas no backend (ADR 08): YouTube (canonicalizado
  para `watch?v=`) ou arquivo de vídeo direto (mp4/webm/ogg/m4v/mov). Player único em
  `src/components/shared/VideoPlayer.tsx`; sem integração com serviço de streaming dedicado.
- **Certificado (PDF)** — `CertificateTemplate.tsx` existe para exibir/imprimir o certificado
  na tela; não há geração de PDF assinado digitalmente nem envio por e-mail.
- **Confirmação de cadastro público** — não existe fluxo de e-mail de confirmação; uma conta
  `pending_confirmation` só vira `active` por ação manual de um admin.
- **Presença online/offline de instrutor, notas de aula do aluno, avatar/status de perfil** —
  continuam armazenados só em `localStorage` do navegador (não sincronizam entre dispositivos
  nem persistem no banco).
- **Reset de dados** (`POST /api/dev/reset`) — utilitário de desenvolvimento, indisponível
  quando `NODE_ENV=production`.

## 17. Pontos de atenção e próximos passos recomendados

1. **Corrigir os dois `fetch()` sem token em `InstructorDashboard.tsx`** (linhas do envio de
   documento de aula e de item de biblioteca) para usarem o wrapper autenticado — hoje eles
   quebram silenciosamente contra o backend protegido.
2. **Decidir se a exportação de Dados Gerenciais do AdminDashboard deve passar a chamar
   `GET /api/export/:dataset`** em vez de montar os CSVs no navegador — unificaria a exportação
   sob rate limit + auditoria reais.
3. **Wiring do upload real de entrega de exercício** do aluno, substituindo o simulador por uma
   chamada real ao endpoint de upload.
4. **Identidade por nome, não por ID de usuário**: progresso, matrícula, certificados e
   mensagens ainda são chaveados por `studentName` (string) em vez de `userId` — funcional e
   protegido por checagens de posse, mas uma migração para chave estrangeira real é mais robusta
   a médio prazo (evita colisão de nomes).
5. **Storage de upload em pasta pública estática** (`/uploads`) — adequado para materiais de
   curso, mas não para dados sensíveis de longo prazo; migrar para storage privado com URL
   assinada é a evolução recomendada.
6. **Fluxo de confirmação de cadastro por e-mail** ainda não existe — hoje depende de ação
   manual do admin para ativar contas públicas.
7. **Flags "mortas"** (`bibliotecaDigital`, `eventosWebinars`, `dossieAcademico`, `gamificacao`,
   `internacionalizacao`, `historicoAvancado`, `acompanhamentoParticipacao`,
   `trilhasAvancadas`, `penalidadesCancelamento`) não têm nenhum código condicionado a elas —
   vale decidir se serão implementadas, removidas do arquivo de flags, ou documentadas como
   "reservadas para o roadmap".
8. **Bundle do frontend** cresceu para ~365KB gzip — não é um risco de segurança, mas é uma
   oportunidade de `code-splitting`/`manualChunks` numa próxima iteração.
