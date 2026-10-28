# ADR 021 — Histórico de versões, prévia ao vivo e as decisões do responsável de 23/10/2026

- **Data:** 23/10/2026
- **Situação:** aceita — decisões dadas pelo responsável em 23/10 sobre as pendências da M5 e da M6
- **Afeta:** ADR 12 (nada é apagado), ADR 018, ADR 019, ADR 020

> **Nota (27/10/2026):** os nomes de tabela, coluna e rota citados aqui foram atualizados para os de depois da [ADR 024](024-nomes-em-portugues.md); o texto histórico segue como foi decidido.

## Contexto

Ao fechar o Admin (M5) e o editor de aula (M6) ficaram pendências que dependiam de
decisão. O responsável respondeu uma a uma; este registro guarda o que foi decidido, o
que foi feito, e o que ficou de fora de propósito.

## Decisões e o que mudou

| # | Decisão | O que foi feito |
|---|---|---|
| 1 | Base de Matrículas do BI **inclui** o curso concorrente (ninguém usava a planilha ainda) | `BasesGerenciais` ganhou linhas `Ativa` com id `<matrícula>-E<n>` |
| 2 | Páginas do Site e Modelos de documento **guardam a versão anterior** | Tabela `VersaoDeConteudo`, serviço `VersoesDeConteudo`, e "Versões anteriores" com "Voltar a esta versão" nas duas telas do admin |
| 3 | O texto de Dúvidas ("Student João Silva…") é **da coordenação** | Nada a fazer em código: corrige-se em Páginas do Site, e agora há histórico |
| 4 | Bloquear conta e cadastrar gestor pelo painel: **fora por ora** | Nada |
| 5 | Redefinir senha mantém o limite de 5 por 15 min | Nada (pode mudar depois) |
| 6 | As cinco configurações sem consumidor **ficam assim** | Nada |
| 7 | Contagem de matrícula conta **tudo** (principal + concorrentes) | Já era assim desde a M5 |
| 8 | Avaliações da M6 (Desafio 1/2) **ficam como estão** | Nada |
| 9 | "Sofia Rocha" não existe | Pendência encerrada |
| 10 | **Corrigir a falha de segurança do gabarito e quaisquer outras** | Ver "Segurança" abaixo |
| 11 | **Sem limite** de tentativas no envio de quiz | Nada |
| 12 | `emite_certificado`: **só o curso** | A convenção "Sem Certificado" na categoria saiu da emissão, do painel do instrutor e da tela. Nenhum curso a usava |
| 13 | Histórico de versões da aula **e** prévia ao vivo | Ver abaixo |
| 14 | `isOptional` não precisa existir, nem no banco | Nada será criado; a referência na tela já dizia isso |

## Histórico de versões (decisões 2 e 13)

- **Uma tabela só**, `VersaoDeConteudo` (`tipo`, `referencia`, `conteudo` em JSON, `origem`,
  `criado_por_usuario_id`, `criado_em`). **Só se acrescenta**: não há UPDATE nem DELETE, e
  restaurar uma versão grava uma linha nova — a do que estava no ar. Voltar atrás de uma
  restauração é possível.
- **Guarda o que ia se perder**, antes de cada gravação que muda o conteúdo. Gravar sem
  mudar nada não cria versão. Para páginas e modelos, a primeira versão é o **texto de
  fábrica**, que antes sumia no primeiro salvamento.
- **Páginas do Site e Modelos:** guardado no `upsert` dos serviços, na mesma transação da
  gravação. Restaurar passa pelo mesmo `upsert` (mesma normalização, mesma regra de papel).
- **Aula:** guardado por um evento do model `Aula` (`updating`), porque a aula é
  gravada por vários caminhos (editor, API do curso, restauração) e um só esquecido
  abriria um buraco. Cobre título, carga, vídeo e texto. **Os anexos não entram**: são
  registros próprios, cuja retirada já é preservada pela regra de nada ser apagado.
- **Quem alterou** vem do usuário da requisição; a versão mostra quem a **substituiu**.
- A versão só restaura **no item a que pertence**: o id de outra página ou de outra aula
  responde 404 e nada muda.
- **Mudança de schema** (tabela nova, só aditiva) aprovada pelo responsável ao decidir o item 2.

## Prévia ao vivo (decisão 13)

Ao editar um trecho do material, o formulário mostra "Prévia — como o aluno vai ver",
atualizada ao digitar (espera 350 ms). Quem monta a prévia é o **servidor**
(`AreaDoInstrutorController::previa`), com o mesmo texto gravado, a mesma regra de
`MaterialDaAula` e o mesmo componente que a aula do aluno usa; não grava nada. Por isso a
prévia **não tem regra de formatação própria** — se o aluno vê diferente, o componente
mudou e a prévia muda junto. Texto digitado é escapado. Regra quebrada responde 422 com a
mesma mensagem que o "Aplicar" mostraria.

Limite conhecido: imagem escolhida no computador e ainda não enviada **não aparece** na
prévia (ela só existe depois do "Aplicar"); a prévia mostra a imagem que o trecho já tem.
Trecho novo de imagem ou vídeo também não tem prévia, porque ainda não existe no texto.

## Segurança (decisão 10)

Varredura feita em 23/10/2026. Achados confirmados e corrigidos, cada um com teste:

1. **`GET /api/avaliacoes` entregava o gabarito ao aluno** (`indice_opcao_correta`, `explanation`,
   `mensagem_revisao`): quem chamasse a API à mão tirava 100% em qualquer prova do seu curso.
   O aluno agora recebe só enunciados e alternativas, e não vê prova em rascunho ou
   restrita. Instrutor e admin seguem recebendo tudo.
