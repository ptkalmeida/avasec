/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState } from 'react';
import {
  Pencil, Check, X, Trash2, Plus, HelpCircle, ChevronDown, ChevronUp,
  Heading1, Heading2, AlignLeft, ListOrdered, List
} from 'lucide-react';
import {
  LessonBlock, parseLessonContent, serializeLessonBlock,
  replaceLessonBlock, removeLessonBlock, insertLessonBlockAfter,
} from '../../utils/lessonContent';
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

/**
 * Tipos que o botão "+" oferece, com o texto inicial de cada um.
 *
 * "Código" NÃO entra aqui de propósito: quem edita o texto das aulas é gestor de
 * conteúdo, não programador, e oferecer um bloco que pede linguagem e indentação
 * convida ao erro em vez de ajudar. Os blocos de código que JÁ existem nas aulas
 * continuam aparecendo e sendo editáveis pelo lápis — tirar a edição deles tornaria
 * esse conteúdo impossível de corrigir.
 */
const NOVOS_BLOCOS = [
  { kind: 'section', label: 'Seção', icon: Heading1, texto: '## Título da seção' },
  { kind: 'subsection', label: 'Subtítulo', icon: Heading2, texto: '### Subtítulo' },
  { kind: 'paragraph', label: 'Parágrafo', icon: AlignLeft, texto: 'Escreva o parágrafo aqui.' },
  { kind: 'orderedList', label: 'Lista numerada', icon: ListOrdered, texto: '1. Primeiro item\n2. Segundo item' },
  { kind: 'bulletList', label: 'Lista', icon: List, texto: '- Primeiro item\n- Segundo item' },
] as const;

const ROTULO: Record<LessonBlock['kind'], string> = {
  section: 'Seção',
  subsection: 'Subtítulo',
  paragraph: 'Parágrafo',
  orderedList: 'Lista numerada',
  bulletList: 'Lista',
  code: 'Bloco de código',
};

interface LessonContentEditorProps {
  value: string;
  onChange: (next: string) => void;
}

const campo = 'w-full rounded-lg border border-slate-200 p-2.5 text-[13px] text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500';
const rotuloCampo = 'block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1';

/**
 * Formulário do bloco em edição. Cada tipo mostra só os campos que fazem
 * sentido para ele — quem escreve não vê marcação, vê "Título da seção",
 * "Itens da lista", "Linguagem".
 */
const BlockForm: React.FC<{
  block: LessonBlock;
  onCancel: () => void;
  onConfirm: (texto: string) => void;
}> = ({ block, onCancel, onConfirm }) => {
  const [texto, setTexto] = useState(
    block.kind === 'section' || block.kind === 'subsection' || block.kind === 'paragraph' ? block.text : ''
  );
  const [itens, setItens] = useState(
    block.kind === 'orderedList' || block.kind === 'bulletList' ? block.items.join('\n') : ''
  );
  const [codigo, setCodigo] = useState(block.kind === 'code' ? block.code : '');
  const [legenda, setLegenda] = useState(block.kind === 'code' ? (block.caption ?? '') : '');
  const [linguagem, setLinguagem] = useState(block.kind === 'code' ? (block.language ?? 'java') : 'java');

  const confirmar = () => {
    switch (block.kind) {
      case 'section':
      case 'subsection':
      case 'paragraph':
        onConfirm(serializeLessonBlock({ ...block, text: texto.trim() }));
        break;
      case 'orderedList':
      case 'bulletList':
        onConfirm(serializeLessonBlock({
          ...block,
          items: itens.split('\n').map((i) => i.trim()).filter((i) => i !== ''),
        }));
        break;
      case 'code':
        onConfirm(serializeLessonBlock({
          ...block,
          language: linguagem,
          caption: legenda.trim() === '' ? null : legenda.trim(),
          code: codigo,
        }));
        break;
    }
  };

  return (
    <div className="rounded-xl border-2 border-teal-500/60 bg-teal-50/20 p-3 space-y-2.5">
      <span className="text-[10px] font-black uppercase tracking-widest text-teal-700">
        Editando: {ROTULO[block.kind]}
      </span>

      {(block.kind === 'section' || block.kind === 'subsection') && (
        <div>
          <label className={rotuloCampo}>
            {block.kind === 'section' ? 'Título da seção (entra no índice)' : 'Subtítulo'}
          </label>
          <input type="text" autoFocus value={texto} onChange={(e) => setTexto(e.target.value)} className={`${campo} font-bold`} />
        </div>
      )}

      {block.kind === 'paragraph' && (
        <div>
          <label className={rotuloCampo}>Texto do parágrafo</label>
          <textarea rows={4} autoFocus value={texto} onChange={(e) => setTexto(e.target.value)} className={campo} />
          <p className="mt-1 text-[10px] text-slate-400">
            Para destacar uma palavra, envolva com dois asteriscos: **assim**.
          </p>
        </div>
      )}

      {(block.kind === 'orderedList' || block.kind === 'bulletList') && (
        <div>
          <label className={rotuloCampo}>Itens — um por linha</label>
          <textarea rows={5} autoFocus value={itens} onChange={(e) => setItens(e.target.value)} className={`${campo} font-mono text-xs`} />
          <p className="mt-1 text-[10px] text-slate-400">
            A numeração é automática; não precisa escrever “1.” nem “-”.
          </p>
        </div>
      )}

      {block.kind === 'code' && (
        <>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
            <div>
              <label className={rotuloCampo}>Linguagem</label>
              <select value={linguagem} onChange={(e) => setLinguagem(e.target.value)} className={`${campo} cursor-pointer font-bold`}>
                {LINGUAGENS.map((l) => <option key={l.fence} value={l.fence}>{l.label}</option>)}
              </select>
            </div>
            <div className="sm:col-span-2">
              <label className={rotuloCampo}>Legenda (opcional)</label>
              <input
                type="text"
                value={legenda}
                onChange={(e) => setLegenda(e.target.value)}
                placeholder="Ex: Código 1: declaração da classe"
                className={campo}
              />
            </div>
          </div>
          <div>
            <label className={rotuloCampo}>Código</label>
            <textarea
              rows={8}
              autoFocus
              value={codigo}
              onChange={(e) => setCodigo(e.target.value)}
              spellCheck={false}
              className={`${campo} font-mono text-xs leading-relaxed`}
            />
          </div>
        </>
      )}

      <div className="flex justify-end gap-2 pt-0.5">
        <button
          type="button"
          onClick={onCancel}
          className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-bold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
        >
          <X className="h-3 w-3" /> Cancelar
        </button>
        <button
          type="button"
          onClick={confirmar}
          className="inline-flex items-center gap-1 rounded-lg bg-teal-600 hover:bg-teal-500 px-3.5 py-1.5 text-[10px] font-black uppercase tracking-wider text-white transition-colors cursor-pointer"
        >
          <Check className="h-3 w-3" /> Aplicar
        </button>
      </div>
    </div>
  );
};

