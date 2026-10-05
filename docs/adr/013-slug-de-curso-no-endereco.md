# ADR 13 — O endereço diz o nome do curso: slug persistido

- **Status:** aceita
- **Data:** 09/09/2026
- **Decidida por:** coordenação do AVASEC
- **Substitui:** a decisão registrada em `src/router/instructorRoutes.ts` de usar
  o id do curso no endereço. Complementa a [ADR 12](012-nada-e-apagado.md) — o
  histórico de slugs é append-only pelo mesmo motivo que o resto.

## Contexto

Os endereços de curso eram `/aluno/curso/course-1` e `/inst/curso/course-2`. O
`course-1` não diz nada a ninguém — e o endereço é justamente a parte da tela
que a pessoa copia, salva nos favoritos e manda para outra.

Havia uma decisão registrada **contra** slug, e as razões eram boas:

> *"O identificador é o id do curso, não um slug do título. Duas razões:
> categoria não serve (dois cursos dividem "Design Digital", então
> `/inst/design-digital` não diria qual), e o título é editável — slug de título
> quebraria todo link salvo no dia em que a coordenação corrigisse uma palavra."*

A segunda razão continua **inteiramente verdadeira** — para slug *derivado do
título na hora de montar o link*. Não se aplica a slug **persistido**, que é o
que esta ADR decide. O que mudou não foi a opinião sobre o risco; foi o preço
pago para eliminá-lo.

## Decisão

O endereço de curso passa a ser o **slug**, uma coluna `Course.slug` com índice
**único**, derivada do título no cadastro e **nunca aceita do cliente**.

`/aluno/curso/ux-ui-design-interfaces-de-alta-performance`

Três garantias, cada uma com teste que cai se ela for perdida:

1. **Link salvo não morre.** Renomear um curso gera slug novo e **aposenta** o
   antigo em `CourseSlugHistory`, que continua resolvendo para o mesmo curso.
   `GET /api/courses/resolve/<valor>` responde a partir de slug atual, slug
   aposentado ou id, e informa qual é o canônico. O cliente então troca a URL da
   barra — senão a forma antiga se propaga para sempre, copiada de tela em tela.
2. **Id continua funcionando.** Todo link que circula hoje tem a forma
   `/aluno/curso/course-1`. Uma melhoria de endereço que transforma link salvo
   em 404 é uma regressão disfarçada de melhoria.
3. **Nenhum slug tem a forma de um id.** Um curso chamado *"Course 1"* geraria o
   slug `course-1`, que é o **id de outro curso** — e como o roteador aceita as
   duas formas, o link abriria o curso errado **sem erro na tela**.
   `CursoSlug::ehFormaDeId` bloqueia isso na geração, e `cursoPorRef` dá
   precedência ao slug como segunda barreira.

## Consequências

**O que custou.** Uma mudança de schema (coluna + tabela de histórico +
migration com backfill), um endpoint novo e a renomeação de `courseId` para
`cursoRef` nos roteadores do frontend — o campo passou a carregar slug, e manter
o nome antigo seria uma mentira no tipo.

**O que ficou pior, e foi aceito.** Renomear um curso e desfazer o rename **não
devolve o slug original**: ele está aposentado e ainda resolve, então o curso
recebe `-2`. O endereço fica um pouco feio. A alternativa era reaproveitar um
slug aposentado, e aí o link salvo de um curso passaria a abrir **outro** — pior
que o link morto que esta ADR veio evitar.

**Unicidade atravessa as duas tabelas.** Slug novo é conferido contra os slugs
em uso *e* os aposentados. E a leitura de `Course` para essa conferência é SQL
cru de propósito: o SoftDeletes esconderia curso inativado, cujo slug continua
ocupado — a mesma armadilha de upsert registrada na ADR 12.

**Rascunho não vaza.** `/api/courses/resolve` é público como o catálogo e passa
pela mesma escada de audiência (`Visibilidade`). Sem isso, ela seria o único
jeito de um visitante confirmar que um curso não publicado existe.

## Alternativas descartadas

- **Slug + id no fim** (`.../ux-ui-design-course-1`): resolve tudo sem mudança de
  schema, porque a leitura usaria só o id e o slug seria decoração. Descartada
  por deixar o id visível no endereço — o incômodo que originou o pedido.
- **Slug derivado do título na hora de montar o link**: sem custo de schema, e
  com exatamente o defeito que a decisão anterior previu — rename mata link
  salvo, e título repetido colide.

## Pendência conhecida — RESOLVIDA em 09/09/2026

O parâmetro `?modulo=` carregava o **nome do módulo**, e módulo não existia no
banco: `Lesson` não tinha coluna de módulo, não havia tabela `Module`, e os
módulos eram construídos em `getCourseModules` a partir de
`course.id === 'course-1'`, dividindo as aulas **por posição**. Esse trecho do
endereço estava ancorado em texto que ninguém cadastrou.

A coordenação decidiu que módulo **não precisa existir** — a lista de aulas
basta. `?modulo=` saiu do tipo e da URL. Ver
[ADR 14](014-certificado-exige-avaliacao.md).
