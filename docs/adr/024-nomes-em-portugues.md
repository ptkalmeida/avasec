# ADR 024 — Tabelas, colunas e API em português, em snake_case

- **Data:** 26/10/2026
- **Situação:** aceita — decisão do responsável, aplicada antes de existir HML ou PRD
- **Afeta:** ADR 012 (nada é apagado), ADR 022 (sessão revogável), norma TI-SECEC C.20 (nomenclatura de banco)

## Contexto

O banco nasceu com nomes em inglês e em `PascalCase`/`camelCase` (`Course`, `StudentEnrollment`,
`instructorName`), herdados do Prisma. O resto do projeto — telas, textos, regras, testes — é em
português, e o banco era a única camada que obrigava a traduzir de cabeça (`Quiz` é a avaliação,
`StudentEnrollment` é a matrícula).

A norma TI-SECEC deixa a **nomenclatura definitiva de tabelas e campos em aberto** (Documento C, item
C.20). Não há, portanto, regra oficial que contrarie esta decisão. Ela vale como regra do projeto e
**cede à norma** se a SECEC fechar um padrão diferente — a divergência deve ser sinalizada lá, e a
migration desta decisão é reversível justamente para isso.

Não havia HML nem PRD no ar: renomear agora custa uma migration, e renomear depois custaria uma
migração de produção.

## Decisão

1. **Tabelas e colunas em português, em `snake_case`.** Tabela no singular (`curso`, `matricula`);
   chave estrangeira `<entidade>_id` (`curso_id`, `usuario_id`); data/hora `<fato>_em`
   (`inativado_em`, `criado_em`); data de calendário `<fato>_ate`/`data_<fato>`.
2. **Classes de model em português** (`Curso`, `Aula`, `Usuario`), `PascalCase`.
3. **A API acompanha o banco.** Os campos JSON saem das colunas (o model é serializado direto), então
   `title` virou `titulo`, `instructorName` virou `nome_instrutor`, e as relações viraram `aulas`,
   `sessoes_ao_vivo`, `documentos`, `questoes`. Os caminhos das rotas também estão em português
   (tabela abaixo). O responsável autorizou renomear a API junto; o único consumidor conhecido, o React, saiu na ADR 020.
4. **Regra daqui para frente (também no `AGENTS.md`):** tabela, coluna, model, índice e chave
   estrangeira novos nascem em português e `snake_case`. Índices usam `idx_<tabela>_<colunas>`,
   únicos `uq_<tabela>_<colunas>`, chaves estrangeiras `fk_<tabela>_<coluna>`.

## O que NÃO foi renomeado, e por quê

| Item | Motivo |
|---|---|
| Valores de `ENUM` e de campos de estado (`student`, `instructor`, `admin`, `pending`, `approved`, `active`, `blocked`, `SUCCESS`...) | São **dado**, não nomenclatura: papéis e estados viajam no JWT, em middleware e em milhares de comparações. Renomear exige migrar dados e invalida sessões. Ficam para decisão própria. |
| Chaves **internas** dos JSON armazenados: signatários (`name`, `role`), conteúdo das páginas do site (`items`, `eyebrow`, `imageUrl`, `tag`...), `signatarios[].name/role` | Estão dentro de uma coluna JSON e nas versões guardadas pelo histórico (ADR 021). Renomear pede conversão de dados e de snapshots. As chaves `title`, `description`, `image`, `date`, `time` e `type` do conteúdo do site passaram a `titulo`, `descricao`, `imagem`, `data`, `horario` e `tipo` porque a tabela de conteúdo estava vazia. |
| `migrations` (framework) e `_prisma_migrations` (histórico do Node) | Tabelas que não são do domínio. |
| Chaves de configuração do framework (`config/*.php`: `name`, `options`, `visibility`, `type`), alias de middleware (`role`, `jwt`, `active`, `feature`), nomes de flags em `config/features.php` | Não são banco nem API; renomear quebra o framework. |
| Nomes de services, controllers e rotas web (`CourseService`, `/admin/cursos`) | Fora do escopo desta decisão: tabela, coluna, model e API. |
| `id`, `slug`, `status`, `email`, `cpf`, `celular`, `cep`, `uf`, `endereco`, `municipio`, `modalidade`, `nivel`, `link`, `url`, `tipo`, `referencia`, `conteudo`, `origem`, `jti` | Já são palavras do português ou termos técnicos universais. |

