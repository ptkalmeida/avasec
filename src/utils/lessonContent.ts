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
 */

export type LessonBlock =
  | { kind: 'section'; id: string; text: string; index: number }
  | { kind: 'subsection'; id: string; text: string }
  | { kind: 'paragraph'; text: string }
  | { kind: 'orderedList'; items: string[] }
  | { kind: 'bulletList'; items: string[] }
  | { kind: 'code'; language: string | null; caption: string | null; code: string };

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
  const lines = (content ?? '').split('\n');

  let ordered: string[] = [];
  let bullets: string[] = [];
  let sectionCount = 0;
  let anchorCount = 0;
  let hasCode = false;

  const flushLists = () => {
    if (ordered.length > 0) {
      blocks.push({ kind: 'orderedList', items: ordered });
      ordered = [];
    }
    if (bullets.length > 0) {
      blocks.push({ kind: 'bulletList', items: bullets });
      bullets = [];
    }
  };

  /** Última legenda vista, para colar no próximo bloco de código. */
  let pendingCaption: string | null = null;

  const takeCaption = (): string | null => {
    const caption = pendingCaption;
    pendingCaption = null;

    return caption;
  };

  const flushCaptionAsParagraph = () => {
    if (pendingCaption !== null) {
      blocks.push({ kind: 'paragraph', text: pendingCaption });
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

    if (section) {
      flushLists();
      flushCaptionAsParagraph();
      sectionCount++;
      const id = slug(section[1], anchorCount++);
      blocks.push({ kind: 'section', id, text: section[1], index: sectionCount });
      sections.push({ id, text: section[1], level: 2, index: sectionCount });
    } else if (subsection) {
      flushLists();
      flushCaptionAsParagraph();
      const id = slug(subsection[1], anchorCount++);
      blocks.push({ kind: 'subsection', id, text: subsection[1] });
      sections.push({ id, text: subsection[1], level: 3 });
    } else if (numbered) {
      flushCaptionAsParagraph();
      if (bullets.length > 0) flushLists();
      ordered.push(numbered[1]);
    } else if (bullet) {
      flushCaptionAsParagraph();
      if (ordered.length > 0) flushLists();
      bullets.push(bullet[1]);
    } else {
      flushLists();
      // Uma legenda só vale como legenda se um bloco de código vier depois;
      // senão ela cai como parágrafo normal na próxima decisão.
      if (CAPTION.test(line)) {
        flushCaptionAsParagraph();
        pendingCaption = line;
      } else {
        flushCaptionAsParagraph();
        blocks.push({ kind: 'paragraph', text: line });
      }
    }
  }

  flushLists();
  flushCaptionAsParagraph();

  return { blocks, sections, hasCode };
}
