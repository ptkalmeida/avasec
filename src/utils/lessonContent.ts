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
 *   Código 1: algo  -> legenda, quando imediatamente antes de um bloco de código
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

export type LessonBlock =
  | { kind: 'section'; id: string; text: string; index: number; range: LessonBlockRange }
  | { kind: 'subsection'; id: string; text: string; range: LessonBlockRange }
  | { kind: 'paragraph'; text: string; range: LessonBlockRange }
  | { kind: 'orderedList'; items: string[]; range: LessonBlockRange }
  | { kind: 'bulletList'; items: string[]; range: LessonBlockRange }
  | { kind: 'code'; language: string | null; caption: string | null; code: string; range: LessonBlockRange };

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
const CAPTION = /^(?:c[óo]digo|figura|tabela|quadro|exemplo)\s*\d*\s*[:.–-]\s*.+/i;

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

    const section = line.match(/^##\s+(?!#)(.*)$/);
    const subsection = line.match(/^###\s+(.*)$/);
    const numbered = line.match(/^\d+\.\s+(.*)$/);
    const bullet = line.match(/^[-*]\s+(.*)$/);
    const range = { start: lineStart[i], end: lineEnd(i) };

    if (section) {
      flushLists();
      flushCaptionAsParagraph();
      sectionCount++;
      const id = slug(section[1], anchorCount++);
      blocks.push({ kind: 'section', id, text: section[1], index: sectionCount, range });
      sections.push({ id, text: section[1], level: 2, index: sectionCount });
    } else if (subsection) {
      flushLists();
      flushCaptionAsParagraph();
      const id = slug(subsection[1], anchorCount++);
      blocks.push({ kind: 'subsection', id, text: subsection[1], range });
      sections.push({ id, text: subsection[1], level: 3 });
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
      // Uma legenda só vale como legenda se um bloco de código vier depois;
      // senão ela cai como parágrafo normal na próxima decisão.
      if (CAPTION.test(line)) {
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
      return `### ${block.text}`;
    case 'paragraph':
      return block.text;
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
  }
}

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

/** Insere um bloco novo depois de `range` (ou no fim, quando não há bloco). */
export function insertLessonBlockAfter(
  content: string,
  range: LessonBlockRange | null,
  texto: string
): string {
  if (content.trim() === '') return texto;

  const posicao = range === null ? content.length : range.end;

  return `${content.slice(0, posicao)}\n\n${texto}${content.slice(posicao)}`;
}
