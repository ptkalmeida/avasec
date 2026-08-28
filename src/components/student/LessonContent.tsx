/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { Suspense } from 'react';
import { LessonBlock } from '../../utils/lessonContent';

// O realce de sintaxe (Prism + linguagens) pesa ~140kB e a maioria das aulas
// não tem código — então o bloco só é baixado quando uma aula realmente usa.
const LessonCodeBlock = React.lazy(() =>
  import('./LessonCodeBlock').then((m) => ({ default: m.LessonCodeBlock }))
);

/** Placeholder enquanto o bloco de código chega, para o texto não "pular". */
const CodeFallback: React.FC = () => (
  <div className="my-5 rounded-xl border border-slate-800 bg-slate-950 px-4 py-6">
    <span className="text-[11px] font-mono text-slate-500">Carregando código...</span>
  </div>
);

/** Paletas por contexto: aula do aluno (claro) e prévia do instrutor (escuro). */
const TONES = {
  light: {
    body: 'text-slate-700',
    strong: 'text-slate-900',
    section: 'text-slate-900',
    sectionNumber: 'text-[#540D6E]',
    subsection: 'text-teal-700',
    orderedMarker: 'marker:text-[#540D6E]',
    bulletMarker: 'marker:text-teal-600',
  },
  dark: {
    body: 'text-slate-300',
    strong: 'text-white',
    section: 'text-white',
    sectionNumber: 'text-purple-400',
    subsection: 'text-teal-400',
    orderedMarker: 'marker:text-purple-400',
    bulletMarker: 'marker:text-teal-400',
  },
} as const;

/** Aplica **negrito** montando nós React — nunca HTML. */
const renderInline = (
  text: string,
  keyPrefix: string,
  strongClass: string
): React.ReactNode[] =>
  text.split(/(\*\*[^*]+\*\*)/g).map((part, i) => {
    if (part.startsWith('**') && part.endsWith('**')) {
      return <strong key={`${keyPrefix}-${i}`} className={`font-bold ${strongClass}`}>{part.slice(2, -2)}</strong>;
    }

    return <React.Fragment key={`${keyPrefix}-${i}`}>{part}</React.Fragment>;
  });

interface LessonContentProps {
  blocks: LessonBlock[];
  /** 'dark' para uso sobre fundo escuro (prévia do instrutor). */
  tone?: 'light' | 'dark';
}

/**
 * Renderiza o conteúdo da aula na medida de leitura confortável. Títulos
 * recebem `id` para o índice ancorar, e `scroll-mt` para o cabeçalho fixo não
 * cobrir o alvo depois do salto.
 */
export const LessonContent: React.FC<LessonContentProps> = ({ blocks, tone = 'light' }) => {
  const t = TONES[tone];

  return (
  <div className={`text-[13.5px] leading-[1.75] ${t.body}`}>
    {blocks.map((block, i) => {
      switch (block.kind) {
        case 'section':
          return (
            <h3
              key={`s-${i}`}
              id={block.id}
              className={`scroll-mt-28 mt-9 mb-4 first:mt-0 flex items-baseline gap-2.5 text-base md:text-lg font-black font-serif ${t.section}`}
            >
              <span className={`font-mono text-sm shrink-0 ${t.sectionNumber}`}>{block.index}.</span>
              <span>{renderInline(block.text, `s-${i}`, t.strong)}</span>
            </h3>
          );

        case 'subsection':
          return (
            <h4
              key={`ss-${i}`}
              id={block.id}
              className={`scroll-mt-28 mt-7 mb-2.5 text-sm font-extrabold ${t.subsection}`}
            >
              {renderInline(block.text, `ss-${i}`, t.strong)}
            </h4>
          );

        case 'paragraph':
          return (
            <p key={`p-${i}`} className="mb-3.5">
              {renderInline(block.text, `p-${i}`, t.strong)}
            </p>
          );

        case 'orderedList':
          return (
            <ol key={`ol-${i}`} className={`list-decimal pl-5 space-y-1.5 mb-4 marker:font-bold ${t.orderedMarker}`}>
              {block.items.map((item, j) => (
                <li key={j}>{renderInline(item, `ol-${i}-${j}`, t.strong)}</li>
              ))}
            </ol>
          );

        case 'bulletList':
          return (
            <ul key={`ul-${i}`} className={`list-disc pl-5 space-y-1.5 mb-4 ${t.bulletMarker}`}>
              {block.items.map((item, j) => (
                <li key={j}>{renderInline(item, `ul-${i}-${j}`, t.strong)}</li>
              ))}
            </ul>
          );

        case 'code':
          return (
            <Suspense key={`code-${i}`} fallback={<CodeFallback />}>
              <LessonCodeBlock
                code={block.code}
                language={block.language}
                caption={block.caption}
              />
            </Suspense>
          );
      }
    })}
  </div>
  );
};
