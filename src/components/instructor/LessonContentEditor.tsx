/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState } from 'react';
import {
  Pencil, Check, X, Trash2, Plus, HelpCircle, ChevronDown, ChevronUp,
  Heading1, Heading2, AlignLeft, ListOrdered, List, Image as ImageIcon, Film
} from 'lucide-react';
import {
  LessonBlock, LessonBlockRange, parseLessonContent, serializeLessonBlock,
  replaceLessonBlock, removeLessonBlock, insertLessonBlockAt,
} from '../../utils/lessonContent';
import { LessonContent } from '../student/LessonContent';
import { LessonMediaForm } from './LessonMediaForm';

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
  // Mídia não tem texto pronto: o arquivo ou o link só existem depois que o autor
  // escolhe. `texto: null` faz o menu abrir o formulário em vez de inserir na hora.
  { kind: 'image', label: 'Imagem', icon: ImageIcon, texto: null },
  { kind: 'video', label: 'Vídeo', icon: Film, texto: null },
] as const;

const ROTULO: Record<LessonBlock['kind'], string> = {
  section: 'Seção',
  subsection: 'Subtítulo',
  paragraph: 'Parágrafo',
  orderedList: 'Lista numerada',
  bulletList: 'Lista',
  code: 'Bloco de código',
  image: 'Imagem',
  video: 'Vídeo',
};

interface LessonContentEditorProps {
  value: string;
  onChange: (next: string) => void;
  /**
   * Envio de arquivo — mesma assinatura de `uploadArquivo` do LMSContext. Vem por
   * prop, e não por contexto, para o editor continuar montável em teste sem
   * provider (o Harness usa `useState` puro) e para cada tela decidir se oferece
   * envio. Sem ela, o bloco de imagem avisa que o envio não está disponível.
   */
  onUpload?: (file: File) => Promise<{ ok: boolean; url?: string; error?: string }>;
}

const campo = 'w-full rounded-lg border border-slate-200 p-2.5 text-apoio text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500';
const rotuloCampo = 'block text-sobretitulo text-escult-ink-2 uppercase mb-1';

/**
 * Tipos entre os quais o trecho pode ser convertido. Código fica de fora: virar
 * "lista" um trecho com indentação e cerca destruiria o código em vez de
 * reaproveitá-lo, e quem edita aula não é quem escreve código (ver NOVOS_BLOCOS).
 */
const CONVERSIVEIS = ['section', 'subsection', 'paragraph', 'orderedList', 'bulletList'] as const;

type TipoTexto = (typeof CONVERSIVEIS)[number];

/** Uma linha por título/parágrafo/item — a forma comum a todos os conversíveis. */
const linhasDoBloco = (block: LessonBlock): string => {
  switch (block.kind) {
    case 'section':
    case 'subsection':
    case 'paragraph':
      return block.text;
    case 'orderedList':
    case 'bulletList':
      return block.items.join('\n');
    // Código e mídia não são "linhas de texto": não participam da conversão.
    case 'code':
    case 'image':
    case 'video':
      return '';
  }
};

/** O trecho participa da troca de tipo? Mídia e código ficam de fora. */
const ehConversivel = (kind: LessonBlock['kind']): kind is TipoTexto =>
  (CONVERSIVEIS as readonly string[]).includes(kind);

/**
 * Monta o bloco do tipo escolhido a partir das linhas digitadas.
 *
 * Título é uma linha por natureza, então linhas soltas viram uma só; parágrafo
 * preserva as quebras (cada linha vira um parágrafo); lista usa cada linha como
 * item. `id` e `index` não participam da serialização — existem só para satisfazer
 * o tipo, e o parser os recalcula na próxima leitura.
 */
const comoBloco = (kind: TipoTexto, partes: string[], range: LessonBlockRange): LessonBlock => {
  switch (kind) {
    case 'section':
      return { kind, id: '', text: partes.join(' '), index: 0, range };
    case 'subsection':
      return { kind, id: '', text: partes.join(' '), range };
    case 'paragraph':
      return { kind, text: partes.join('\n'), range };
    case 'orderedList':
    case 'bulletList':
      return { kind, items: partes, range };
  }
};

/**
 * Formulário do bloco em edição. Cada tipo mostra só os campos que fazem
 * sentido para ele — quem escreve não vê marcação, vê "Título da seção",
 * "Itens da lista", "Linguagem".
 */
