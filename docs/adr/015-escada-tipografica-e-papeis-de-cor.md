# ADR 15 — Escada tipográfica, papéis de cor, e o painel administrativo fora da unificação

- **Status:** aceita
- **Data:** 10/09/2026
- **Decidida por:** coordenação do AVASEC
- **Relaciona-se com:** o pacote `design_handoff_avasec_navegacao` (Blocos 3, 4, 6
  e 7) e com a [ADR 14](014-certificado-exige-avaliacao.md), cuja regra de
  certificado corrigiu um contador desta rodada.

## Contexto

O handoff de navegação pedia quatro coisas nesta rodada: escada tipográfica
(Bloco 3), papéis de cor e redução de quatro para duas famílias de fonte
(Bloco 4), menus das áreas internas (Bloco 6) e **unificação visual dos três
painéis** (Bloco 7), adotando o padrão do administrador como base e trocando o
azul dele (`#3b82f6`) pelo roxo institucional.

O levantamento no código encontrou:

| Padrão fora do alvo | Ocorrências |
|---|---|
| Texto arbitrário abaixo de 12px (menor: **7,5px**) | 799 |
| `slate-400` / `slate-500` em texto (2,6:1 sobre branco) | 767 |
| Monoespaçada decorativa (`font-mono`) | 228 |
| Caixa alta (`uppercase`) | 662 |

Além disso: **nenhum `<h1>`** no portal público inteiro (o título de cada página
institucional era um `<h3>`), a classe `text-3.5xl` usada em três títulos e
**inexistente no tema** (o Tailwind 4 não gera classe para tamanho não
declarado, então esses títulos ficavam em 24px em qualquer tela, sem erro), e a
Space Grotesk baixada em toda visita **aplicada a zero elementos**.

## Decisão

### 1. Tamanho tem nome de papel, não número

Os tamanhos vivem em `@theme` como `--text-nota` (12px, o piso), `--text-apoio`
(14px), `--text-rotulo` (15px), `--text-corpo` (16px), `--text-cartao` (19px),
`--text-secao` (36px), `--text-pagina` (42px) e `--text-sobretitulo` (14px, com
entreletra e peso próprios).

Nomear o papel é o que impede a escada de ser reinventada por tela: `text-corpo`
não vira 10px sem alguém editar o `index.css`. **Não existe token abaixo de
12px**, de propósito.

A trava está em `tests/frontend/escadaTipografica.test.ts`, que lê o
código-fonte — o defeito é uma classe escrita no JSX, e um teste de render não o
pegaria sem montar todas as telas.

### 2. Duas famílias, e a monoespaçada só onde informa

Playfair Display em título, Inter em todo o resto. `--font-mono` continua
existindo apontando para a monoespaçada **do sistema**, sem webfont, porque o
`<pre>` de código de aula depende de largura fixa para mostrar indentação e
distinguir `l` de `1` de `I`. É a única exceção, e há teste que cai se ela sair
de dentro do `<pre>`.

### 3. Cor tem papel, e o vermelho tem dois tons

| Token | Hex | Papel |
|---|---|---|
| `escult-purple` | `#540D6E` | estrutura, navegação, números |
| `escult-red` | `#EE4266` | **indicador e enfeite — não carrega texto** |
| `escult-red-acao` | `#d62f52` | fundo da ação principal (branco dá 4,79:1) |
| `escult-green` | `#3BCEAC` | confirmação (com tinta escura sobre ela) |
| `escult-yellow` | `#FFD23F` | destaque pontual **sobre fundo escuro** |
| `escult-ink` … `ink-3` | `#1d2432` … `#6b7385` | texto, do principal ao terciário |
| `escult-ink-claro` | `#c3c8d2` | texto **sobre superfície escura** |