2. **`GET /api/biblioteca` e `GET /api/webinars` (públicos) ignoravam a escada de visibilidade**:
   um visitante anônimo lia item restrito (gestor/admin) e rascunho (admin). Agora valem
   `Visibilidade` e o papel da sessão, se houver (`jwt.optional`).
3. **`PUT /api/matriculas/{usuario_id}`: instrutor dono de um curso podia, na mesma chamada,
   marcar qualquer curso como concluído para qualquer aluno, mexer na penalidade de evasão
   e tirar o aluno do curso de outro instrutor.** Conclusão e penalidade passaram a ser do
   Admin Superior; e só o dono do curso atual de um aluno o muda de curso.
4. **As telas não tinham cabeçalhos de segurança** quando servidas sem Nginx na frente
   (só a API tinha). Entraram `X-Content-Type-Options: nosniff` e `X-Frame-Options: SAMEORIGIN`
   em toda resposta. O `Referrer-Policy` ficou de fora de propósito: o player do YouTube
   precisa do referenciador, e a política é do Nginx.

5. **XSS refletido em `/entrar?papel=`**: o valor ia cru para dentro de um atributo do Alpine
   (`?papel=');alert(1)//` virava JavaScript na página da senha). Agora só entram `student`,
   `instructor` e `admin`, e a view usa `@js`.
6. **Instrutor sequestrava aula, documento e encontro ao vivo de outro curso** pelo `PUT
   /api/cursos/{id}`: os ids saem no catálogo público, e o `syncLessons` achava o registro em
   qualquer curso e o movia para o dele, sobrescrevendo o texto. Id que já é de outro curso
   vira registro novo.
7. **Certificado forjável por aluno sem matrícula**: `POST /api/progresso` aceitava qualquer
   `curso_id` com os ids de aula do catálogo, e `POST /api/certificados` emitia com 100%.
   Agora o progresso e o certificado exigem que o aluno pertença ao curso; as propriedades do
   componente Livewire de conclusão ficaram `#[Locked]`.
8. **A consulta pública de certificado enumerava alunos**: o nome casava por `LIKE %termo%`
   (`q=Ma`, ou só `%`, devolvia nome, curso e código). Agora o nome tem de ser o completo, igual.
9. **Token de admin rebaixado ou bloqueado continuava provisionando administradores por até
   12 h** (`jwt.optional` confiava no papel do token). Agora o papel e o estado vêm do banco; conta
   que não vale mais é visitante.
10. **Injeção de fórmula em CSV**: nome de aluno como `=HYPERLINK(...)` executava no Excel do
    administrador. Texto que começa com `= + - @` ou tabulação sai com apóstrofo na frente.
11. **A API de matrícula aceitava curso inexistente, em rascunho ou restrito**, e isso dava ao aluno
    acesso à prova (e à nota) de um curso não publicado. `selfEnroll` e `createAdmission` agora
    pedem curso visível ao aluno; `submitQuiz` não aceita prova que o aluno não enxerga.
12. **Fluxos hoje desligados por flag, mas com IDOR**: curtir mensagem de fórum de outro curso
    (devolvia a mensagem inteira), e entregar exercício de curso alheio ou com `url_arquivo` em
    `javascript:`. Corrigidos antes de a flag ligar.
13. **`id` de curso livre** (`%` ampliava o escopo de alunos do instrutor nas consultas `LIKE`):
    só letras, números, `_` e `-`.
14. **A senha digitada no campo do CPF ficava gravada na trilha de auditoria**, que é permanente.
    O log agora guarda e-mail, CPF mascarado, ou só "fora do formato".
15. **Conta bloqueada trocava a própria senha** com token ainda válido: `PUT /api/autenticacao/senha`
    passou a exigir conta ativa.

Verificado sem achado: toda rota de escrita da API exige sessão e papel; `DELETE
/api/logs-seguranca` é recusado (ADR 12); o segredo do JWT é validado em produção; não há
SQL bruto com dado de entrada nem `{!! !!}` com dado de usuário; listagens de aluno (matrícula,
progresso, certificado, mensagens, fórum) só devolvem o próprio; upload valida tipo e conteúdo.

### O que ficou de fora, para decisão do responsável

- **Sessão JWT não revogável** (logout só apaga o cookie; trocar a senha não invalida tokens de 12 h)
  — exige coluna nova e mexe no fluxo de autenticação.
- **Cadastro público confirma quais CPFs e e-mails existem (409)** e o bloqueio por 5 senhas erradas
  permite travar a conta de quem se conhece o CPF — mudar é mudar a experiência do cadastro e do login.
- **Aluno pode publicar arquivo em `/uploads/`** (upload público aberto a qualquer papel) — os testes
  atuais dependem disso; restringir é decisão de produto.
- **API com cookie e sem token CSRF** (só `SameSite=Lax`).
- **Oráculo de gabarito por tentativas ilimitadas**: a correção por questão mais o reenvio sem limite
  deixam descobrir a alternativa certa. O responsável decidiu (23/10) que **não haverá limite**; fica
  registrado como risco aceito.
- **Instrutor lista todas as solicitações acadêmicas** (regra "secretaria centralizada" documentada),
  hoje com a flag desligada.
## Consequências

- O histórico cresce sem limite no banco (a tela mostra as 30 mais recentes). Não há política
  de retenção, e a ADR 12 não a permitiria sem decisão própria.
- A API de quizzes mudou para o aluno (campos a menos). O único cliente dela era o React,
  removido na M7.
- A convenção "Sem Certificado" na categoria deixou de impedir a emissão. Quem a usar por
  engano precisa marcar o curso como "não emite certificado".
