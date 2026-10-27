# ADR 018 — O editor de aula passa a ser do servidor (Blade), e grava uma coisa de cada vez

- **Data:** 21/10/2026
- **Situação:** aceita — fase M6 do plano 16, decisão do responsável pelo projeto
  ("editor de aula primeiro, admin depois")
- **Afeta:** ADR 017 (MPA em vez de SPA), ADR 12 (nada é apagado)

> **Nota (27/10/2026):** os nomes de tabela, coluna e rota citados aqui foram atualizados para os de depois da [ADR 024](024-nomes-em-portugues.md); o texto histórico segue como foi decidido.

## Contexto

O plano 16 deixou o editor de conteúdo de aula para o fim (M6) e exigiu uma
decisão técnica própria registrada em ADR, porque é a única peça da área do
instrutor que parecia pedir JavaScript pesado: ~1.500 linhas de React
(`LessonManagePage`, `LessonContentEditor`, `LessonMediaForm`) mais o parser de
conteúdo (`lessonContent.ts`).

O levantamento mostrou três coisas:

1. **Não havia editor de texto rico.** O conteúdo é um Markdown reduzido, cortado
   em blocos (seção, subtítulo, parágrafo, listas, código, imagem, vídeo), e o
   editor trocava UM trecho por vez, por emenda no texto. Nada de `contentEditable`
   nem biblioteca de editor: dava para portar.
2. **O parser já existia em PHP, só de leitura** (`ConteudoDaAula`, usado pela
   página da aula do aluno). Faltava a metade de escrita.
3. **O salvamento era frágil.** Toda edição de uma aula fazia
   `PUT /api/cursos/{id}` com a lista de TODAS as aulas do curso. Quem salvava a
   identificação de uma aula regravava o texto das outras com o que a sua tela
   guardava, e uma aba velha desfazia, sem aviso, o trabalho de outra pessoa.

## Decisão

**O editor é do servidor: Blade, formulários e links, sem editor no navegador.**

1. **Cada bloco é um formulário.** Editar, mover, remover e inserir são `POST`s
   para o servidor; o formulário aberto vem do endereço (`?editar=material&bloco=3`),
   e não de estado em JavaScript. Funciona sem JS, o F5 volta para o mesmo lugar
   e o erro de validação reabre a zona certa. Alpine só alterna campos dentro do
   formulário de um trecho de texto.
2. **A escrita é portada, não reinventada.** `EdicaoDaAula` (serializar,
   substituir, remover, inserir, mover) é a tradução de `lessonContent.ts`, e
   `MaterialDaAula` traduz o que os dois componentes React faziam. Editar um bloco
   troca só o trecho dele: o resto da aula fica idêntico byte a byte.
   **Conferido contra o TypeScript** sobre as 82 aulas reais do banco e 14 casos
   de borda: 5.184 comparações, zero divergências.
3. **Cada escrita toca uma coisa só.** `CourseService` ganha `criarAula`,
   `atualizarAula`, `editarConteudoDaAula`, `anexarDocumento` e
   `desvincularDocumento`. Nenhuma regrava o curso inteiro.
4. **O texto se edita com trava otimista.** Cada formulário leva o hash do texto
   que a pessoa viu; se o texto gravado já é outro, nada é aplicado e ela recebe o
   aviso para recarregar. O índice do bloco só vale para o texto que foi visto.
5. **Cada ação grava na hora.** Não há rascunho no navegador nem botão "Salvar
   material": mover, remover, inserir e aplicar já gravam. Remover pede confirmação
   na página e deixa na auditoria o texto do trecho removido.
6. **Endereços `/inst/curso/{ref}/aulas/{id}` e `/aulas/nova`.** Plural, porque o
   editor React ocupava `/aula/...` até a virada; os endereços antigos redirecionam
   para os novos. `/inst` passa a ser prefixo no contrato de rotas, e a última rota
   da árvore casa com qualquer profundidade — o que o Laravel não atende cairia no
   fallback, que serve o React, que o devolveria ao servidor para sempre.

## O que muda para quem usa (e precisa de validação humana)

| Antes (React) | Agora (Blade) | Por quê |
|---|---|---|
| Edição acumulada num rascunho, gravada por "Salvar material" | Cada ação grava na hora | Sem rascunho para perder; a trava de versão cobre o conflito |
| Prévia ao vivo dentro do formulário de formatação | A prévia é o próprio trecho, depois de aplicar | Prévia ao vivo exigiria o parser no navegador |
| Nova aula num modal, com o editor de blocos | Formulário simples; depois de criada, a aula abre na página dela | O modal tentava fazer num canto o que a página faz inteira |
| Legenda de bloco de código livre | Legenda de código também no padrão ("Código 1: …") | Fora do padrão ela virava parágrafo solto na releitura |
| Tamanho do arquivo de anexo "1.2 MB" inventado | Em branco, ou o tamanho real do arquivo enviado | Informação que o sistema não tinha |
| Selo "Aula obrigatória/opcional" | Não veio | A coluna `isOptional` não existe; o botão nunca gravou nada |
| Título de aula até 200 caracteres | Até 191 | É o tamanho da coluna; 200 virava erro de banco |

## Consequências

- **A edição de texto sobrescreve o texto anterior**, como já acontecia. A regra de
  "nada é apagado" (ADR 12) cobre pessoa, disciplina, conteúdo, avaliação, nota e
  entrega; o texto de uma aula editada não tem histórico de versões. A remoção de
  trecho fica na auditoria, com o texto — mas um histórico de versões da aula é
  trabalho novo, **não feito aqui**, e fica como pendência para decisão.
- O bloco de código continua sem realce de sintaxe e sem poder ser criado pelo
  menu (só editado), pela mesma razão de antes: quem escreve aula não é quem
  programa.
- O código React do instrutor (`InstructorDashboard` e filhos) fica sem rota que o
  alcance. Sai na M7, junto com o resto do React — como o do aluno.
