# ADR 025 — Mais de um gestor por curso, curso sem gestor e gestor criado pelo painel

- **Data:** 27/10/2026
- **Situação:** aceita — decisão do responsável (pedido de 26/10, desenho confirmado em 27/10), implementada
- **Afeta:** ADR 10 (posse por chave estrangeira), ADR 12 (nada é apagado), ADR 19 (painel administrativo em Blade), ADR 22 (sessão revogável), ADR 24 (nomes em português)

## Contexto

Até aqui a posse de um curso era **uma coluna só**: `curso.instrutor_id`. Um curso tinha exatamente um
gestor, e a regra "este instrutor gerencia este curso" era a comparação `instrutor_id == eu`, escrita à mão
em quatro lugares (`InstructorScope::courseIds`, `CourseService::assertCourseOwnership`,
`EnrollmentService::assertInstructorCanManage` e `AreaDoInstrutorController::meuCurso`).

A coordenação pediu três coisas que esse desenho não comporta:

1. **Um curso editável por mais de um gestor.**
2. **Curso sem gestor** é permitido (e ao retirar um gestor isso passa a acontecer).
3. **Criar e retirar gestor pela aba Equipe**, atribuindo cursos na criação e podendo mudá-los depois.
   Antes, gestor só nascia pela API (`POST /api/autenticacao/cadastro` com `papel` de admin) e não
   havia tela para retirá-lo; o formulário de criação do painel React nunca foi ligado a nada.

Isto muda **schema** e **autorização**, as duas categorias que o `AGENTS.md` ("Nunca mude sem sinalizar")
reserva à validação humana explícita. A validação foi dada pelo responsável antes da implementação.

## Decisão

### 1. Tabela `curso_gestor`: os gestores adicionais

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | bigint, autoincremento | |
| `curso_id` | varchar(191) | FK `fk_curso_gestor_curso_id` → `curso.id`, `RESTRICT` |
| `usuario_id` | varchar(191) | FK `fk_curso_gestor_usuario_id` → `usuario.id`, `RESTRICT` |
| `criado_em` | timestamp | |
| `inativado_em`, `inativado_por`, `motivo_inativacao` | as três colunas da ADR 12 | |

Índices: único `uq_curso_gestor_curso_usuario (curso_id, usuario_id)` e `idx_curso_gestor_usuario_id`.
Nome e padrão seguem a ADR 24. Model: `App\Models\CursoGestor`, com o trait `Inativavel`.

### 2. `curso.instrutor_id` continua sendo o gestor **responsável**

Ele segue aparecendo na vitrine, no certificado e nas listas (`nome_instrutor` é o seu nome em texto).
`curso_gestor` guarda **os demais**. Não houve mudança em `curso`: `instrutor_id` já era anulável, com
`ON DELETE SET NULL`.

**Quem gerencia um curso é o responsável OU qualquer vínculo ativo em `curso_gestor`.** Essa regra mora em
**um só lugar**, `App\Services\GestoresDoCurso` (`gerencia()`, `cursoIdsDe()`), e os quatro pontos acima
passaram a chamá-la. As outras leituras de `instrutor_id` (vitrine, exportação, relatórios) são de
**exibição**, não de autorização, e não mudaram.

Isto **amplia** quem pode editar um curso: um gestor adicional passa a ter, no curso, o mesmo poder do
responsável. É o que o pedido quer, e é a única mudança de autorização.

### 3. Curso sem gestor

Um curso sem responsável tem `instrutor_id = NULL` e `nome_instrutor = ''` (a coluna é `NOT NULL`, então
vazio, e não nulo). Só a coordenação o edita. A aba Equipe lista esses cursos numa faixa própria, "Cursos
sem gestor ativo".

"Sem gestor ativo" inclui o curso cujo único gestor foi retirado da equipe, e o curso cujo responsável é um
admin (`papel` diferente de `instructor`).

### 4. Atribuir e desatribuir (`GestoresDoCurso::definirCursosDoGestor`)

A tela entrega a lista completa de cursos marcados, e o serviço faz a diferença, numa transação:

