# ADR 14 — Certificado exige avaliação aprovada; e módulo deixa de existir

- **Status:** aceita
- **Data:** 09/09/2026
- **Decidida por:** coordenação do AVASEC
- **Relaciona-se com:** [ADR 12](012-nada-e-apagado.md) (certificado é registro
  acadêmico e sua revogação é ato administrativo) e
  [ADR 13](013-slug-de-curso-no-endereco.md) (que registrou a pendência de módulo).

## Contexto

Duas coisas que vinham da mesma raiz — informação na tela sem dado por trás —
foram decididas na mesma conversa.

### 1. O certificado saía por navegação

Em 08/09/2026 a conclusão de aula passou a ser **automática ao avançar**, a
pedido da coordenação, porque marcar manualmente não era intuitivo. O efeito
colateral foi avisado no mesmo dia e confirmado: frequência era o **único**
critério de emissão, e a frequência é alimentada pela conclusão de aulas. Logo,
**quem clicasse "Próxima aula" até o fim recebia certificado sem responder uma
única questão.**

A cadeia já existia pelo botão manual; a conclusão automática a tornou trivial
de alcançar sem intenção.

### 2. Módulo não existia

O painel do aluno exibia três "módulos" por curso, com nome e descrição escritos
dentro do componente e presos a `course.id === 'course-1'` / `'course-2'`,
dividindo as aulas **por posição** (`lessons.slice(0, 2)`).

`Lesson` não tem coluna de módulo. Não há tabela `Module`. Consequências
verificadas no banco e na tela:

- o gestor **não podia** criar, renomear ou reordenar módulo — não havia campo;
- inserir uma aula no começo fazia as aulas **escorregarem de módulo em
  silêncio**, sob um título que descrevia outro conteúdo;
- os quatro cursos fora daqueles dois recebiam "Módulo 1: Introdução Básica",
  cuja descrição prometia "exercícios de fixação assistida e material
  complementar" que podiam não existir;
- a vitrine descrevia cada aula com um texto sorteado por `idx % 4` — a aula 3
  de qualquer curso era "Avaliação Teórica de Meio-Termo";
- "Ver grade completa" abria uma **ementa inteira inventada**: quatro módulos
  fixos, com nomes de aula próprios ("Aula 1.1: Boas-vindas e Configuração de
  Perfil"), durações e pré-requisitos — iguais para todos os cursos;
- o relatório gerencial "Progresso por Módulo" percorria `course.lessons` e
  gravava as colunas como `id_modulo` / `titulo_modulo`: o dado era de aula, só
  o rótulo dizia módulo;
- e o `?modulo=` da URL (ADR 13) carregava o **nome** desse módulo inexistente.

## Decisão

**1. Curso com avaliação exige todas as avaliações ativas APROVADAS, além da
frequência.** Curso sem avaliação segue apenas pela frequência — é o que ele
mede. "Aprovada" é `QuizSubmission.passed`, coluna que já existia: responder e
zerar a prova não conclui o curso.

A regra vive em `CertificateService::issueCertificate`, no **servidor**. O
cliente nunca decidiu isso e continua não decidindo; a tela apenas deixou de
prometer o que o servidor vai recusar.

**2. Módulo não existe.** Em vez de criar tabela `Module` e um editor para o
gestor, a coordenação decidiu que **a lista de aulas basta**. Tudo o que
apresentava módulo como fato foi removido, e o que era dado de aula passou a se
chamar aula.

## Consequências

**Três recortes na exigência de avaliação, cada um com teste, para não travar o
certificado de quem não tem culpa:**

1. Avaliação **inativada** não conta (o SoftDeletes já a exclui). Tirar uma prova
   do ar não pode congelar o certificado de todos que cumpriram o curso.
2. Só avaliação **visível ao aluno** conta, e a escada de `Visibilidade` é
   consultada com o papel `student` **fixo**, não com o papel de quem chama.
   Prova em rascunho não pode ser cobrada de quem não a enxerga — e, com o papel
   do requisitante, o gestor emitindo em nome do aluno seria cobrado por uma
   prova que só o gestor vê.
3. Aprovação vale de **qualquer tentativa**, não da última. A ADR 12 manda
   acrescentar tentativa em vez de sobrescrever; ler "a última" tiraria um
   certificado já conquistado de quem refez a prova e foi mal.

**"Concluir curso" foi escondido enquanto há avaliação pendente.** Concluir
encerra a matrícula e remove o curso da lista de disponíveis: quem concluísse com
prova pendente ficaria **sem o certificado e sem poder se rematricular** para
fazê-la. Nenhum aviso conserta isso depois de clicado. É a única parte desta ADR
que vai além do pedido literal, e é reversível numa linha.

**A regra passou a ser dita em quatro lugares** que antes afirmavam só
frequência: o medidor de frequência, o banner de conclusão, o FAQ (em duas telas)
e o regulamento exibido antes da matrícula. Os textos também deixaram de fixar
70%: o mínimo vem de `courseMinAttendance`.

**Cabeçalhos de CSV mudaram.** `buildProgressoModulo` virou `buildProgressoAula`,
e `id_modulo` / `titulo_modulo` / `status_modulo` viraram `id_aula` /
`titulo_aula` / `status_aula`. É mudança visível para quem já baixou a planilha —
a alternativa era manter `titulo_modulo` com título de aula dentro, que é o
problema, não a solução.

**`recommendedModule` (em `QuizQuestion`) FICA.** É coluna real, preenchida à mão
pelo instrutor como dica de revisão depois de uma resposta errada. É texto de uma
pessoa, não estrutura inventada — e apagá-la seria destruir dado histórico, o que
a ADR 12 proíbe. Só o conceito de módulo como *agrupamento de aulas* deixou de
existir.

**`?modulo=` saiu da URL**, encerrando a pendência registrada na ADR 13.

## O que fica pendente

A duplicação da regra entre `CertificateService` (servidor, autoridade) e
`src/utils/certificadoElegivel.ts` (tela, para não prometer errado) é
consciente: são perguntas diferentes — "posso emitir?" e "o que digo antes de a
pessoa clicar?". Se divergirem, quem manda é o servidor e a tela mostra o erro
dele. Não há teste que prenda as duas juntas, e esse é o risco aceito.
