/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState } from 'react';
import { Copy, Check, Sun, Moon } from 'lucide-react';
import { PrismLight as SyntaxHighlighter } from 'react-syntax-highlighter';
import { oneDark, oneLight } from 'react-syntax-highlighter/dist/esm/styles/prism';
import java from 'react-syntax-highlighter/dist/esm/languages/prism/java';
import javascript from 'react-syntax-highlighter/dist/esm/languages/prism/javascript';
import typescript from 'react-syntax-highlighter/dist/esm/languages/prism/typescript';
import php from 'react-syntax-highlighter/dist/esm/languages/prism/php';
import python from 'react-syntax-highlighter/dist/esm/languages/prism/python';
import sql from 'react-syntax-highlighter/dist/esm/languages/prism/sql';
import bash from 'react-syntax-highlighter/dist/esm/languages/prism/bash';
import json from 'react-syntax-highlighter/dist/esm/languages/prism/json';
import css from 'react-syntax-highlighter/dist/esm/languages/prism/css';
import markup from 'react-syntax-highlighter/dist/esm/languages/prism/markup';

// Build "light" do Prism: registramos só as linguagens que o curso usa, para não
// arrastar o pacote inteiro (~1MB) para dentro do bundle.
const LANGUAGES: Record<string, unknown> = {
  java, javascript, typescript, php, python, sql, bash, json, css, markup,
};

Object.entries(LANGUAGES).forEach(([name, definition]) => {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  SyntaxHighlighter.registerLanguage(name, definition as any);
});

/** Apelidos que o instrutor pode escrever na cerca. */
const ALIASES: Record<string, string> = {
  js: 'javascript',
  ts: 'typescript',
  py: 'python',
  sh: 'bash',
  shell: 'bash',
  html: 'markup',
  xml: 'markup',
  postgres: 'sql',
  mysql: 'sql',
};

/** Rótulo exibido no cabeçalho do bloco. */
const LABELS: Record<string, string> = {
  java: 'Java',
  javascript: 'JavaScript',
  typescript: 'TypeScript',
  php: 'PHP',
  python: 'Python',
  sql: 'SQL',
  bash: 'Terminal',
  json: 'JSON',
  css: 'CSS',
  markup: 'HTML',
};

interface LessonCodeBlockProps {
  code: string;
  /** Linguagem declarada na cerca (```java); null = texto sem realce. */
  language: string | null;
  /** Legenda exibida acima do bloco (ex.: "Código 5: método atualizarID"). */
  caption?: string | null;
}

export const LessonCodeBlock: React.FC<LessonCodeBlockProps> = ({ code, language, caption }) => {
  const [copied, setCopied] = useState(false);
  const [isLight, setIsLight] = useState(false);

  const resolved = language ? (ALIASES[language] ?? language) : null;
  const isSupported = resolved !== null && resolved in LANGUAGES;
  const label = resolved ? (LABELS[resolved] ?? resolved.toUpperCase()) : 'Código';

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(code);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // Clipboard bloqueado (contexto sem permissão): não trava a leitura.
    }
  };

  return (
    <figure className="my-5 space-y-2">
      {caption && (
        <figcaption className="text-[11px] font-bold text-teal-700">{caption}</figcaption>
      )}

      <div
        className={`rounded-xl overflow-hidden border shadow-2xs ${
          isLight ? 'bg-white border-slate-250' : 'bg-slate-950 border-slate-800'
        }`}
      >
        {/* Cabeçalho: linguagem + controles */}
        <div
          className={`flex items-center justify-between gap-3 px-3.5 py-2 border-b ${
            isLight ? 'border-slate-150 bg-slate-50' : 'border-slate-800 bg-slate-900'
          }`}
        >
          <span
            className={`text-[10px] font-mono font-black uppercase tracking-wider ${
              isLight ? 'text-slate-500' : 'text-slate-400'
            }`}
          >
            {label}
          </span>

          <div className="flex items-center gap-1">
            <button
              type="button"
              onClick={() => setIsLight((v) => !v)}
              title={isLight ? 'Usar fundo escuro' : 'Usar fundo claro'}
              aria-label={isLight ? 'Usar fundo escuro' : 'Usar fundo claro'}
              className={`p-1.5 rounded-lg transition-colors cursor-pointer ${
                isLight ? 'text-slate-500 hover:bg-slate-150' : 'text-slate-400 hover:bg-slate-800'
              }`}
            >
              {isLight ? <Moon className="h-3.5 w-3.5" /> : <Sun className="h-3.5 w-3.5" />}
            </button>

            <button
              type="button"
              onClick={copy}
              title="Copiar código"
              className={`flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-wider transition-colors cursor-pointer ${
                isLight
                  ? 'text-slate-600 hover:bg-slate-150'
                  : 'text-slate-300 hover:bg-slate-800'
              }`}
            >
              {copied ? <Check className="h-3.5 w-3.5 text-teal-500" /> : <Copy className="h-3.5 w-3.5" />}
              <span>{copied ? 'Copiado' : 'Copiar'}</span>
            </button>
          </div>
        </div>

        {/* O código em si — rola na horizontal sem estourar a página */}
        <div className="overflow-x-auto">
          {isSupported ? (
            <SyntaxHighlighter
              language={resolved as string}
              style={isLight ? oneLight : oneDark}
              customStyle={{
                margin: 0,
                background: 'transparent',
                fontSize: '12px',
                lineHeight: 1.65,
                padding: '14px 16px',
              }}
              codeTagProps={{ style: { fontFamily: 'ui-monospace, SFMono-Regular, Menlo, monospace' } }}
            >
              {code}
            </SyntaxHighlighter>
          ) : (
            <pre
              className={`m-0 px-4 py-3.5 text-[12px] leading-relaxed font-mono ${
                isLight ? 'text-slate-800' : 'text-slate-200'
              }`}
            >
              {code}
            </pre>
          )}
        </div>
      </div>
    </figure>
  );
};