O vermelho institucional precisou de dois tons porque branco sobre `#EE4266` dá
**3,75:1**, abaixo do piso de 4,5:1. O `ink-claro` existe porque os cinzas
`ink-*` são calibrados para fundo branco: `ink-2` sobre a seção quase preta dá
2,38:1 — a varredura tipográfica, aplicada sem esse token, **piorava** o
contraste justamente onde o fundo é escuro.

Azul e índigo do Tailwind saíram das áreas pública, do aluno e da gestão (106
classes): não pertencem à paleta e vinham servindo de "cor informativa" por
acidente.

### 4. O painel administrativo fica fora da unificação do Bloco 7

**Decisão da coordenação (10/09/2026):** o ambiente administrativo **não troca
de cor e não perde função**. Pode receber melhoria de navegação e de clareza,
nada mais.

Consequência que este documento existe para registrar: **a unificação visual dos
três painéis não acontece.** O admin mantém a barra lateral `#0F172A` com acento
azul; aluno e gestão seguem no roxo institucional com a escada nova. Quem ler o
Bloco 7 do handoff e não encontrar a troca de azul por roxo deve saber que foi
decisão, e não esquecimento.

O que o admin recebeu nesta rodada: os dez itens planos agrupados em Pessoas /
Ensino / Sistema, subtítulo por seção (havia um só, repetido nas dez telas), e
uma gaveta no lugar do `<select>` que colapsava a barra lateral abaixo de
1024px. Os dez `id` e as dez flags continuam idênticos, com teste que guarda a
lista.

**Pendência declarada:** a tipografia do painel administrativo não foi tocada.
Restam 479 ocorrências de texto abaixo do piso, caixa alta e monoespaçada
decorativa, incluindo o aviso de privacidade em 10px. A contagem está registrada
na trava `escadaTipografica`, que acusa se o número subir.

### 5. Alto contraste é tema de tokens, com um limite escrito

O recurso era uma sobrescrita universal que apagava o logotipo e os ícones
(`svg * { fill: none }`) e zerava o contorno das imagens — quebrava a página
para quem mais depende dela.

Agora os valores vêm de tokens (`--hc-fundo`, `--hc-texto`, `--hc-borda`,
`--hc-acao`, `--hc-foco`), há anel de foco (não havia nenhum) e svg, imagem e
vídeo estão fora da regra ampla, por nome.

O limite, que está no código para não ser redescoberto: o Tailwind 4 emite
`var(--color-*)`, então redefinir token cascateia — mas **o mesmo passo da
paleta serve papéis opostos** neste código (`text-slate-300` é texto em 37
lugares; `bg-slate-900` é fundo em 84), e há 337 cores arbitrárias que não
passam por token nenhum. Por isso a regra ampla continua, agora com exceções
nomeadas. Um remapeamento "puro" da paleta por tom inverteria texto e fundo.

## Consequências

- Contraste medido no navegador, nó de texto por nó de texto: **0 falhas** nas
  nove páginas públicas, no painel do aluno, na tela de certificados e no painel
  do instrutor. As duas ocorrências restantes no painel do instrutor são
  limitação do medidor (fundo em gradiente tem `backgroundColor` transparente) e
  foram conferidas por captura de tela.
- O piso de 12px vale para as superfícies convertidas e é verificado por teste;
  o painel administrativo tem exceção **com contagem registrada**, não silenciosa.
- Quem alterar a paleta muda um lugar. Quem quiser reabrir a unificação do Bloco
  7 precisa de nova decisão da coordenação, porque esta ADR a fechou.

## Alternativas consideradas

- **Aplicar o Bloco 3 e depois o Bloco 4**, na ordem do handoff: editaria os
  mesmos ~1.600 pontos duas vezes, e o Bloco 7 reescreveria de novo a camada
  visual do aluno e da gestão. A execução foi por superfície, cada arquivo
  editado uma vez.
- **Remapear a paleta do Tailwind no alto contraste**, sem regra ampla: descrito
  acima — inverte texto e fundo, porque o tom não diz o papel.
- **Trocar o azul do admin pelo roxo**, como o handoff pede: recusado pela
  coordenação.