/**
 * Edição do conteúdo da aula NA PRÓPRIA tela do aluno: um único card, sem
 * painel de marcação ao lado. Cada bloco renderizado tem um lápis que o abre
 * em formulário no lugar; o resto da aula continua visível como o aluno vê.
 *
 * A gravação é um splice pelo `range` do bloco (ver lessonContent.ts), então
 * editar um bloco NÃO reescreve o resto do texto — indentação de código,
 * espaçamento e tudo o mais permanecem byte a byte.
 */
export const LessonContentEditor: React.FC<LessonContentEditorProps> = ({ value, onChange }) => {
  const [editando, setEditando] = useState<number | null>(null);
  const [adicionandoEm, setAdicionandoEm] = useState<number | null>(null);
  const [showHelp, setShowHelp] = useState(false);

  const parsed = parseLessonContent(value);

  const adicionar = (indice: number, texto: string) => {
    const alvo = indice < 0 ? null : parsed.blocks[indice]?.range ?? null;
    onChange(insertLessonBlockAfter(value, alvo, texto));
    setAdicionandoEm(null);
  };

  /** Menu "+" que aparece entre blocos. `indice` = -1 insere no começo. */
  const MenuAdicionar: React.FC<{ indice: number }> = ({ indice }) => {
    const aberto = adicionandoEm === indice;

    return (
      <div className="relative py-1">
        {aberto ? (
          <div className="rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
            <span className="mb-1.5 block px-1 text-[9px] font-black uppercase tracking-widest text-slate-400">
              Adicionar aqui
            </span>
            <div className="flex flex-wrap gap-1.5">
              {NOVOS_BLOCOS.map((n) => (
                <button
                  key={n.kind}
                  type="button"
                  onClick={() => adicionar(indice, n.texto)}
                  className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[10px] font-bold text-slate-600 hover:border-teal-400 hover:text-teal-700 transition-colors cursor-pointer"
                >
                  <n.icon className="h-3 w-3" /> {n.label}
                </button>
              ))}
              <button
                type="button"
                onClick={() => setAdicionandoEm(null)}
                className="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-[10px] font-bold text-slate-400 hover:text-slate-700 transition-colors cursor-pointer"
              >
                <X className="h-3 w-3" /> Fechar
              </button>
            </div>
          </div>
        ) : (
          <button
            type="button"
            onClick={() => { setAdicionandoEm(indice); setEditando(null); }}
            aria-label="Adicionar bloco aqui"
            className="group flex w-full items-center gap-2 py-0.5 cursor-pointer"
          >
            <span className="h-px flex-1 bg-slate-200 group-hover:bg-teal-400 transition-colors" />
            <span className="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-400 group-hover:border-teal-400 group-hover:text-teal-700 transition-colors">
              <Plus className="h-2.5 w-2.5" /> Adicionar
            </span>
            <span className="h-px flex-1 bg-slate-200 group-hover:bg-teal-400 transition-colors" />
          </button>
        )}
      </div>
    );
  };

  return (
    <div className="space-y-2">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider">
            Conteúdo de estudo da aula
          </label>
          <p className="text-[10px] text-slate-400">
            Esta é a tela do aluno. Clique no lápis de um trecho para alterá-lo.
          </p>
        </div>
        <button
          type="button"
          onClick={() => setShowHelp((v) => !v)}
          className="inline-flex items-center gap-1 text-[10px] font-bold text-teal-700 hover:text-teal-800 cursor-pointer"
        >
          <HelpCircle className="h-3 w-3" />
          Como funciona
          {showHelp ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
        </button>
      </div>

      {showHelp && (
        <div className="rounded-xl border border-teal-100 bg-teal-50/40 p-3 text-[11px] leading-relaxed text-slate-700 space-y-1.5">
          <p>O que você vê abaixo é exatamente o que o aluno vê. Para mudar algo:</p>
          <ul className="space-y-1 pl-4 list-disc marker:text-teal-600">
            <li>Passe o mouse sobre um trecho e clique no <strong>lápis</strong> para editá-lo.</li>
            <li>Clique em <strong>Adicionar</strong>, entre dois trechos, para inserir algo novo ali.</li>
            <li>A <strong>lixeira</strong> remove o trecho.</li>
            <li><strong>Seção</strong> numera a parte e cria o índice “Nesta aula”; <strong>Subtítulo</strong> separa assuntos dentro dela.</li>
          </ul>
        </div>
      )}

      <div className="rounded-xl border border-slate-200 bg-white p-4">
        {parsed.blocks.length === 0 ? (
          <div className="py-6 text-center space-y-3">
            <p className="text-xs text-slate-400 italic">Esta aula ainda não tem material escrito.</p>
            <div className="flex flex-wrap justify-center gap-1.5">
              {NOVOS_BLOCOS.map((n) => (
                <button
                  key={n.kind}
                  type="button"
                  onClick={() => onChange(n.texto)}
                  className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[10px] font-bold text-slate-600 hover:border-teal-400 hover:text-teal-700 transition-colors cursor-pointer"
                >
                  <Plus className="h-3 w-3" /> {n.label}
                </button>
              ))}
            </div>
          </div>
        ) : (
          <>
            <MenuAdicionar indice={-1} />

            {parsed.blocks.map((block, i) => (
              <div key={`${block.kind}-${block.range.start}`}>
                {editando === i ? (
                  <BlockForm
                    block={block}
                    onCancel={() => setEditando(null)}
                    onConfirm={(texto) => {
                      onChange(replaceLessonBlock(value, block.range, texto));
                      setEditando(null);
                    }}
                  />
                ) : (
                  <div className="group relative rounded-xl px-3 py-1 transition-colors hover:bg-slate-50/80">
                    {/* Controles do bloco: aparecem no hover e no foco por teclado. */}
                    <div className="absolute right-1.5 top-1.5 z-10 flex gap-1 opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100">
                      <button
                        type="button"
                        onClick={() => { setEditando(i); setAdicionandoEm(null); }}
                        aria-label={`Editar ${ROTULO[block.kind].toLowerCase()}`}
                        title={`Editar ${ROTULO[block.kind].toLowerCase()}`}
                        className="rounded-lg border border-slate-200 bg-white p-1.5 text-slate-500 shadow-3xs hover:border-[#540D6E]/40 hover:text-[#540D6E] transition-colors cursor-pointer"
                      >
                        <Pencil className="h-3 w-3" />
                      </button>
                      <button
                        type="button"
                        onClick={() => onChange(removeLessonBlock(value, block.range))}
                        aria-label={`Remover ${ROTULO[block.kind].toLowerCase()}`}
                        title={`Remover ${ROTULO[block.kind].toLowerCase()}`}
                        className="rounded-lg border border-slate-200 bg-white p-1.5 text-slate-400 shadow-3xs hover:border-rose-300 hover:text-rose-600 transition-colors cursor-pointer"
                      >
                        <Trash2 className="h-3 w-3" />
                      </button>
                    </div>

                    {/* Renderizado pelo MESMO componente do aluno. */}
                    <LessonContent blocks={[block]} />
                  </div>
                )}

                <MenuAdicionar indice={i} />
              </div>
            ))}
          </>
        )}
      </div>

      <div className="flex flex-wrap gap-3 text-[10px] font-bold text-slate-400">
        <span>{parsed.sections.filter((s) => s.level === 2).length} seções</span>
        <span>{parsed.blocks.length} {parsed.blocks.length === 1 ? 'trecho' : 'trechos'}</span>
        <span>{value.trim() === '' ? 0 : value.trim().split(/\s+/).length} palavras</span>
        {parsed.hasCode && <span className="text-teal-600">contém bloco de código</span>}
      </div>
    </div>
  );
};
