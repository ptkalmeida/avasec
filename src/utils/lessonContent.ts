/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Parser do conteúdo de aula. O instrutor escreve em Markdown reduzido:
 *
 *   ## Seção        -> seção principal (entra no índice, ganha banner numerado)
 *   ### Subtítulo   -> subtítulo (entra no índice, recuado)
 *   **negrito**     -> negrito
 *   1. item         -> lista numerada
 *   - item          -> lista com marcador
 *   ```java         -> bloco de código (a linguagem é opcional)
 *   ![descrição](/uploads/x.png) -> imagem (sozinha na linha)
 *   @[título](https://youtu.be/x) -> vídeo embutido (sozinho na linha)
 *   Figura 1: algo  -> legenda, quando imediatamente antes de código, imagem ou vídeo
 *   {centro grande} texto -> parágrafo com alinhamento e/ou tamanho
 *   ### {direita} Subtítulo -> subtítulo alinhado (tamanho do subtítulo é fixo)
 *   ```
 *
 * Não é Markdown completo — é este subconjunto, e nada aqui vira HTML: a
 * renderização monta nós React, então conteúdo autorado não injeta marcação.
 *
 * Cada bloco carrega o trecho do texto original que o gerou (`range`). É isso
 * que permite ao editor trocar UM bloco fazendo splice no texto: o resto do
 * conteúdo continua idêntico byte a byte, sem passar por reserialização.
 */

/** Posição do bloco no texto original: [start, end) em índices de caractere. */
export interface LessonBlockRange {
  start: number;
  end: number;
}

/*
 * Formatação escolhida pelo autor (decisão humana de 28/09/2026, que reverte em
 * parte a de 22/09: a fonte segue fixa, tamanho e alinhamento passam a ser dele).
 *
 * Os valores são uma lista FECHADA, e quem os traduz em aparência é o render, com
 * as classes do design system. O texto nunca carrega estilo, então nada aqui vira
 * HTML nem CSS — o motivo de segurança da decisão de 22/09 continua de pé.
 *
 * O valor padrão não é gravado: parágrafo sem formatação continua sendo o mesmo
 * texto de antes, byte a byte, e as aulas já publicadas não mudam de leitura.
 */
export const TAMANHOS_TEXTO = ['pequeno', 'normal', 'grande', 'muito-grande'] as const;
export type TamanhoTexto = (typeof TAMANHOS_TEXTO)[number];

export const ALINHAMENTOS_TEXTO = ['esquerda', 'centro', 'direita', 'justificado'] as const;
export type AlinhamentoTexto = (typeof ALINHAMENTOS_TEXTO)[number];

export const TAMANHO_PADRAO: TamanhoTexto = 'normal';
export const ALINHAMENTO_PADRAO: AlinhamentoTexto = 'esquerda';

export type LessonBlock =
  | { kind: 'section'; id: string; text: string; index: number; range: LessonBlockRange }
  // Ausente = padrão. Opcional para o bloco sem formatação continuar com a forma
  // de sempre, inclusive nos testes que comparam blocos.
  | { kind: 'subsection'; id: string; text: string; alinhamento?: AlinhamentoTexto; range: LessonBlockRange }
  | { kind: 'paragraph'; text: string; tamanho?: TamanhoTexto; alinhamento?: AlinhamentoTexto; range: LessonBlockRange }
  | { kind: 'orderedList'; items: string[]; range: LessonBlockRange }
  | { kind: 'bulletList'; items: string[]; range: LessonBlockRange }
  | { kind: 'code'; language: string | null; caption: string | null; code: string; range: LessonBlockRange }
  // `alt` é string, nunca null: o atributo é obrigatório no HTML e '' tem sentido
  // próprio ("imagem decorativa"). Quem escreve é barrado no editor; a leitura é
  // permissiva para o aluno nunca ver a marcação crua por descuido de autoria.
  | { kind: 'image'; url: string; alt: string; caption: string | null; range: LessonBlockRange }
  | { kind: 'video'; url: string; title: string; caption: string | null; range: LessonBlockRange };

export interface LessonSection {
  id: string;
  text: string;
  level: 2 | 3;
  /** Número da seção principal; subtítulos não numeram. */
  index?: number;
}

export interface ParsedLesson {
  blocks: LessonBlock[];
  sections: LessonSection[];
  hasCode: boolean;
}

