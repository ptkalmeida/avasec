/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';

// Conteúdo de aula é escrito pelo instrutor em Markdown simples: títulos
// (### Título), negrito (**texto**) e listas numeradas (1. item). Este
// renderer cobre só esse subconjunto — não é um Markdown completo.
function renderInline(text: string, keyPrefix: string): React.ReactNode[] {
  return text.split(/(\*\*[^*]+\*\*)/g).map((part, i) => {
    if (part.startsWith('**') && part.endsWith('**')) {
      return <strong key={`${keyPrefix}-${i}`}>{part.slice(2, -2)}</strong>;
    }
    return <React.Fragment key={`${keyPrefix}-${i}`}>{part}</React.Fragment>;
  });
}

export function renderLessonMarkdown(
  content: string,
  opts?: { headingClassName?: string }
): React.ReactNode {
  const headingClassName = opts?.headingClassName ?? 'text-slate-900';
  const lines = content.split('\n');
  const blocks: React.ReactNode[] = [];
  let listItems: string[] = [];
  let key = 0;

  const flushList = () => {
    if (listItems.length > 0) {
      const listKey = key++;
      blocks.push(
        <ol key={`list-${listKey}`} className="list-decimal pl-5 space-y-1.5">
          {listItems.map((item, i) => (
            <li key={i}>{renderInline(item, `list-${listKey}-${i}`)}</li>
          ))}
        </ol>
      );
      listItems = [];
    }
  };

  for (const rawLine of lines) {
    const line = rawLine.trim();
    if (line === '') {
      flushList();
      continue;
    }

    const heading = line.match(/^###\s+(.*)/);
    const numbered = line.match(/^\d+\.\s+(.*)/);

    if (heading) {
      flushList();
      const headingKey = key++;
      blocks.push(
        <h5 key={`h-${headingKey}`} className={`font-bold ${headingClassName}`}>
          {renderInline(heading[1], `h-${headingKey}`)}
        </h5>
      );
    } else if (numbered) {
      listItems.push(numbered[1]);
    } else {
      flushList();
      const pKey = key++;
      blocks.push(<p key={`p-${pKey}`}>{renderInline(line, `p-${pKey}`)}</p>);
    }
  }
  flushList();

  return <div className="space-y-3">{blocks}</div>;
}
