# ADR 16 — Aprovação automática de matrícula, e a trava que faltava nos dois caminhos

- **Status:** aceita
- **Data:** 14/09/2026
- **Decidida por:** coordenação do AVASEC
- **Relaciona-se com:** a [ADR 12](012-nada-e-apagado.md) (o pedido retirado foi
  inativado, não apagado) e com a regra de matrícula única já validada.

## Contexto

A coordenação decidiu que, nesta fase, a matrícula em disciplina **não passa por
aprovação humana**: o aluno pede e o acesso é liberado no ato.

O levantamento no código encontrou um problema que a decisão transformaria em
dano. Havia **dois caminhos para matricular**, e apenas um tinha regra:

| Caminho | Travas |
|---|---|
| `selfEnroll` — o aluno se inscreve | curso existe · sem restrição por cancelamento · não está já matriculado · **não concluiu o curso** · não tem outra matrícula ativa |
| `updateAdmissionStatus` — o botão "Aprovar Acesso" | **nenhuma** |

O segundo sobrescrevia `enrolledCourseId` direto. Enquanto uma pessoa lia cada
pedido, a assimetria passava despercebida — quem aprovava via o nome do aluno e
o curso, e decidia. Automatizar sem unificar tornaria isso silencioso.

O caso concreto estava no banco: **João Silva, matriculado em Full-Stack
(`course-2`), com pedido pendente para Metodologias Ágeis (`course-3`) e
`canMultiEnroll = 0`.** Aprovar — automaticamente ou não — trocaria o curso
ativo dele, deixando para trás progresso e frequência, sem aviso e sem registro
de quem decidiu.

## Decisão

### 1. A regra vive no serviço, não em esconder o botão

`EnrollmentService::createAdmission` consulta
`features.aprovacaoAutomaticaMatricula`. Ligada, a solicitação nasce `approved`
e a matrícula é efetivada **na mesma transação** — "aprovada" sem matricular é
um estado que não pode existir, porque o aluno veria acesso liberado na tela sem
registro nenhum por trás.

Esconder apenas a fila na interface foi recusado: os pedidos continuariam
nascendo `pending` e ficariam presos para sempre, já que a tela que os resolvia
deixou de ser exibida.

### 2. Automática quer dizer "sem espera humana", não "sem regra"

As cinco travas passaram a viver em `modoDaNovaMatricula()`, usada pelos **dois**
caminhos. Quem já tem curso ativo, já concluiu aquele curso ou está em restrição
por cancelamento é recusado — agora **na hora e com a razão escrita**, em vez de
esperar por uma aprovação que ninguém mais vai dar.

A recusa não cria solicitação: um pedido `pending` numa fila que não é mais
exibida é um registro órfão.

### 3. A interface diz a regra que vale

O aviso "Matrículas pendentes aguardam sua aprovação técnica" passou a ser
condicional — com a automação ligada ele afirmaria algo falso e mandaria o
professor procurar uma fila que não enche mais. No lugar: "As matrículas são
aprovadas automaticamente: o acesso é liberado no ato do pedido."

A fila "A fazer" consulta a **regra**, não a lista. Isso não é preciosismo:
`LMSContext` semeia três solicitações pendentes embutidas no código (Lucas
Santana, Carolina Mendes, Ana Souza), de alunos que não existem no banco — sem a
trava, a fila anuncia trabalho fictício sempre que a hidratação não sobrescrever
o estado inicial. O mesmo vale para os exercícios práticos, desligados no mesmo
dia pela mesma razão.

Nenhuma função saiu do painel: o Diretório Global de Alunos, que matricula
diretamente, continua.

## Consequências

- Voltar atrás é trocar `false` por `true` nos dois espelhos
  (`src/config/features.ts` e `backend-laravel/config/features.php`), e a fila de
  aprovação reaparece com o aviso antigo.
- O pedido pendente do João Silva foi **inativado** com motivo registrado
  (ADR 12), e ele permanece no Full-Stack. A linha continua no banco.
- Cobertura: 4 testes de backend ligam a flag explicitamente nos dois valores —
  um teste que passasse só por a flag estar ligada hoje não diria nada sobre o
  dia em que alguém a desligasse. No frontend, 3 testes cobrem a fila.
- Verificado no navegador com a resposta de `/api/admissions` interceptada
  devolvendo duas matrículas pendentes: o app as recebeu no estado e a interface
  não as exibiu. A ausência foi provada **com o dado presente**, não com a lista
  vazia.

## Alternativas consideradas

- **Esconder a fila na interface e deixar o backend como está**: descrito acima
  — os pedidos ficariam presos em `pending` sem ninguém para resolvê-los.
- **Aprovar trocando o curso ativo**: recusado pela coordenação. O aluno perderia
  a matrícula corrente só por clicar em outro curso.
- **Manter uma fila só para quem já tem curso ativo**: recusado — devolveria ao
  professor uma fila rara, que enche a ponto de ser esquecida e não a ponto de
  ser hábito.