## Consequências

- **Sessões abertas caem.** Os claims do JWT passaram de `name`/`role` para `nome`/`papel`; quem está
  logado precisa entrar de novo. Em DEV isso é irrelevante; antes de PRD a decisão já está tomada.
- **A migration é reversível** (`down()` aplica o mapa ao contrário), não apaga linha nem coluna, e
  preserva os tipos e as regras de `ON UPDATE`/`ON DELETE` das chaves estrangeiras. A ADR 012 segue
  valendo: `inativado_em`, `inativado_por` e `motivo_inativacao` são as antigas `inativadoEm`,
  `inativadoPor` e `motivoInativacao`.
- **Migrations antigas não foram reescritas.** Em um banco novo elas criam os nomes antigos e a
  migration `2026_10_26_200000_renomeia_tabelas_e_colunas_para_portugues` os renomeia no fim. Foi
  validado: schema do zero → migration → rollback → migration.
- **Colunas legadas em texto** ganharam o sufixo `_texto` para conviver com a coluna nativa ao lado
  (`enviado_em_texto` e `enviado_em`; `vencimento_contrato_texto` e `vigencia_ate`).
- **`Aula.ordem`** deixou de ser escondida e reexposta como `order`: a coluna se chama `ordem` e o
  contrato JSON também.

## Mapa: tabelas

| Antes | Depois | Model |
|---|---|---|
| `StoredFile` | `arquivo_armazenado` | `ArquivoArmazenado` |
| `Lesson` | `aula` | `Aula` |
| `Quiz` | `avaliacao` | `Avaliacao` |
| `Certificate` | `certificado` | `Certificado` |
| `SystemSettings` | `configuracao_sistema` | `ConfiguracaoSistema` |
| `SitePageContent` | `conteudo_pagina_site` | `ConteudoPaginaSite` |
| `Course` | `curso` | `Curso` |
| `LessonDocument` | `documento_aula` | `DocumentoAula` |
| `ExerciseSubmission` | `entrega_exercicio` | `EntregaExercicio` |
| `ClientEvent` | `evento_cliente` | `EventoCliente` |
| `WebinarEvent` | `evento_webinar` | `EventoWebinar` |
| `PracticalExercise` | `exercicio_pratico` | `ExercicioPratico` |
| `CourseSlugHistory` | `historico_slug_curso` | `HistoricoSlugCurso` |
| `LibraryItem` | `item_biblioteca` | `ItemBiblioteca` |
| `SecurityLog` | `log_seguranca` | `LogSeguranca` |
| `StudentEnrollment` | `matricula` | `Matricula` |
| `ChatMessage` | `mensagem_chat` | `MensagemChat` |
| `DirectMessage` | `mensagem_direta` | `MensagemDireta` |
| `ForumMessage` | `mensagem_forum` | `MensagemForum` |
| `DocumentTemplate` | `modelo_documento` | `ModeloDocumento` |
| `StudentProgress` | `progresso_aluno` | `ProgressoAluno` |
| `QuizQuestion` | `questao_avaliacao` | `QuestaoAvaliacao` |
| `LiveSession` | `sessao_ao_vivo` | `SessaoAoVivo` |
| `SessaoEncerrada` | `sessao_encerrada` | `SessaoEncerrada` |
| `AcademicRequest` | `solicitacao_academica` | `SolicitacaoAcademica` |
| `AdmissionRequest` | `solicitacao_ingresso` | `SolicitacaoIngresso` |
| `QuizSubmission` | `tentativa_avaliacao` | `TentativaAvaliacao` |
| `User` | `usuario` | `Usuario` |
| `VersaoDeConteudo` | `versao_de_conteudo` | `VersaoDeConteudo` |

## Mapa: colunas

Cada nome antigo virou sempre o mesmo nome novo, em qualquer tabela.

