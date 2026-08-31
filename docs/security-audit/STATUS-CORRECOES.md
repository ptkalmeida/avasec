# Status das correções — auditoria de 31/08/2026

O relatório em PDF é o registro do estado do sistema **na data da auditoria** e não é
reescrito: é a evidência que a revisão de PR precisa para julgar se a correção basta.
Este arquivo é o acompanhamento — o que foi corrigido, onde, e o que segue aberto.

Última atualização: 31/08/2026.

## Corrigido

| Achado | Sev. | Onde | Commit |
|---|---|---|---|
| SEC-01 — build de produção saindo em modo desenvolvimento | crítica | `.env`, `.env.example`, `scripts/verificar-build-producao.mjs`, `package.json` | `f68c6b4` |
| SEC-02 — PINs de demonstração no bundle | alta | `src/dev/demoProfiles.ts`, `ProfileView.tsx`, `App.tsx` | `0968ca1` |
| SEC-03 — senha padrão `1234` na criação de aluno | média | `AdminDashboard.tsx`, `utils/cpf.ts`, `LMSContext.tsx` | `0968ca1` |
| ISO-01 — catálogo público entregando o material do curso | alta | `CourseService::listCoursesFor`, `routes/api.php` | `3a7b597` |
| XSS-01 — `javascript:` em campo de URL usado em `href` | alta | `App\Support\SafeUrl`, `SafeUrlRule`, `src/utils/safeUrl.ts` | `7a3a033` |
| ISO-02 / ISO-03 — fórum sem escopo de curso (leitura e escrita) | média | `LearningService` | `6164704` |
| ISO-05 — gabarito de quizzes de toda a escola | média | `LearningService::listQuizzes`, `submitQuiz` | `6164704` |
| ISO-06 — exercícios sem escopo de curso | baixa | `LearningService::listExercises` | `6164704` |
| IDOR-01 — instrutor baixando entrega privada de turma alheia | média | `UploadService::podeBaixar` | `6164704` |
| PERM-01 — instrutor emitindo certificado de curso alheio | média | `CertificateService::issueCertificate` | `6164704` |
| PERM-02 — `system-settings` público com merge de chave arbitrária | baixa | `SettingsService::ALLOWED_KEYS` | `6164704` |
| XSS-02 — anotações via `innerHTML` e título sem escape no arquivo exportado | baixa | `src/utils/noteHtml.ts`, `StudentDashboard.tsx` | `6164704` |

### Achados adicionais, encontrados durante a correção

Não estavam entre os 15 do relatório:

1. **Modal "Redefinir Senha" do admin não chamava API alguma** — só alterava estado
   local do React. A coordenação via a confirmação e a senha antiga continuava
   valendo: acesso que se acreditava revogado seguia ativo. O endpoint passou a
   existir (`PUT /api/auth/users/{id}/password`, `role:admin`, auditado). Commit
   `0968ca1`.
2. **Senha padrão `1234` era inválida desde a ADR 11** (mín. 8, letra e número): o
   cadastro de aluno falhava sem a tela dizer por quê. Commit `0968ca1`.
3. **Senha em texto plano no `localStorage`** — `studentsList` guardava o campo
   `password`, que ninguém lia, persistido no navegador de quem cadastra. Commit
   `0968ca1`.
4. **Título da aula sem escape no arquivo exportado** de anotações. O título vem do
   servidor, então não é self-XSS: `</title><script>` num título executaria na máquina
   do aluno. Commit `6164704`.
5. **Geradora de senha podia produzir senha inválida** — a antiga (base-36 de bytes)
   podia sair só com dígitos ou só com letras, e nesse caso a API rejeitava. Commit
   `0968ca1`.

## Aberto — depende de decisão ou execução humana

### 1. Rotação das senhas de demonstração (SEC-02, metade operacional)

Tirar os PINs do código **não os invalida**: `1234`, `5678` e `9999` continuam
valendo nas contas e estão em 69 commits do histórico do git.

O comando existe e **não foi executado** — é operação sobre dado real:

```bash
cd backend-laravel
php artisan avasec:rotate-demo-passwords --dry-run   # lista, sem alterar
php artisan avasec:rotate-demo-passwords             # pede confirmação e exibe as novas UMA vez
```

O `--dry-run` de 31/08/2026 encontrou **8 contas**, não as 3 esperadas:

- `Admin Superior` (admin) — admin@avasec.local
- `Gestor de Conteúdos` (instructor) — professor@avasec.local
- 6 contas de aluno (`joao.silva`, `beatriz.c`, `sofia.rocha`, `ana.souza`,
  `lucas.santana`, `carol.mendes`)

As senhas novas aparecem uma única vez: o banco guarda só o hash.

### 2. ISO-04 e IDOR-02 — solicitações acadêmicas (média)

**Não corrigidos de propósito.** O `RequestService` declara no próprio código que
"instrutor/admin acompanham todas (secretaria centralizada)" — é regra de negócio
validada, e estreitá-la cai no `06-controle-de-mudancas.md`.

O que a auditoria observou: as justificativas carregam motivo de saúde e familiar, e
hoje qualquer instrutor lê e defere as de toda a escola, não só as dos próprios alunos.

Decisão necessária: manter a secretaria centralizada, ou restringir o instrutor aos
alunos dos seus cursos (mantendo admin irrestrito)? Enquanto não houver decisão, o
comportamento segue como está.

### 3. Contas de teste no banco de desenvolvimento

`Aluno Teste` (CPF público `52998224725`, senha `senha123456`) e outras contas
vazadas de execuções de teste continuam no MySQL de dev. Ligadas à pendência de
exclusão dos alunos antigos, que aguarda o cadastro de um aluno no modo novo e a
validação dos três perfis.

## Explorabilidade — o que a configuração atual já limita

Estas flags estão **desligadas** em `config/features.php`, então as rotas respondem
404 hoje: `forum`, `atividadesPraticasAvancadas`, `solicitacoesAcademicas`,
`liveClassroom`, `mensagensDiretas`.

Isso reduz o alcance de ISO-02, ISO-03, ISO-06, ISO-04 e IDOR-02 **na configuração
atual** — e não é correção: flag é chave de liga/desliga, não controle de acesso.
Os testes de escopo ligam as flags explicitamente e cobram a regra com elas ativas,
para que ligar a funcionalidade não reabra o vazamento.

## Gates

Todos os commits acima passaram, antes do commit: `tsc --noEmit`, Vitest (147 testes),
PHPUnit (211 testes), Pint, PHPStan nível 9 e `npm run build` com o verificador de
artefato.