- **Vincular** a um curso **sem responsável**: a pessoa vira a responsável (`instrutor_id`, `nome_instrutor`).
- **Vincular** a um curso **com responsável**: vira gestora adicional (linha em `curso_gestor`; se havia uma
  linha inativa, ela é reativada — não se cria duplicata).
- **Desvincular** a **responsável**: passa a responsável a gestora adicional **mais antiga** (o vínculo dela é
  inativado, para não existir em dois lugares); se não há nenhuma, o curso fica sem gestor.
- **Desvincular** uma adicional: a linha é **inativada**, não apagada.

### 5. Criar e retirar gestor

- **Criar** (`POST /admin/equipe`): `AuthService::register` com `papel = instructor`, o mesmo caminho do
  provisionamento por admin que já existia; conta **ativa** na hora, sem passar pela confirmação de cadastro.
  A senha é opcional; em branco, o sistema gera uma e a mostra **uma vez** para a coordenação repassar.
  Os cursos marcados são vinculados na mesma requisição. Fica na auditoria ("Cadastro de Usuário").
- **Editar cursos** (`POST /admin/equipe/{id}/cursos`): auditoria "Alteração de Cursos do Gestor".
- **Retirar** (`POST /admin/equipe/{id}/retirar`): **sem motivo obrigatório**. É `AuthService::deleteUser`, o
  mesmo de aluno: a conta é inativada e bloqueada, e as sessões caem (ADR 22). **Os vínculos não são
  desfeitos**: o curso continua ligado à pessoa, e reativá-la devolve tudo como estava.
- **Reativar** já existia (26/10) e continua igual.
- Só o admin faz qualquer uma dessas ações; aluno e gestor são redirecionados, e um id de aluno nas rotas de
  gestor devolve 404.

## Alternativas consideradas

| Alternativa | Por que não |
|---|---|
| Tirar `instrutor_id` e guardar **todos** os gestores só em `curso_gestor` | Mexe em todas as leituras de `instrutor_id` (vitrine, certificado, exportação, relatórios) e em `nome_instrutor`, para um ganho só de simetria. O risco de quebrar exibição é maior que o de manter dois lugares atrás de uma única regra de posse. |
| Apagar a linha de `curso_gestor` ao desvincular | Contraria a ADR 12; a linha é o registro de quem gerenciou o quê, e quando. |
| Ao retirar o gestor, **desvincular** os cursos dele | Perde-se o vínculo no caso mais comum (afastamento temporário), e reativar passaria a exigir reatribuir tudo à mão. Os cursos já ficam visíveis como "sem gestor ativo". |
| Tornar o curso sem gestor impossível | Contraria o pedido, e a própria retirada de um gestor o produz. |

## Consequências

- **Poder de edição ampliado:** um gestor adicional edita o curso inteiro (aulas, avaliações, matrículas, alunos
  do curso) como o responsável. Não há "gestor só leitura"; se a coordenação quiser níveis, é decisão nova.
- **`nome_instrutor` do retirado permanece** na vitrine e no certificado até a coordenação escolher outro
  responsável. É aceito: apagá-lo reescreveria um dado público sem ordem de ninguém.
- **Admin como responsável** (curso criado pelo admin sem indicar o gestor) aparece como "sem gestor ativo". É
  verdade para efeito de edição por gestor, e evita esconder o curso.
- **Migration reversível** (`down()` remove só a tabela nova). Nenhuma linha existente de outra tabela é alterada.
- **Testes:** `tests/Feature/GestoresDoAdminTest.php` (9) cobre criar com cursos, gestor adicional abrindo o
  curso e o de fora recebendo 404, curso sem gestor, promoção ao desvincular o responsável, reativação sem
  duplicata, retirar sem motivo mantendo vínculos, validação, permissão e 404 para aluno. Com a regra do
  adicional desligada de propósito, dois deles falham.

## Fora do escopo

- Escolher o gestor **por curso** na tela de Cursos (lá continua o seletor do "professor responsável"). A
  atribuição de gestores adicionais é feita pela aba Equipe, no gestor.
- Papéis diferentes entre gestores do mesmo curso.
- Notificar o gestor por e-mail ao ser criado ou vinculado; a senha é repassada pela coordenação.