| Antes | Depois |
|---|---|
| `action` | `acao` |
| `allowRetry` | `permite_nova_tentativa` |
| `aposentadoEm` | `aposentado_em` |
| `areaInteresse` | `area_interesse` |
| `areaTematica` | `area_tematica` |
| `attendancePercent` | `percentual_frequencia` |
| `attendedLiveSessions` | `sessoes_ao_vivo_assistidas` |
| `canMultiEnroll` | `pode_multimatricula` |
| `cargaHoraria` | `carga_horaria` |
| `category` | `categoria` |
| `completedCourseIds` | `cursos_concluidos_ids` |
| `completedLessons` | `aulas_concluidas` |
| `content` | `conteudo` |
| `contractExpirationDate` | `vencimento_contrato_texto` |
| `correctOptionIndex` | `indice_opcao_correta` |
| `courseId` | `curso_id` |
| `courseTitle` | `titulo_curso` |
| `courseType` | `tipo_curso` |
| `coverImage` | `imagem_capa` |
| `createdAt` | `criado_em` |
| `criadoEm` | `criado_em` |
| `criadoPorUserId` | `criado_por_usuario_id` |
| `customHtml` | `html_personalizado` |
| `data` | `dados` |
| `dataCadastro` | `data_cadastro_texto` |
| `dataEvento` | `data_evento` |
| `date` | `data_texto` |
| `description` | `descricao` |
| `details` | `detalhes` |
| `device` | `dispositivo` |
| `dropOutPenaltyUntil` | `penalidade_desistencia_ate` |
| `dueDate` | `prazo_texto` |
| `duration` | `duracao` |
| `durationMinutes` | `duracao_minutos` |
| `emiteCertificado` | `emite_certificado` |
| `encerradaEm` | `encerrada_em` |
| `enrolledAt` | `matriculado_em_texto` |
| `enrolledCourseId` | `curso_matriculado_id` |
| `enrollmentId` | `matricula_id` |
| `enviadaEm` | `enviada_em` |
| `enviadoEm` | `enviado_em` |
| `exerciseId` | `exercicio_id` |
| `explanation` | `explicacao` |
| `extraCourseIds` | `cursos_extras_ids` |
| `failedLoginAttempts` | `tentativas_login_falhas` |
| `feedback` | `devolutiva` |
| `fileName` | `nome_arquivo` |
| `fileUrl` | `url_arquivo` |
| `footerText` | `texto_rodape` |
| `gradedAt` | `corrigido_em_texto` |
| `gradedBy` | `corrigido_por` |
| `hasChat` | `tem_chat` |
| `image` | `imagem` |
| `inativadoEm` | `inativado_em` |
| `inativadoPor` | `inativado_por` |
| `institutionLogoPath` | `caminho_logo_instituicao` |
| `institutionName` | `nome_instituicao` |
| `instructions` | `instrucoes` |
| `instructorId` | `instrutor_id` |
| `instructorName` | `nome_instrutor` |
| `ipAddress` | `ip` |
| `isLive` | `ao_vivo` |
| `issueDate` | `data_emissao` |
| `lesson_order` | `ordem` |
| `lessonId` | `aula_id` |
| `likedBy` | `curtido_por` |
| `likes` | `curtidas` |
| `lockedUntil` | `bloqueado_ate` |
| `maxPoints` | `pontuacao_maxima` |
| `meetingLink` | `link_reuniao` |
| `minAttendance` | `frequencia_minima` |
| `motivoInativacao` | `motivo_inativacao` |
| `name` | `nome` |
| `nomeSocial` | `nome_social` |
| `ocorridoEm` | `ocorrido_em` |
| `options` | `opcoes` |
| `originalName` | `nome_original` |
| `ownerUserId` | `dono_usuario_id` |
| `pageKey` | `chave_pagina` |
| `passed` | `aprovado` |
| `passwordHash` | `senha_hash` |
| `prazoEm` | `prazo_em` |
| `questionText` | `texto_questao` |
| `quizId` | `avaliacao_id` |
| `recommendedModule` | `modulo_recomendado` |
| `reviewMessage` | `mensagem_revisao` |
| `role` | `papel` |
| `scheduledAt` | `agendado_para_texto` |
| `score` | `nota` |
| `scorePercent` | `percentual_nota` |
| `senderName` | `nome_remetente` |
| `senderRole` | `papel_remetente` |
| `senderUserId` | `remetente_usuario_id` |
| `sessionId` | `sessao_id` |
| `signatories` | `signatarios` |
| `size` | `tamanho` |
| `statusCurso` | `status_curso` |
| `studentName` | `nome_aluno` |
| `studentUserId` | `aluno_usuario_id` |
| `submissionText` | `texto_entrega` |
| `submittedAt` | `enviado_em_texto` |
| `text` | `texto` |
| `thumbnail` | `miniatura` |
| `time` | `horario` |
| `timestamp` | `registrado_em_texto` |
| `title` | `titulo` |
| `type` | `tipo` |
| `updatedAt` | `atualizado_em` |
| `updatedByUserId` | `atualizado_por_usuario_id` |
| `user` | `usuario` |
| `userId` | `usuario_id` |
| `verificationHash` | `hash_verificacao` |
| `versaoDaSessao` | `versao_da_sessao` |
| `videoUrl` | `url_video` |
| `vigenciaAte` | `vigencia_ate` |
| `visibility` | `visibilidade` |

