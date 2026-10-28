# ADR 022 — Sessão revogável, bloqueio por rede, cadastro que não revela quem existe e upload público só para a equipe

- **Data:** 24/10/2026
- **Situação:** aceita — o responsável aprovou itens 1, 2 e 3 da lista aberta no ADR 021 e mandou deixar os itens 4 e 5 como estão
- **Afeta:** ADR 11 (identificação), ADR 12 (nada é apagado), ADR 021

> **Nota (27/10/2026):** os nomes de tabela, coluna e rota citados aqui foram atualizados para os de depois da [ADR 024](024-nomes-em-portugues.md); o texto histórico segue como foi decidido.

## Decisões do responsável (24/10)

| # | Item do ADR 021 | Decisão | O que foi feito |
|---|---|---|---|
| 1 | Sessão JWT não revogável | Fazer, com cuidado | Ver "Sessão revogável" |
| 2 | Cadastro revela CPF/e-mail; bloqueio travável por terceiro | Fazer as duas | Ver "Cadastro" e "Bloqueio por rede" |
| 3 | Upload público aberto a aluno | Fazer o sugerido | Ver "Upload" |
| 4 | API sem token CSRF (só `SameSite=Lax`) | Deixar como está | Nada |
| 5 | Instrutor lista todas as solicitações acadêmicas | Deixar como está | Nada (flag segue desligada) |

## Sessão revogável

O JWT valia 12 h e nada o cancelava: "Sair" só apagava o cookie, e trocar a senha não
derrubava quem já tinha o token. Duas peças, ambas aditivas (**mudança de schema aprovada**):

- **`usuario.versao_da_sessao`** (inteiro, padrão 0). O token carrega `sv`, a versão no momento da
  emissão; token com `sv` menor que a do banco é recusado. A versão sobe quando:
  - a pessoa troca a própria senha (a sessão que trocou segue, com token novo no cookie);
  - a coordenação redefine a senha;
  - uma conta bloqueada ou pendente é **reativada** (o token de antes não volta a valer).
- **`SessaoEncerrada`** (`jti`, `usuario_id`, `encerrada_em`; só se acrescenta). O token carrega `jti`;
  "Sair" grava o `jti` e **só aquele aparelho sai** — quem sai do computador do laboratório
  não derruba o próprio celular.

A conferência está em `JwtAuthenticate::decode` (todos os middlewares passam por ela), via
`App\Support\Sessoes`.

**Por que o bloqueio não sobe a versão na hora.** Conta bloqueada ou inativada já é recusada a
cada requisição, com a resposta certa (403 `ACCOUNT_BLOCKED`). Subir a versão trocaria isso por
401 "token inválido", e o ADR 12 registra o que acontece: o cliente tenta renovar a sessão para
sempre. A versão sobe só na reativação, que é quando o token antigo poderia reviver.

**Compatibilidade.** Token emitido antes desta mudança não tem `sv` nem `jti`: vale como versão 0
e continua valendo até a primeira troca de senha ou reativação. **Ninguém é deslogado pelo deploy.**
Esse token antigo não pode ser cancelado por "Sair" (não tem `jti`); expira em no máximo 12 h.

## Bloqueio por rede

Antes, 5 senhas erradas, de qualquer lugar, trancavam a conta por 15 min: quem sabe o CPF de
alguém a trancava à vontade. Agora:

- O contador é por **(identificador, papel, IP)**, no cache. Quem erra de propósito tranca só o
  próprio acesso; o aluno de verdade, em outra rede, entra normalmente. Login certo zera o contador.
- O contador vale também para identificador **que não existe**, para o 429 não revelar quais CPFs
  têm conta.
- A conta só trava para todos após **30 erros somados** de qualquer rede (força bruta distribuída),
  nas colunas que já existiam (`tentativas_login_falhas`, `bloqueado_ate`).
- Os limitadores por rota (`auth-login`: 10 por identificador+IP e 100 por IP, a cada 15 min) seguem.

**Limite conhecido.** Quem controla muitas redes ainda consegue 30 erros e travar a conta por 15 min.
É o preço de ainda proteger contra adivinhação distribuída; o ataque custa 30 tentativas, e não 5 de
um só lugar.

## Cadastro que não revela quem existe

`POST /cadastro` (tela) e `POST /api/autenticacao/cadastro` **público** respondem igual a um cadastro novo
quando o e-mail ou o CPF já existem: nada é gravado, a resposta tem a mesma forma, e o tempo de
resposta não muda (o hash da senha roda mesmo assim). A tela acrescentou "Se você já tem cadastro,
entre com seu CPF e sua senha". A tentativa fica na trilha de auditoria como `WARNING`, para a
coordenação ver. **Admin provisionando conta continua vendo o 409**, porque precisa saber.

A checagem passou a incluir contas inativadas (`withTrashed`): antes, e-mail de conta inativada
passava na checagem e estourava o índice único (erro 500).

## Upload

`POST /api/upload` com `visibility=public` (o padrão) responde **403 para aluno**. A pasta pública
é aberta a qualquer visitante, com o endereço da escola; aluno só envia arquivo privado (a entrega
dele, baixada por `/api/arquivos/{id}` com autorização). Instrutor e admin seguem publicando. Nenhuma
tela do aluno enviava arquivo público.

## Consequências

- `Usuario` ganha uma coluna e há uma tabela nova; a `down()` da migration não remove a coluna (ADR 12).
- Cada requisição autenticada faz mais uma ou duas consultas pequenas (versão da sessão e `jti`).
- Aluno que troca a senha sai dos outros aparelhos — é o efeito pretendido.
- 13 testes novos em `SessaoRevogavelTest` (sessão, bloqueio por rede e cadastro) e 2 em `UploadTest`;
  neste, os testes de pasta pública passaram a usar instrutor (a regra de papel mudou).
  O `RateLimitKeyTest` ganhou as cinco respostas 429 `ACCOUNT_LOCKED` que agora vêm antes do 429 da rota.
- **Segue aberto, por decisão:** API sem token CSRF (itens 4) e solicitações acadêmicas do instrutor (5).
