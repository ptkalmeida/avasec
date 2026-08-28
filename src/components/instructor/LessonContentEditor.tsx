/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useRef, useState } from 'react';
import {
  Heading1, Heading2, Bold, ListOrdered, List, Code2, Captions,
  Eye, PenLine, HelpCircle, ChevronDown, ChevronUp
} from 'lucide-react';
import { parseLessonContent } from '../../utils/lessonContent';

// A prévia usa o MESMO renderizador do aluno — é essa a garantia de que o que o
// instrutor vê aqui é o que o aluno recebe. O peso do realce de sintaxe continua
// carregado sob demanda dentro dele (LessonCodeBlock em React.lazy).
import { LessonContent } from '../student/LessonContent';

/** Linguagens que o bloco de código realmente realça (LessonCodeBlock). */
const LINGUAGENS = [
  { fence: 'java', label: 'Java' },
  { fence: 'javascript', label: 'JavaScript' },
  { fence: 'typescript', label: 'TypeScript' },
  { fence: 'php', label: 'PHP' },
  { fence: 'python', label: 'Python' },
  { fence: 'sql', label: 'SQL' },
  { fence: 'json', label: 'JSON' },
  { fence: 'css', label: 'CSS' },
  { fence: 'html', label: 'HTML' },
  { fence: 'bash', label: 'Terminal' },
] as const;

interface LessonContentEditorProps {
  value: string;
  onChange: (next: string) => void;
  /** Altura da área de escrita; o painel de detalhes usa uma caixa maior. */
  rows?: number;
}

/**
 * Edição do texto da aula com barra de ferramentas e prévia idêntica à tela do
 * aluno. O formato gravado continua sendo texto puro — os botões só inserem a
 * marcação, para quem escreve não precisar decorá-la.
 */