## Mapa: caminhos da API

| Antes | Depois |
|---|---|
| `/api/auth/register` | `/api/autenticacao/cadastro` |
| `/api/auth/login` | `/api/autenticacao/entrar` |
| `/api/auth/me` | `/api/autenticacao/eu` |
| `/api/auth/logout` | `/api/autenticacao/sair` |
| `/api/auth/password` | `/api/autenticacao/senha` |
| `/api/auth/users` | `/api/autenticacao/usuarios` |
| `/api/auth/users/{id}/name` | `/api/autenticacao/usuarios/{id}/nome` |
| `/api/auth/users/{id}/password` | `/api/autenticacao/usuarios/{id}/senha` |
| `/api/library` | `/api/biblioteca` |
| `/api/courses` | `/api/cursos` |
| `/api/courses/resolve/{valor}` | `/api/cursos/resolver/{valor}` |
| `/api/progress` | `/api/progresso` |
| `/api/enrollments` | `/api/matriculas` |
| `/api/enrollments/self/enroll` | `/api/matriculas/proprio/matricular` |
| `/api/enrollments/self/drop` | `/api/matriculas/proprio/desistir` |
| `/api/enrollments/self/complete` | `/api/matriculas/proprio/concluir` |
| `/api/admissions` | `/api/ingressos` |
| `/api/certificates` | `/api/certificados` |
| `/api/certificates/verify` | `/api/certificados/verificar` |
| `/api/files/{id}` | `/api/arquivos/{id}` |
| `/api/quizzes` | `/api/avaliacoes` |
| `/api/quiz-submissions` | `/api/tentativas-avaliacao` |
| `/api/forum/{id}/like` | `/api/forum/{id}/curtir` |
| `/api/exercises` | `/api/exercicios` |
| `/api/exercise-submissions` | `/api/entregas-exercicio` |
| `/api/exercise-submissions/{id}/grade` | `/api/entregas-exercicio/{id}/corrigir` |
| `/api/dms` | `/api/mensagens-diretas` |
| `/api/academic-requests` | `/api/solicitacoes-academicas` |
| `/api/system-settings` | `/api/configuracoes-sistema` |
| `/api/document-templates` | `/api/modelos-documento` |
| `/api/document-templates/{type}/preview` | `/api/modelos-documento/{tipo}/previa` |
| `/api/site-content` | `/api/conteudo-site` |
| `/api/security-logs` | `/api/logs-seguranca` |
| `/api/telemetry` | `/api/telemetria` |
| `/api/export/{dataset}` | `/api/exportar/{dataset}` |

## Como conferir

- `php artisan migrate` em banco novo e em banco com dados; `php artisan migrate:rollback --step=1`
  volta aos nomes antigos.
- A suíte inteira roda contra o schema novo.