/** Slug estável para ancorar o índice no título. */
const slug = (text: string, fallback: number): string => {
  const base = text
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

  return base === '' ? `secao-${fallback}` : base;
};

/** Reconhece "Código 3: descrição" / "Figura 2 - descrição" como legenda. */
const CAPTION = /^(?:c[óo]digo|figura|imagem|gr[áa]fico|v[íi]deo|tabela|quadro|exemplo)\s*\d*\s*[:.–-]\s*.+/i;

/**
 * A linha serve de legenda? Exportado para o editor avisar quem digita uma legenda
 * fora do padrão — que na releitura viraria parágrafo solto, não legenda.
 * A regex em si não sai daqui: quem a conhece é o parser.
 */
export function ehLegenda(texto: string): boolean {
  return CAPTION.test(texto.trim());
}

/**
 * Mídia sozinha na linha. O primeiro caractere separa as duas famílias — `!` para
 * imagem (Markdown padrão) e `@` para vídeo — então nenhuma das regex precisa saber
 * da outra.
 *
 * A URL recusa espaço e parêntese de propósito: `UploadService` gera nomes
 * "<timestamp>-<16 hex>.<ext>", nunca reaproveita o nome do cliente, e um endereço
 * com espaço quebraria a releitura do que foi gravado. Linha que não casa segue
 * virando parágrafo — falha visível é melhor que figura quebrada em silêncio.
 */
const IMAGE = /^!\[([^\]]*)\]\(([^()\s]+)\)$/;
const VIDEO = /^@\[([^\]]*)\]\(([^()\s]+)\)$/;

/** `{centro grande} texto`: marcador no início, separado do texto por espaço. */
const FORMATO = /^\{([a-z -]+)\}\s+(\S.*)$/;

interface Formato {
  tamanho?: TamanhoTexto;
  alinhamento?: AlinhamentoTexto;
}

const ehTamanho = (v: string): v is TamanhoTexto => (TAMANHOS_TEXTO as readonly string[]).includes(v);
const ehAlinhamento = (v: string): v is AlinhamentoTexto => (ALINHAMENTOS_TEXTO as readonly string[]).includes(v);

/**
 * Lê o marcador de formatação do começo da linha. Devolve null — e a linha segue
 * como texto comum, com as chaves à vista — quando o marcador não é válido:
 * palavra desconhecida, dois tamanhos, dois alinhamentos, ou tamanho num lugar
 * que não aceita tamanho. Falha visível é melhor que formatação adivinhada.
 */
function lerFormato(linha: string, aceitaTamanho: boolean): (Formato & { texto: string }) | null {
  const m = linha.match(FORMATO);
  if (!m) return null;

  const formato: Formato = {};
  for (const palavra of m[1].split(' ').filter((p) => p !== '')) {
    if (ehTamanho(palavra) && aceitaTamanho && formato.tamanho === undefined) {
      formato.tamanho = palavra;
    } else if (ehAlinhamento(palavra) && formato.alinhamento === undefined) {
      formato.alinhamento = palavra;
    } else {
      return null;
    }
  }

  // O padrão não é gravado; lido, some, para o bloco ter uma forma só.
  if (formato.tamanho === TAMANHO_PADRAO) delete formato.tamanho;
  if (formato.alinhamento === ALINHAMENTO_PADRAO) delete formato.alinhamento;

  return { ...formato, texto: m[2] };
}

/** Marcador a gravar na frente do texto; vazio quando tudo é o padrão. */
function marcadorDeFormato({ tamanho, alinhamento }: Formato): string {
  const palavras = [
    alinhamento !== undefined && alinhamento !== ALINHAMENTO_PADRAO ? alinhamento : null,
    tamanho !== undefined && tamanho !== TAMANHO_PADRAO ? tamanho : null,
  ].filter((p): p is AlinhamentoTexto | TamanhoTexto => p !== null);

  return palavras.length === 0 ? '' : `{${palavras.join(' ')}} `;
}