export const LessonContentEditor: React.FC<LessonContentEditorProps> = ({ value, onChange, rows = 14 }) => {
  const areaRef = useRef<HTMLTextAreaElement>(null);
  const [showHelp, setShowHelp] = useState(false);
  // Em tela estreita não cabem escrita e prévia lado a lado: alterna.
  const [mobileTab, setMobileTab] = useState<'escrever' | 'previa'>('escrever');
  const [linguagem, setLinguagem] = useState<string>('java');

  const parsed = parseLessonContent(value);

  /**
   * Grava o texto e devolve o cursor para a área de escrita, já selecionando o
   * trecho inserido — assim quem clicou em "Seção" pode digitar o título por cima.
   * O reposicionamento é secundário: se o ambiente não tiver rAF, o texto entra
   * do mesmo jeito em vez de a inserção falhar por causa do cursor.
   */
  const applyEdit = (next: string, selectionStart: number, selectionEnd: number) => {
    onChange(next);

    const restoreCursor = () => {
      const area = areaRef.current;
      if (!area) return;
      area.focus();
      area.setSelectionRange(selectionStart, selectionEnd);
    };

    if (typeof requestAnimationFrame === 'function') {
      requestAnimationFrame(restoreCursor);
    }
  };

  /** Prefixa a marcação na linha do cursor, sem atropelar o que já está escrito. */
  const prefixLine = (marca: string, exemplo: string) => {
    const area = areaRef.current;
    if (!area) return;

    const { selectionStart, selectionEnd } = area;
    const selecionado = value.slice(selectionStart, selectionEnd);
    const texto = selecionado || exemplo;

    const inicioLinha = value.lastIndexOf('\n', selectionStart - 1) + 1;
    const proximaQuebra = value.indexOf('\n', selectionEnd);
    const fimLinha = proximaQuebra === -1 ? value.length : proximaQuebra;
    const linha = value.slice(inicioLinha, fimLinha);

    // Havia seleção: transforma a linha dela e mantém o texto escolhido.
    if (selecionado !== '') {
      const next = value.slice(0, inicioLinha) + marca + value.slice(inicioLinha);
      applyEdit(next, selectionStart + marca.length, selectionEnd + marca.length);

      return;
    }

    // Linha vazia: escreve na própria linha.
    if (linha.trim() === '') {
      const next = value.slice(0, inicioLinha) + marca + texto + value.slice(fimLinha);
      const cursor = inicioLinha + marca.length;
      applyEdit(next, cursor, cursor + texto.length);

      return;
    }

    // Linha com conteúdo: abre um novo parágrafo abaixo, para não colar no texto.
    const insercao = `\n\n${marca}${texto}`;
    const next = value.slice(0, fimLinha) + insercao + value.slice(fimLinha);
    const cursor = fimLinha + insercao.length;
    applyEdit(next, cursor - texto.length, cursor);
  };

  /** Envolve a seleção (ou insere um exemplo) entre delimitadores. */
  const wrapSelection = (marca: string, exemplo: string) => {
    const area = areaRef.current;
    if (!area) return;

    const { selectionStart, selectionEnd } = area;
    const selecionado = value.slice(selectionStart, selectionEnd) || exemplo;
    const next = value.slice(0, selectionStart) + marca + selecionado + marca + value.slice(selectionEnd);
    const inicio = selectionStart + marca.length;
    applyEdit(next, inicio, inicio + selecionado.length);
  };

  /** Insere um bloco de código já com a cerca e a linguagem escolhida. */
  const insertCode = () => {
    const area = areaRef.current;
    if (!area) return;

    const { selectionStart, selectionEnd } = area;
    const selecionado = value.slice(selectionStart, selectionEnd) || '// escreva o código aqui';
    const antes = value.slice(0, selectionStart);
    const quebra = antes === '' || antes.endsWith('\n\n') ? '' : antes.endsWith('\n') ? '\n' : '\n\n';
    const bloco = `${quebra}\`\`\`${linguagem}\n${selecionado}\n\`\`\`\n`;
    const next = antes + bloco + value.slice(selectionEnd);
    const inicioCodigo = antes.length + quebra.length + linguagem.length + 4;
    applyEdit(next, inicioCodigo, inicioCodigo + selecionado.length);
  };

  const botao = 'inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[10px] font-bold text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors cursor-pointer';

  return (
    <div className="space-y-2">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider">
          Conteúdo de estudo da aula
        </label>
        <button
          type="button"
          onClick={() => setShowHelp((v) => !v)}
          className="inline-flex items-center gap-1 text-[10px] font-bold text-teal-700 hover:text-teal-800 cursor-pointer"
        >
          <HelpCircle className="h-3 w-3" />
          Como formatar
          {showHelp ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
        </button>
      </div>

      {showHelp && (
        <div className="rounded-xl border border-teal-100 bg-teal-50/40 p-3 text-[11px] leading-relaxed text-slate-700 space-y-1.5">
          <p>
            Você escreve o texto normalmente. Os botões abaixo inserem a marcação — não precisa
            decorar nada, e a prévia à direita mostra o resultado exato que o aluno vê.
          </p>
          <ul className="space-y-1 pl-4 list-disc marker:text-teal-600">
            <li><strong>Seção</strong> divide a aula em partes numeradas e cria o índice “Nesta aula”.</li>
            <li><strong>Subtítulo</strong> separa assuntos dentro de uma seção, sem numerar.</li>
            <li><strong>Bloco de código</strong> mostra o código colorido, com botão de copiar.</li>
            <li><strong>Legenda</strong> é a linha escrita logo ANTES do código (ex.: “Código 1: …”).</li>
            <li>Linha vazia separa parágrafos.</li>
          </ul>
        </div>
      )}

      {/* Barra de ferramentas */}
      <div className="flex flex-wrap items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 p-2">
        <button type="button" className={botao} onClick={() => prefixLine('## ', 'Título da seção')} title="Seção numerada (entra no índice)">
          <Heading1 className="h-3 w-3" /> Seção
        </button>
        <button type="button" className={botao} onClick={() => prefixLine('### ', 'Subtítulo')} title="Subtítulo dentro da seção">
          <Heading2 className="h-3 w-3" /> Subtítulo
        </button>
        <button type="button" className={botao} onClick={() => wrapSelection('**', 'texto em destaque')} title="Negrito">
          <Bold className="h-3 w-3" /> Negrito
        </button>
        <span className="mx-0.5 h-5 w-px bg-slate-200" />
        <button type="button" className={botao} onClick={() => prefixLine('1. ', 'Primeiro item')} title="Lista numerada">
          <ListOrdered className="h-3 w-3" /> Lista numerada
        </button>
        <button type="button" className={botao} onClick={() => prefixLine('- ', 'Item da lista')} title="Lista com marcadores">
          <List className="h-3 w-3" /> Lista
        </button>
        <span className="mx-0.5 h-5 w-px bg-slate-200" />
        <select
          value={linguagem}
          onChange={(e) => setLinguagem(e.target.value)}
          title="Linguagem do próximo bloco de código"
          className="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[10px] font-bold text-slate-600 cursor-pointer"
        >
          {LINGUAGENS.map((l) => (
            <option key={l.fence} value={l.fence}>{l.label}</option>
          ))}
        </select>
        <button type="button" className={botao} onClick={insertCode} title="Inserir bloco de código">
          <Code2 className="h-3 w-3" /> Bloco de código
        </button>
        <button type="button" className={botao} onClick={() => prefixLine('', 'Código 1: descreva o que o código faz:')} title="Legenda do código (escreva antes do bloco)">
          <Captions className="h-3 w-3" /> Legenda
        </button>
      </div>

      {/* Alternador só em tela estreita */}
      <div className="flex gap-1 lg:hidden">
        <button
          type="button"
          onClick={() => setMobileTab('escrever')}
          className={`flex-1 inline-flex items-center justify-center gap-1 rounded-lg py-1.5 text-[10px] font-black uppercase tracking-wider cursor-pointer ${
            mobileTab === 'escrever' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'
          }`}
        >
          <PenLine className="h-3 w-3" /> Escrever
        </button>
        <button
          type="button"
          onClick={() => setMobileTab('previa')}
          className={`flex-1 inline-flex items-center justify-center gap-1 rounded-lg py-1.5 text-[10px] font-black uppercase tracking-wider cursor-pointer ${
            mobileTab === 'previa' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'
          }`}
        >
          <Eye className="h-3 w-3" /> Prévia
        </button>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
        <div className={mobileTab === 'escrever' ? '' : 'hidden lg:block'}>
          <textarea
            ref={areaRef}
            rows={rows}
            value={value}
            onChange={(e) => onChange(e.target.value)}
            className="w-full rounded-xl border border-slate-200 p-3 text-[12.5px] font-mono leading-relaxed text-slate-700 bg-white focus:outline-hidden focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500"
            placeholder={'## Comece pelo título da primeira seção\n\nEscreva o texto da aula normalmente.\n\nUse os botões acima para inserir seções, listas e código.'}
          />
          <div className="mt-1 flex flex-wrap gap-3 text-[10px] font-bold text-slate-400">
            <span>{parsed.sections.filter((s) => s.level === 2).length} seções</span>
            <span>{value.trim() === '' ? 0 : value.trim().split(/\s+/).length} palavras</span>
            {parsed.hasCode && <span className="text-teal-600">contém bloco de código</span>}
          </div>
        </div>

        <div className={mobileTab === 'previa' ? '' : 'hidden lg:block'}>
          <div className="rounded-xl border border-slate-200 bg-white p-4 h-full max-h-[420px] overflow-y-auto">
            <span className="mb-2.5 flex items-center gap-1 text-[9px] font-black uppercase tracking-widest text-slate-400">
              <Eye className="h-2.5 w-2.5" /> Como o aluno vê
            </span>
            {value.trim() === '' ? (
              <p className="text-xs text-slate-400 italic">
                Nada escrito ainda — o que você digitar aparece aqui.
              </p>
            ) : (
              <LessonContent blocks={parsed.blocks} />
            )}
          </div>
        </div>
      </div>
    </div>
  );
};