const BlockForm: React.FC<{
  block: LessonBlock;
  onUpload?: LessonContentEditorProps['onUpload'];
  onCancel: () => void;
  onConfirm: (texto: string) => void;
}> = ({ block, onUpload, onCancel, onConfirm }) => {
  // Mídia tem formulário próprio (arquivo/link, descrição, legenda), e o mesmo
  // componente serve ao menu "+" — assim a validação não existe em duplicata.
  if (block.kind === 'image' || block.kind === 'video') {
    return (
      <LessonMediaForm
        tipo={block.kind}
        inicial={{
          url: block.url,
          texto: block.kind === 'image' ? block.alt : block.title,
          caption: block.caption,
        }}
        onUpload={onUpload}
        onCancel={onCancel}
        onConfirm={onConfirm}
      />
    );
  }

  return <BlockTextForm block={block} onCancel={onCancel} onConfirm={onConfirm} />;
};

/** Formulário dos trechos feitos de texto — inclui a troca de tipo. */
const BlockTextForm: React.FC<{
  block: LessonBlock;
  onCancel: () => void;
  onConfirm: (texto: string) => void;
}> = ({ block, onCancel, onConfirm }) => {
  // O tipo é editável: converter no lugar evita apagar o trecho e redigitá-lo só
  // para trocar um parágrafo por seção.
  const [kind, setKind] = useState<LessonBlock['kind']>(block.kind);
  const [linhas, setLinhas] = useState(linhasDoBloco(block));
  const [codigo, setCodigo] = useState(block.kind === 'code' ? block.code : '');
  const [legenda, setLegenda] = useState(block.kind === 'code' ? (block.caption ?? '') : '');
  const [linguagem, setLinguagem] = useState(block.kind === 'code' ? (block.language ?? 'java') : 'java');

  const partes = linhas.split('\n').map((l) => l.trim()).filter((l) => l !== '');
  // Só o que é feito de linhas pode estar "vazio": exigir texto de uma imagem
  // deixaria o botão Aplicar desabilitado para sempre.
  const conversivel = ehConversivel(block.kind);
  const vazio = conversivel && partes.length === 0;

  const trocarTipo = (proximo: TipoTexto) => {
    // Título mora num campo de uma linha: converter já juntando as quebras evita
    // que o campo esconda texto que o autor tinha escrito.
    if (proximo === 'section' || proximo === 'subsection') setLinhas(partes.join(' '));
    setKind(proximo);
  };

  const confirmar = () => {
    if (block.kind === 'code') {
      onConfirm(serializeLessonBlock({
        ...block,
        language: linguagem,
        caption: legenda.trim() === '' ? null : legenda.trim(),
        code: codigo,
      }));

      return;
    }

    // Trecho vazio não vira bloco: gravaria "## " e sujaria o conteúdo.
    if (vazio || !ehConversivel(kind)) return;

    onConfirm(serializeLessonBlock(comoBloco(kind, partes, block.range)));
  };

  return (
    <div className="rounded-xl border-2 border-teal-500/60 bg-teal-50/20 p-3 space-y-2.5">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <span className="text-sobretitulo uppercase text-teal-700">
          Editando: {ROTULO[block.kind]}
        </span>

        {conversivel && (
          <label className="flex items-center gap-1.5">
            <span className="text-sobretitulo uppercase text-escult-ink-2">Tipo</span>
            <select
              value={kind}
              onChange={(e) => trocarTipo(e.target.value as TipoTexto)}
              className="rounded-lg border border-slate-200 p-1.5 text-apoio font-bold text-slate-700 cursor-pointer focus:outline-hidden focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500"
            >
              {CONVERSIVEIS.map((k) => <option key={k} value={k}>{ROTULO[k]}</option>)}
            </select>
          </label>
        )}
      </div>

      {(kind === 'section' || kind === 'subsection') && (
        <div>
          <label className={rotuloCampo}>
            {kind === 'section' ? 'Título da seção (entra no índice)' : 'Subtítulo'}
          </label>
          <input type="text" autoFocus value={linhas} onChange={(e) => setLinhas(e.target.value)} className={`${campo} font-bold`} />
        </div>
      )}

      {kind === 'paragraph' && (
        <div>
          <label className={rotuloCampo}>Texto do parágrafo</label>
          <textarea rows={4} autoFocus value={linhas} onChange={(e) => setLinhas(e.target.value)} className={campo} />
          <p className="mt-1 text-apoio text-escult-ink-2">
            Para destacar uma palavra, envolva com dois asteriscos: **assim**.
          </p>
        </div>
      )}

      {(kind === 'orderedList' || kind === 'bulletList') && (
        <div>
          <label className={rotuloCampo}>Itens — um por linha</label>
          <textarea rows={5} autoFocus value={linhas} onChange={(e) => setLinhas(e.target.value)} className={`${campo} text-xs`} />
          <p className="mt-1 text-apoio text-escult-ink-2">
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
              className={`${campo} text-xs leading-relaxed`}
            />
          </div>
        </>
      )}

      <div className="flex justify-end gap-2 pt-0.5">
        <button
          type="button"
          onClick={onCancel}
          className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-apoio font-bold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
        >
          <X className="h-3 w-3" /> Cancelar
        </button>
        <button
          type="button"
          onClick={confirmar}
          disabled={vazio}
          title={vazio ? 'Escreva algo antes de aplicar' : undefined}
          className="inline-flex items-center gap-1 rounded-lg bg-teal-600 hover:bg-teal-500 px-3.5 py-1.5 text-sobretitulo uppercase text-white transition-colors cursor-pointer disabled:bg-slate-300 disabled:cursor-not-allowed"
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
export const LessonContentEditor: React.FC<LessonContentEditorProps> = ({ value, onChange, onUpload }) => {
  const [editando, setEditando] = useState<number | null>(null);
  const [adicionandoEm, setAdicionandoEm] = useState<number | null>(null);
  /** Mídia sendo criada: em qual posição, e de que tipo. */
  const [midiaNova, setMidiaNova] = useState<{ indice: number; tipo: 'image' | 'video' } | null>(null);
  const [showHelp, setShowHelp] = useState(false);

  const parsed = parseLessonContent(value);

  const adicionar = (indice: number, texto: string) => {
    // -1 é o menu do topo: o trecho novo entra ANTES de tudo, na posição 0.
    // Para os demais, logo depois do bloco que o menu acompanha.
    const bloco = indice < 0 ? null : parsed.blocks[indice];

    // Índice fora da faixa é engano de programação, não pedido do autor: melhor
    // não fazer nada do que despejar o trecho no fim da aula sem avisar.
    if (indice >= 0 && bloco === undefined) {
      setAdicionandoEm(null);

      return;
    }

    onChange(insertLessonBlockAt(value, bloco === null ? 0 : bloco.range.end, texto));
    setAdicionandoEm(null);
    setMidiaNova(null);
  };

  /** Menu "+" que aparece entre blocos. `indice` = -1 insere no começo. */
  const MenuAdicionar: React.FC<{ indice: number }> = ({ indice }) => {
    const aberto = adicionandoEm === indice;
    const criandoMidia = midiaNova !== null && midiaNova.indice === indice;

    if (criandoMidia) {
      return (
        <div className="py-1">
          <LessonMediaForm
            tipo={midiaNova.tipo}
            onUpload={onUpload}
            onCancel={() => setMidiaNova(null)}
            onConfirm={(texto) => adicionar(indice, texto)}
          />
        </div>
      );
    }

    return (
      <div className="relative py-1">
        {aberto ? (
          <div className="rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
            <span className="mb-1.5 block px-1 text-sobretitulo uppercase text-escult-ink-2">
              Adicionar aqui
            </span>
            <div className="flex flex-wrap gap-1.5">
              {NOVOS_BLOCOS.map((n) => (
                <button
                  key={n.kind}
                  type="button"
                  onClick={() => {
                    // Mídia não tem texto pronto: abre o formulário e só insere
                    // depois que o arquivo (ou o link) existir.
                    if (n.texto === null) {
                      setMidiaNova({ indice, tipo: n.kind === 'image' ? 'image' : 'video' });
                      setAdicionandoEm(null);

                      return;
                    }
                    adicionar(indice, n.texto);
                  }}
                  className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-apoio font-bold text-slate-600 hover:border-teal-400 hover:text-teal-700 transition-colors cursor-pointer"
                >
                  <n.icon className="h-3 w-3" /> {n.label}
                </button>
              ))}
              <button
                type="button"
                onClick={() => setAdicionandoEm(null)}
                className="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-apoio font-bold text-escult-ink-2 hover:text-slate-700 transition-colors cursor-pointer"
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
            <span className="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2 py-0.5 text-sobretitulo uppercase text-escult-ink-2 group-hover:border-teal-400 group-hover:text-teal-700 transition-colors">
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
          <label className="block text-sobretitulo text-escult-ink-2 uppercase">
            Conteúdo de estudo da aula
          </label>
          <p className="text-apoio text-escult-ink-2">
            Esta é a tela do aluno. Clique no lápis de um trecho para alterá-lo.
          </p>
        </div>
        <button
          type="button"
          onClick={() => setShowHelp((v) => !v)}
          className="inline-flex items-center gap-1 text-apoio font-bold text-teal-700 hover:text-teal-800 cursor-pointer"
        >
          <HelpCircle className="h-3 w-3" />
          Como funciona
          {showHelp ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
        </button>
      </div>

      {showHelp && (
        <div className="rounded-xl border border-teal-100 bg-teal-50/40 p-3 text-rotulo leading-relaxed text-slate-700 space-y-1.5">
          <p>O que você vê abaixo é exatamente o que o aluno vê. Para mudar algo:</p>
          <ul className="space-y-1 pl-4 list-disc marker:text-teal-700">
            <li>Passe o mouse sobre um trecho e clique no <strong>lápis</strong> para editá-lo.</li>
            <li>Clique em <strong>Adicionar</strong>, entre dois trechos, para inserir algo novo ali.</li>
            <li>A <strong>lixeira</strong> remove o trecho.</li>
            <li><strong>Seção</strong> numera a parte e cria o índice “Nesta aula”; <strong>Subtítulo</strong> separa assuntos dentro dela.</li>
            <li>Ao editar um trecho, o campo <strong>Tipo</strong> converte o que já está escrito — uma lista numerada vira <strong>Seção</strong> sem redigitar o texto. Use isso quando os títulos estiverem saindo todos como “1.”: a Seção numera 1, 2, 3 pela aula inteira, mesmo com parágrafos no meio.</li>
            <li><strong>Imagem</strong> põe uma figura no meio da aula, onde você clicar em Adicionar. A <strong>descrição é obrigatória</strong>: é o que o aluno recebe quando a imagem não carrega, e o que o leitor de tela lê em voz alta. A legenda (“Figura 1 - …”) aparece abaixo da figura.</li>
            <li><strong>Vídeo</strong> encaixa um vídeo do YouTube no meio da explicação. O vídeo <em>principal</em> da aula continua no campo do topo desta página — este aqui é complemento.</li>
            <li>Imagem enviada fica num endereço público: quem tiver o link abre o arquivo sem estar matriculado. <strong>Não use imagem para prova, gabarito ou dado pessoal.</strong></li>
          </ul>
        </div>
      )}

      <div className="rounded-xl border border-slate-200 bg-white p-4">
        {parsed.blocks.length === 0 && midiaNova !== null ? (
          <LessonMediaForm
            tipo={midiaNova.tipo}
            onUpload={onUpload}
            onCancel={() => setMidiaNova(null)}
            onConfirm={(texto) => { onChange(texto); setMidiaNova(null); }}
          />
        ) : parsed.blocks.length === 0 ? (
          <div className="py-6 text-center space-y-3">
            <p className="text-xs text-escult-ink-2 italic">Esta aula ainda não tem material escrito.</p>
            <div className="flex flex-wrap justify-center gap-1.5">
              {NOVOS_BLOCOS.map((n) => (
                <button
                  key={n.kind}
                  type="button"
                  onClick={() => {
                    if (n.texto === null) {
                      setMidiaNova({ indice: -1, tipo: n.kind === 'image' ? 'image' : 'video' });

                      return;
                    }
                    onChange(n.texto);
                  }}
                  className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-apoio font-bold text-slate-600 hover:border-teal-400 hover:text-teal-700 transition-colors cursor-pointer"
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
                    onUpload={onUpload}
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
                        className="rounded-lg border border-slate-200 bg-white p-1.5 text-escult-ink-2 shadow-3xs hover:border-[#540D6E]/40 hover:text-[#540D6E] transition-colors cursor-pointer"
                      >
                        <Pencil className="h-3 w-3" />
                      </button>
                      <button
                        type="button"
                        onClick={() => onChange(removeLessonBlock(value, block.range))}
                        aria-label={`Remover ${ROTULO[block.kind].toLowerCase()}`}
                        title={`Remover ${ROTULO[block.kind].toLowerCase()}`}
                        className="rounded-lg border border-slate-200 bg-white p-1.5 text-escult-ink-2 shadow-3xs hover:border-rose-300 hover:text-rose-600 transition-colors cursor-pointer"
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

      <div className="flex flex-wrap gap-3 text-apoio font-bold text-escult-ink-2">
        <span>{parsed.sections.filter((s) => s.level === 2).length} seções</span>
        <span>{parsed.blocks.length} {parsed.blocks.length === 1 ? 'trecho' : 'trechos'}</span>
        <span>{value.trim() === '' ? 0 : value.trim().split(/\s+/).length} palavras</span>
        {parsed.hasCode && <span className="text-teal-700">contém bloco de código</span>}
      </div>
    </div>
  );
};
