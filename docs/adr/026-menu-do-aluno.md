# ADR 026 — Menu do aluno: todos os cursos, continuar estudando, avaliações no menu e ajuda e suporte unidos

- **Data:** 27/10/2026
- **Situação:** aceita — o responsável autorizou, item a item, as mudanças que alteram o desenho aprovado em 19/10
- **Afeta:** skill `redesenhar-ava-lms` (telas 3 e 4, menu lateral), ADR 018/019 (telas em Blade)

## Contexto

Lendo o fluxo do aluno no código, apareceram atritos que a própria estrutura do menu causava:

1. **Vários cursos, um menu só.** A matrícula múltipla está ligada, mas o menu mostrava um único "Meu curso" (o primeiro em andamento). Para trocar de curso, o aluno voltava ao painel.
2. **Dois itens para a mesma ideia.** "Meu curso" e "Cursos concluídos" eram duas listas separadas.
3. **O caminho até as avaliações** — o que decide a certificação — estava só num link dentro da página do curso.
4. **Duas listas de menu escritas à mão** (o menu largo e o das telas estreitas), que já tinham divergido uma vez: o suporte apontava, no estreito, para uma tela fechada ao aluno.
5. **Ajuda e Suporte pedagógico** eram dois itens para a mesma pergunta do aluno: "preciso de ajuda".

Parte dessas melhorias contraria o que a skill aprovou em 19/10 (evitar controle duplicado, rótulos exatos, "Suporte pedagógico" como item próprio). Por isso cada uma foi apresentada ao responsável e autorizada.

## Decisão

| Mudança | Antes | Depois |
|---|---|---|
| Cursos no menu | um "Meu curso" | "Meus cursos": todos os em andamento, com "X de Y aulas concluídas" e o atual marcado; "Cursos concluídos" continua submenu, dentro dele |
| Atalho | só "Iniciar aula" na página do curso | "Continuar estudando": a primeira aula não concluída (do curso em tela, se houver; senão do primeiro em andamento) |
| Avaliações | link "Avaliações do curso" na página do curso | item "Avaliações" do menu, sob o curso em tela; **sai** da página do curso (um caminho só) |
| Rótulos | Painel · Catálogo | **Início** · **Descobrir cursos** |
| Ajuda | "Ajuda" e "Suporte pedagógico" | "**Ajuda e suporte**", com duas abas: *Dúvidas frequentes* e *Falar com o gestor* |
| Lista do menu | duas, à mão | uma, em `App\Services\MenuDoAluno`, lida pelo menu largo e pelo estreito |

Detalhes que valem como regra:

- **O percentual nunca vira texto.** A contagem exibida é "X de Y aulas concluídas", como a skill já exigia.
- **"Ajuda e suporte" abre nas dúvidas**; dentro de uma aula abre direto na conversa, com a aula junto (`/aluno/suporte?aula=`), e o gestor recebe o título dela no assunto. Cada aba segue sendo a sua página, no seu endereço (`/aluno/duvidas`, `/aluno/suporte`). Com `mensagensDiretas` desligada não há abas, só as dúvidas.
- **"Aulas do curso" continua aparecendo uma vez por tela**: expansor do menu na página da aula, coluna da direita na página do curso.
- **Um curso concluído em tela** entra na lista de "Meus cursos" como o atual, porque é nele que moram "Aulas do curso" e "Avaliações".
- **Sem item repetido** (ajuste do mesmo dia): com **um** curso em andamento não há grupo "Meus cursos", e o curso aparece direto como item; o grupo só surge com dois ou mais. "Cursos concluídos" só aparece quando há algum concluído. "Continuar estudando" some na própria aula para a qual levaria.
- O menu recolhido empilha o símbolo da escola e o botão de recolher: lado a lado eles não cabem nos 48 px úteis e se sobrepunham.
- `MenuDoAluno` é `scoped` (um por requisição): o menu é desenhado duas vezes na mesma tela, e a matrícula, os cursos e o progresso são buscados uma vez só.

## O que não muda

Sem mudança de schema, de rota nem de regra de negócio: os endereços antigos continuam respondendo, e quem tinha `/aluno/duvidas` ou `/aluno/suporte` guardado não perde nada.

## O que fica de fora (pedidos de decisão futuros)

Selos de pendência no menu, "Agenda", "Secretaria" e "Requisitos do certificado" usam funções que o servidor já tem, mas pedem tela nova; anotações no servidor e notificações pedem tabela nova e dependem de validação (AGENTS.md, "Nunca mude sem sinalizar"). O componente de item de menu já aceita uma `contagem` para os selos.

## Como conferir

- `tests/Feature/MenuDoAlunoTest.php`: cursos no menu, curso atual marcado, curso inativado fora, "Continuar estudando", avaliações no menu e fora da página do curso, rótulos novos e ausência dos antigos, abas, percentual nunca como texto, "Cursos concluídos" como submenu, "Aulas do curso" uma vez, e as duas listas com os mesmos itens.
- e2e: `aluno-painel`, `aluno-avaliacoes`, `aluno-biblioteca-ajuda` e `aula`, nos quatro tamanhos de tela.