export function parseLessonContent(content: string): ParsedLesson {
  const blocks: LessonBlock[] = [];
  const sections: LessonSection[] = [];
  const source = content ?? '';
  const lines = source.split('\n');

  // Offsets de cada linha no texto original, para montar os ranges.
  const lineStart: number[] = [];
  let cursor = 0;
  for (const line of lines) {
    lineStart.push(cursor);
    cursor += line.length + 1; // +1 = o '\n' consumido pelo split
  }
  const lineEnd = (i: number): number => lineStart[i] + lines[i].length;

  let ordered: string[] = [];
  let bullets: string[] = [];
  let listStartLine = 0;
  let listEndLine = 0;
  let sectionCount = 0;
  let anchorCount = 0;
  let hasCode = false;

  const flushLists = () => {
    const range = { start: lineStart[listStartLine], end: lineEnd(listEndLine) };
    if (ordered.length > 0) {
      blocks.push({ kind: 'orderedList', items: ordered, range });
      ordered = [];
    }
    if (bullets.length > 0) {
      blocks.push({ kind: 'bulletList', items: bullets, range });
      bullets = [];
    }
  };

  /** Última legenda vista, para colar no próximo bloco de código. */
  let pendingCaption: string | null = null;
  let pendingCaptionLine = 0;

  const takeCaption = (): string | null => {
    const caption = pendingCaption;
    pendingCaption = null;

    return caption;
  };

  const flushCaptionAsParagraph = () => {
    if (pendingCaption !== null) {
      blocks.push({
        kind: 'paragraph',
        text: pendingCaption,
        range: { start: lineStart[pendingCaptionLine], end: lineEnd(pendingCaptionLine) },
      });
      pendingCaption = null;
    }
  };

  for (let i = 0; i < lines.length; i++) {
    const raw = lines[i];
    const line = raw.trim();

    // ---- bloco de código: consome até a cerca de fechamento ----
    const fence = line.match(/^```\s*([A-Za-z0-9+#-]*)\s*$/);
    if (fence) {
      flushLists();
      const language = fence[1] === '' ? null : fence[1].toLowerCase();
      // A legenda faz parte deste bloco: editar o código edita a legenda junto.
      const blockStart = pendingCaption !== null ? lineStart[pendingCaptionLine] : lineStart[i];
      const body: string[] = [];
      i++;
      while (i < lines.length && lines[i].trim() !== '```') {
        body.push(lines[i]);
        i++;
      }
      // Remove linhas vazias nas pontas sem tocar na indentação do código.
      while (body.length > 0 && body[0].trim() === '') body.shift();
      while (body.length > 0 && body[body.length - 1].trim() === '') body.pop();

      blocks.push({
        kind: 'code',
        language,
        caption: takeCaption(),
        code: body.join('\n'),
        // i aponta para a cerca final (ou para o fim do texto, se ela faltar).
        range: { start: blockStart, end: lineEnd(Math.min(i, lines.length - 1)) },
      });
      hasCode = true;
      continue;
    }

    if (line === '') {
      flushLists();
      continue;
    }

    const image = line.match(IMAGE);
    const video = line.match(VIDEO);
    const section = line.match(/^##\s+(?!#)(.*)$/);
    const subsection = line.match(/^###\s+(.*)$/);
    const numbered = line.match(/^\d+\.\s+(.*)$/);
    const bullet = line.match(/^[-*]\s+(.*)$/);
    const range = { start: lineStart[i], end: lineEnd(i) };

    // A legenda pendente pertence à mídia, como já acontece no bloco de código:
    // o range engloba as duas linhas, então o lápis e a lixeira levam o par junto.
    const rangeComLegenda = pendingCaption !== null
      ? { start: lineStart[pendingCaptionLine], end: lineEnd(i) }
      : range;

    if (image) {
      flushLists();
      blocks.push({
        kind: 'image',
        url: image[2],
        alt: image[1],
        caption: takeCaption(),
        range: rangeComLegenda,
      });
    } else if (video) {
      flushLists();
      blocks.push({
        kind: 'video',
        url: video[2],
        title: video[1],
        caption: takeCaption(),
        range: rangeComLegenda,
      });
    } else if (section) {
      flushLists();
      flushCaptionAsParagraph();
      sectionCount++;
      const id = slug(section[1], anchorCount++);
      blocks.push({ kind: 'section', id, text: section[1], index: sectionCount, range });
      sections.push({ id, text: section[1], level: 2, index: sectionCount });
    } else if (subsection) {
      flushLists();
      flushCaptionAsParagraph();
      // Subtítulo aceita só alinhamento: o tamanho é o que o distingue do
      // parágrafo na hierarquia da aula. O índice e a âncora usam o texto limpo.
      const formato = lerFormato(subsection[1], false);
      const text = formato?.texto ?? subsection[1];
      const id = slug(text, anchorCount++);
      blocks.push({
        kind: 'subsection',
        id,
        text,
        ...(formato?.alinhamento !== undefined && { alinhamento: formato.alinhamento }),
        range,
      });
      sections.push({ id, text, level: 3 });
    } else if (numbered) {
      flushCaptionAsParagraph();
      if (bullets.length > 0) flushLists();
      if (ordered.length === 0) listStartLine = i;
      listEndLine = i;
      ordered.push(numbered[1]);
    } else if (bullet) {
      flushCaptionAsParagraph();
      if (ordered.length > 0) flushLists();
      if (bullets.length === 0) listStartLine = i;
      listEndLine = i;
      bullets.push(bullet[1]);
    } else {
      flushLists();
      const formato = lerFormato(line, true);
      // Uma legenda só vale como legenda se um bloco de código vier depois;
      // senão ela cai como parágrafo normal na próxima decisão. Linha formatada
      // é parágrafo por escolha do autor — nunca é lida como legenda.
      if (formato !== null) {
        flushCaptionAsParagraph();
        const { texto, ...estilo } = formato;
        blocks.push({ kind: 'paragraph', text: texto, ...estilo, range });
      } else if (CAPTION.test(line)) {
        flushCaptionAsParagraph();
        pendingCaption = line;
        pendingCaptionLine = i;
      } else {
        flushCaptionAsParagraph();
        blocks.push({ kind: 'paragraph', text: line, range });
      }
    }
  }

  flushLists();
  flushCaptionAsParagraph();

  return { blocks, sections, hasCode };
}

/**
 * Converte um bloco de volta para o texto que o gerou. Usado para gravar a
 * edição de UM bloco: o texto novo entra no lugar do `range`, e nada mais no
 * conteúdo é tocado.
 */
export function serializeLessonBlock(block: LessonBlock): string {
  switch (block.kind) {
    case 'section':
      return `## ${block.text}`;
    case 'subsection':
      return `### ${marcadorDeFormato({ alinhamento: block.alinhamento })}${block.text}`;
    case 'paragraph': {
      // Cada linha do texto vira um parágrafo na releitura, então o marcador vai
      // em todas — senão só a primeira sairia formatada.
      const marcador = marcadorDeFormato(block);

      return block.text.split('\n').map((linha) => `${marcador}${linha}`).join('\n');
    }
    case 'orderedList':
      return block.items.map((item, i) => `${i + 1}. ${item}`).join('\n');
    case 'bulletList':
      return block.items.map((item) => `- ${item}`).join('\n');
    case 'code': {
      const cerca = '```';
      const abertura = `${cerca}${block.language ?? ''}`;
      const corpo = [abertura, block.code, cerca].join('\n');

      return block.caption === null ? corpo : `${block.caption}\n${corpo}`;
    }
    case 'image':
      return comLegenda(block.caption, `![${textoDeUmaLinha(block.alt)}](${block.url})`);
    case 'video':
      return comLegenda(block.caption, `@[${textoDeUmaLinha(block.title)}](${block.url})`);
  }
}

/**
 * Colchete e quebra de linha na descrição impediriam a releitura do que foi
 * gravado. O saneamento mora aqui, único ponto por onde todo texto passa, para a
 * idempotência do ciclo parse -> serialize -> parse não depender de o chamador
 * lembrar de limpar. O editor valida de novo, mas só para poder explicar o
 * problema a quem escreve em vez de alterar o texto pelas costas.
 */
const textoDeUmaLinha = (texto: string): string =>
  // Os espaços são colapsados depois da troca: "Figura [1]" viraria "Figura  1 ",
  // com o buraco de cada colchete virando um espaço solto.
  texto.replace(/[[\]\r\n]+/g, ' ').replace(/\s+/g, ' ').trim();

const comLegenda = (caption: string | null, corpo: string): string =>
  caption === null ? corpo : `${caption}\n${corpo}`;

/** Troca o trecho do bloco pelo texto novo, preservando o resto do conteúdo. */
export function replaceLessonBlock(content: string, range: LessonBlockRange, replacement: string): string {
  return content.slice(0, range.start) + replacement + content.slice(range.end);
}

/**
 * Remove o bloco e as linhas em branco que sobrariam grudadas, para a exclusão
 * não deixar buracos de espaçamento no meio da aula.
 */
export function removeLessonBlock(content: string, range: LessonBlockRange): string {
  const antes = content.slice(0, range.start).replace(/\n{2,}$/, '\n\n');
  const depois = content.slice(range.end).replace(/^\n{2,}/, '');

  return `${antes}${depois}`.replace(/^\n+/, '').replace(/\n{3,}/g, '\n\n').trimEnd();
}

/**
 * Insere um bloco novo na posição indicada — índice de caractere no conteúdo,
 * normalmente `range.end` do bloco que deve ficar acima, ou 0 para o começo.
 *
 * Recebe posição em vez de um bloco de referência de propósito. A versão anterior
 * aceitava `null` como sentinela e os dois lados da fronteira o liam ao contrário:
 * o editor mandava `null` querendo dizer "no começo" e esta função entendia "no
 * fim", de modo que o trecho escolhido no topo da aula ia parar no rodapé. Sem
 * sentinela não há como discordarem.
 *
 * A posição é limitada ao tamanho do conteúdo: índice inválido não estoura nem
 * cria buraco no texto.
 */
export function insertLessonBlockAt(content: string, posicao: number, texto: string): string {
  if (content.trim() === '') return texto;

  const limite = Math.min(Math.max(Math.trunc(posicao), 0), content.length);

  // As quebras na emenda são refeitas em vez de somadas: inserir no começo com a
  // fórmula antiga deixava o conteúdo abrindo com linhas em branco.
  const antes = content.slice(0, limite).replace(/\n+$/, '');
  const depois = content.slice(limite).replace(/^\n+/, '');

  return [antes, texto, depois].filter((parte) => parte !== '').join('\n\n');
}

/**
 * Troca um trecho de lugar com o vizinho de cima (`-1`) ou de baixo (`1`).
 *
 * Trabalha sobre os `range`s, trocando só o texto dos dois trechos — o resto do
 * conteúdo não é reescrito. A legenda anda junto porque já está dentro do
 * `range` da figura ou do código.
 *
 * A emenda entre os dois vira linha em branco: dois trechos colados por uma
 * quebra só ("### Título\nParágrafo") podiam, trocados de ordem, se fundir num
 * trecho só na releitura.
 *
 * Devolve o conteúdo INALTERADO quando a troca não é possível ou mudaria o
 * texto além da ordem — ponta da lista, índice inválido, ou um parágrafo que,
 * posto logo acima de uma figura, seria lido como legenda dela ("Exemplo: …").
 * Quem chama compara com o original para saber se moveu.
 */
export function moveLessonBlock(content: string, indice: number, direcao: -1 | 1): string {
  const { blocks } = parseLessonContent(content);
  const alvo = indice + direcao;
  if (!Number.isInteger(indice) || indice < 0 || indice >= blocks.length || alvo < 0 || alvo >= blocks.length) {
    return content;
  }

  const [primeiro, segundo] = direcao === -1 ? [blocks[alvo], blocks[indice]] : [blocks[indice], blocks[alvo]];
  const entre = content.slice(primeiro.range.end, segundo.range.start);
  // Entre dois trechos vizinhos só há espaço em branco; se houver outra coisa,
  // os ranges não são o que parecem e trocar estragaria o texto.
  if (entre.trim() !== '') return content;

  const textoPrimeiro = content.slice(primeiro.range.start, primeiro.range.end);
  const textoSegundo = content.slice(segundo.range.start, segundo.range.end);
  const novo = content.slice(0, primeiro.range.start)
    + textoSegundo
    + (/\n\s*\n/.test(entre) ? entre : '\n\n')
    + textoPrimeiro
    + content.slice(segundo.range.end);

  // A troca só vale se, relido, o conteúdo tem os mesmos trechos na nova ordem.
  const antes = blocks.map(serializeLessonBlock);
  const depois = parseLessonContent(novo).blocks.map(serializeLessonBlock);
  const esperado = [...antes];
  [esperado[indice], esperado[alvo]] = [esperado[alvo], esperado[indice]];
  if (depois.length !== esperado.length || depois.some((texto, i) => texto !== esperado[i])) {
    return content;
  }

  return novo;
}
