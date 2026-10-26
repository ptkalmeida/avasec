# ADR 019 — O painel administrativo passa a ser do servidor (Blade), no padrão do Anexo D

- **Data:** 22/10/2026
- **Situação:** aceita — fase M5 do plano 16; ordem e visual confirmados pelo
  responsável em 22/10 ("visual: seguir as skills obrigatórias", "trocar senha:
  mesma regra do aluno", "ordem pode manter")
- **Afeta:** ADR 017 (MPA em vez de SPA), ADR 018 (editor de aula), ADR 12 (nada é apagado)

## Contexto

O painel do administrador era o último pedaço da aplicação em React: um componente
de ~5.000 linhas (`AdminDashboard.tsx`), mais `SiteContentPanel` e `CapaDoCurso`. Era
também o único item com exigência **vinculante** da TI: o Anexo D (Design System
SECEC), Seção 1, obriga todo back-office a ter sidebar escura, topbar com menu do
usuário (Meu perfil, Trocar senha, Sair), sidebar off-canvas até 991 px, paleta e
tipografia próprias e ícones FontAwesome.

O levantamento mostrou o que já se sabia de outras áreas, em escala maior: **boa parte
do que a tela mostrava era montada ou inventada no navegador**, sem ligação com o banco.

## Decisão

1. **Layout próprio do back-office** (`x-layouts.admin`, `resources/css/admin.css`),
   conforme o Anexo D §1: sidebar `#0F172A`, azul `#3B82F6`, fonte do sistema (sem
   webfont), FontAwesome 7 empacotado pelo Vite (sem CDN). Não reaproveita os tokens do
   portal do aluno e do instrutor — a norma pede que o admin se pareça com um painel
   administrativo, e não com mais uma página do portal.
2. **Migração em oito fatias**, cada uma com testes, gates e push: moldura; Dashboard e
   relatórios; Equipe; Cursos; Alunos; Configurações e Modelos; Páginas do Site;
   Dados Gerenciais; e a virada.
3. **Toda escrita passa pelo mesmo service da API**, com a mesma auditoria — nenhuma
   regra de negócio nasceu na tela.
4. **A virada:** `/admin` vira prefixo em `rotas-laravel.json`. Até as seções desligadas
   por flag e o endereço desconhecido são do servidor (a regra do ADR 018): nenhum
   endereço de `/admin` serve o React.

## O que veio, e o que NÃO veio (e por quê)

Regra seguida: "tirar do lugar sem tirar a função". Uma função que **não funcionava**
(só mudava o estado do navegador) não foi portada como se funcionasse.

| No painel React | No painel Blade | Por quê |
|---|---|---|
| Alunos: RA, turma, polo, "último acesso", "Ausentes > 7d", "Risco de evasão" | Não vieram | O banco não guarda nada disso. O RA saía de uma conta sobre as letras do nome; o risco era calculado lendo o texto "Hoje, às 10:15", fixo. Todo aluno sem matrícula virava "Crítico" |
| Alunos: status da matrícula, status da conta, progresso manual e pendências "editáveis" | Não vieram | Eram `useState`: escolher "Bloqueada" não bloqueava ninguém e recarregar desfazia tudo |
| Alunos: "Enviar notificação" e "Copiar link mágico" | Não vieram | A notificação só mostrava o aviso de sucesso; o link tinha um token escrito no código |
| Cursos: "Alunos ativos" (2 a 4 por curso) | Matrículas reais | Eram escolhidos por uma conta sobre as letras do id do curso |
| Cursos: "áreas de atuação" | Texto livre com sugestões | Ficavam no armazenamento do navegador de quem as criava |
| Cursos: "Excluir" | "Retirar do ar" | A rota nunca apagou (ADR 12): inativa |
| Alunos: "Excluir" | "Retirar da plataforma" | Idem: inativa a conta, preserva matrículas e certificados |
| Equipe: cartão "Especialidade Principal" | Não veio | Texto fixo, igual para qualquer gestor |
| Configurações: controle de presença mínima (50–90%) | Não veio | `useState` que nada gravava; a frequência de verdade é por curso |
| Configurações: "Nota da Assessoria de T.I." | Não veio | Falava em "bancos na memória do navegador" |
| Dados Gerenciais: prévia | Mesma fonte do arquivo | A prévia usava valores de reserva ("São Paulo", "ALU_001") que o arquivo nunca teve |
| Matricular aluno: município/UF/área em branco | Ficam em branco | O painel gravava "São Paulo", "SP", "Tecnologia" como se fossem do aluno |

## O que muda para quem usa (e precisa de validação humana)

- **Trocar senha e Meu perfil** são funções **novas** (o painel React não tinha), e tocam
  autenticação: senha atual conferida no servidor, regra de força, limitador de 5 por
  15 min e auditoria — a mesma do aluno. Validado pelo responsável em 22/10.
- **Matrícula passa a contar o curso principal e os concorrentes** (`extraCourseIds`), como
  o painel do instrutor. O React olhava só o principal, e uma aluna matriculada apenas num
  curso concorrente aparecia com zero.
- **Redefinir senha de aluno** herda o limitador `auth-password` da rota de API
  (5 por 15 min por administrador). Quem redefine várias senhas em sequência vai esbarrar.
- **Configurações:** cinco das seis chaves ficam registradas, mas **nenhuma tela as
  consulta** (só `allowDirectMessages` muda o comportamento). A tela avisa. O valor antigo
  `12 meses` do arquivamento continua ofertado, para salvar não trocá-lo sem aviso.
- **A senha gerada** (matrícula ou redefinição) aparece uma vez, por mensagem de sessão, e
  não é guardada.

## Consequências e pendências (decisão do responsável)

- **Base de Matrículas (BI) ignora curso concorrente.** Preservado de propósito: mudar
  altera uma planilha que alguém já consome. Hoje há um caso (João Silva).
- **Salvar Páginas do Site substitui o texto publicado:** não há histórico de versões.
  O mesmo vale para os Modelos de documento.
- **Conteúdo público com termo de demonstração:** a página de Dúvidas diz "selecionar o
  perfil (Student João Silva ou Ana Souza para simular o LMS)". É texto de conteúdo, não de
  código — corrigir é com a coordenação, pela tela de Páginas do Site.
- **Bloquear conta pelo administrador** existe na API (`PUT /users/{id}/status`) e nunca
  teve tela de verdade. Seria função nova, ligada a autenticação.
- **Cadastro de gestor** existia no código do React, mas nenhuma tela o chamava.
- **O código React do painel** (`AdminDashboard.tsx` e filhos) fica no repositório, sem
  rota que o alcance, até a M7 — que é irreversível e exige checkpoint humano.
