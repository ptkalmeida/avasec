/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Gestão dos documentos de UMA disciplina, aula por aula, numa página só.
 *
 * O que existia: um botão "Biblioteca Digital — Gestão de arquivos" que abria um
 * `fixed inset-0` chamado "Adicionar Recurso à Biblioteca". Três problemas:
 *
 * 1. Não gerenciava nada — só cadastrava. Não listava, não removia, não corrigia.
 * 2. Não era da disciplina: `LibraryItem` não tem `courseId`, é o acervo GERAL da
 *    escola. Quem clicava dentro de uma disciplina esperava os documentos dela.
 * 3. A flag `bibliotecaDigital` está desligada nos dois lados, então
 *    `/api/library` responde 404 e o que aquele formulário salvava ficava só no
 *    localStorage do navegador.
 *
 * Os documentos que SÃO da disciplina são os `LessonDocument`, vinculados a cada
 * aula — e só eram gerenciáveis uma aula por vez, dentro da página daquela aula.
 * Faltava a visão do conjunto: quais aulas têm material e quais estão sem.
 */

import React from 'react';
import {
  FileText, Plus, Trash2, ArrowLeft, ExternalLink, AlertTriangle, Upload, Link2,
} from 'lucide-react';
import { Lesson, LessonDocument } from '../../types';
import { safeHref, safeUrl } from '../../utils/safeUrl';

type Resultado = { ok: boolean; error?: string };

type TipoDoc = LessonDocument['type'];

interface DocumentosDisciplinaPageProps {
  courseTitle: string;
  /** Aulas da disciplina, na ordem em que o aluno as recebe. */
  lessons: Lesson[];
  /** Grava a lista completa de documentos daquela aula. */
  onSave: (lessonId: string, documents: LessonDocument[]) => Promise<Resultado>;
  /** Envia um arquivo e devolve a URL pública, ou um erro. */
  onUpload: (file: File) => Promise<{ ok: boolean; url?: string; error?: string }>;
  /** Confirmação destrutiva — a página não chama window.confirm direto. */
  confirmar: (pergunta: string) => boolean;
  notify: (mensagem: string) => void;
  onBack: () => void;
}

const TIPOS: { valor: TipoDoc; rotulo: string }[] = [
  { valor: 'pdf', rotulo: 'PDF' },
  { valor: 'doc', rotulo: 'Documento' },
  { valor: 'url', rotulo: 'Link' },
  { valor: 'drive', rotulo: 'Google Drive' },
  { valor: 'outro', rotulo: 'Outro' },
];

/** Cor do selo por tipo, igual à da página da aula. */
export const corDoTipo = (tipo: TipoDoc): string => {
  if (tipo === 'pdf') return 'bg-rose-50 text-rose-700 border border-rose-100/40';
  if (tipo === 'doc') return 'bg-blue-50 text-blue-700 border border-blue-100/40';
  if (tipo === 'url') return 'bg-amber-50 text-amber-700 border border-amber-100/40';
  if (tipo === 'drive') return 'bg-emerald-50 text-emerald-700 border border-emerald-100/40';

  return 'bg-slate-100 text-slate-700';
};

/** Tipo deduzido da extensão do arquivo enviado. */
export const tipoPorExtensao = (nome: string): TipoDoc => {
  const ext = nome.split('.').pop()?.toLowerCase();
  if (ext === 'pdf') return 'pdf';
  if (ext === 'doc' || ext === 'docx') return 'doc';

  return 'outro';
};

/** Tamanho legível a partir dos bytes, para não inventar "1.2 MB" fixo. */
export const tamanhoLegivel = (bytes: number): string => {
  if (!Number.isFinite(bytes) || bytes <= 0) return '';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;

  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

export interface RascunhoDoc {
  title: string;
  type: TipoDoc;
  url: string;
  size: string;
}

export const RASCUNHO_VAZIO: RascunhoDoc = { title: '', type: 'pdf', url: '', size: '' };

/**
 * Primeiro problema do rascunho, ou null.
 *
 * A URL é validada: o formulário antigo aceitava qualquer texto, e um
 * `javascript:` ali viraria link clicável na tela do aluno.
 */
export const problemaNoDocumento = (r: RascunhoDoc): string | null => {
  if (r.title.trim() === '') return 'Dê um título ao documento.';
  if (r.url.trim() === '') return 'Informe o link ou envie um arquivo.';
  // `safeUrl` aceita http(s) e caminho interno (/uploads/...), que é o que o
  // upload devolve, e recusa `javascript:` — inclusive a variante com espaço no
  // meio do esquema, que o navegador executa.
  if (safeUrl(r.url.trim()) === null) {
    return 'O endereço precisa ser um link http(s) válido ou um arquivo enviado.';
  }

  return null;
};

/** Rascunho -> documento, com id novo. */
export const paraDocumento = (r: RascunhoDoc): LessonDocument => ({
  id: `doc-${Date.now()}-${Math.round(Math.random() * 10000)}`,
  title: r.title.trim(),
  type: r.type,
  url: r.url.trim(),
  size: r.size.trim() === '' ? undefined : r.size.trim(),
});

const campo =
  'w-full rounded-lg border border-slate-200 p-2 text-[11px] text-slate-800 focus:ring-1 focus:ring-teal-500 focus:outline-hidden';
const rotulo = 'block text-[9px] font-bold text-slate-500 uppercase mb-1';

/** Bloco de uma aula: seus documentos e o formulário de anexo. */
const BlocoDaAula: React.FC<{
  lesson: Lesson;
  indice: number;
  onSave: DocumentosDisciplinaPageProps['onSave'];
  onUpload: DocumentosDisciplinaPageProps['onUpload'];
  confirmar: (p: string) => boolean;
  notify: (m: string) => void;
}> = ({ lesson, indice, onSave, onUpload, confirmar, notify }) => {
  const docs = lesson.documents ?? [];
  const [aberto, setAberto] = React.useState(false);
  const [rascunho, setRascunho] = React.useState<RascunhoDoc>(RASCUNHO_VAZIO);
  const [erro, setErro] = React.useState<string | null>(null);
  const [salvando, setSalvando] = React.useState(false);
  const [enviando, setEnviando] = React.useState(false);
  const [modo, setModo] = React.useState<'link' | 'upload'>('link');

  const anexar = async () => {
    const problema = problemaNoDocumento(rascunho);
    if (problema !== null) {
      setErro(problema);
      return;
    }
    setErro(null);
    setSalvando(true);
    const r = await onSave(lesson.id, [...docs, paraDocumento(rascunho)]);
    setSalvando(false);
    if (r.ok) {
      setRascunho(RASCUNHO_VAZIO);
      setAberto(false);
      notify(`Documento anexado à aula "${lesson.title}".`);
    } else {
      setErro(r.error ?? 'Não foi possível anexar o documento.');
    }
  };

  const remover = async (doc: LessonDocument) => {
    // Desvincular não apaga o arquivo enviado — só o tira desta aula.
    if (!confirmar(`Desvincular "${doc.title}" da aula "${lesson.title}"?`)) return;

    const r = await onSave(lesson.id, docs.filter((d) => d.id !== doc.id));
    if (r.ok) {
      notify('Documento desvinculado.');
    } else {
      setErro(r.error ?? 'Não foi possível desvincular o documento.');
    }
  };

  const enviarArquivo = async (file: File) => {
    setErro(null);
    setEnviando(true);
    const r = await onUpload(file);
    setEnviando(false);
    if (!r.ok || r.url === undefined) {
      setErro(r.error ?? 'Falha ao enviar o arquivo.');
      return;
    }
    setRascunho((prev) => ({
      ...prev,
      url: r.url as string,
      type: tipoPorExtensao(file.name),
      // Tamanho real do arquivo. O formulário antigo gravava "1.2 MB" fixo para
      // qualquer anexo, então o aluno lia um tamanho que não era o do arquivo.
      size: tamanhoLegivel(file.size),
      title: prev.title.trim() === '' ? file.name.replace(/\.[^.]+$/, '') : prev.title,
    }));
  };

  return (
    <li className="rounded-xl border border-slate-200 bg-white">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 p-3">
        <div className="min-w-0">
          <span className="text-[9px] font-black uppercase tracking-wider text-slate-400">
            Aula {indice + 1}
          </span>
          <strong className="block truncate text-xs font-black text-slate-900">{lesson.title}</strong>
        </div>
        <div className="flex items-center gap-2">
          <span
            className={`rounded px-2 py-0.5 text-[9px] font-black uppercase ${
              docs.length === 0 ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600'
            }`}
          >
            {docs.length === 0
              ? 'Sem material'
              : `${docs.length} ${docs.length === 1 ? 'documento' : 'documentos'}`}
          </span>
          <button
            type="button"
            onClick={() => {
              setAberto((v) => !v);
              setErro(null);
            }}
            className="inline-flex cursor-pointer items-center gap-1 rounded-lg bg-teal-600 px-2.5 py-1.5 text-[10px] font-bold text-white transition-colors hover:bg-teal-500"
          >
            <Plus className="h-3 w-3" /> Anexar
          </button>
        </div>
      </div>

      {docs.length > 0 && (
        <ul className="divide-y divide-slate-100">
          {docs.map((doc) => (
            <li key={doc.id} className="flex items-center justify-between gap-3 p-3 text-xs">
              <div className="flex min-w-0 items-center gap-3">
                <span className={`rounded px-2 py-0.5 text-[9px] font-black uppercase ${corDoTipo(doc.type)}`}>
                  {doc.type}
                </span>
                <div className="min-w-0">
                  <p className="truncate font-bold text-slate-800">{doc.title}</p>
                  <p className="truncate font-mono text-[9px] text-slate-400">
                    {doc.size ? `${doc.size} • ` : ''}
                    {doc.url}
                  </p>
                </div>
              </div>
              <div className="flex shrink-0 items-center gap-1">
                <a
                  href={safeHref(doc.url)}
                  target="_blank"
                  rel="noopener noreferrer"
                  referrerPolicy="no-referrer"
                  title="Abrir o documento"
                  className="rounded-md p-1.5 text-slate-500 transition-colors hover:bg-slate-50 hover:text-teal-600"
                >
                  <ExternalLink className="h-3.5 w-3.5" />
                </a>
                <button
                  type="button"
                  onClick={() => remover(doc)}
                  aria-label={`Desvincular ${doc.title}`}
                  className="cursor-pointer rounded-md p-1.5 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}

      {aberto && (
        <div className="space-y-3 border-t border-slate-100 bg-slate-50/60 p-3">
          <div className="flex gap-1 text-[10px] font-bold">
            <button
              type="button"
              onClick={() => setModo('link')}
              className={`inline-flex cursor-pointer items-center gap-1 rounded-lg px-2.5 py-1.5 transition-colors ${
                modo === 'link' ? 'bg-white text-teal-700 shadow-3xs' : 'text-slate-500 hover:text-slate-800'
              }`}
            >
              <Link2 className="h-3 w-3" /> Link
            </button>
            <button
              type="button"
              onClick={() => setModo('upload')}
              className={`inline-flex cursor-pointer items-center gap-1 rounded-lg px-2.5 py-1.5 transition-colors ${
                modo === 'upload' ? 'bg-white text-teal-700 shadow-3xs' : 'text-slate-500 hover:text-slate-800'
              }`}
            >
              <Upload className="h-3 w-3" /> Enviar arquivo
            </button>
          </div>

          <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <div>
              <label className={rotulo} htmlFor={`doc-titulo-${lesson.id}`}>
                Título do documento
              </label>
              <input
                id={`doc-titulo-${lesson.id}`}
                type="text"
                value={rascunho.title}
                onChange={(e) => setRascunho({ ...rascunho, title: e.target.value })}
                placeholder="Ex: Slides da aula 1"
                className={campo}
              />
            </div>
            <div>
              <label className={rotulo} htmlFor={`doc-tipo-${lesson.id}`}>
                Tipo
              </label>
              <select
                id={`doc-tipo-${lesson.id}`}
                value={rascunho.type}
                onChange={(e) => setRascunho({ ...rascunho, type: e.target.value as TipoDoc })}
                className={`${campo} bg-white font-semibold`}
              >
                {TIPOS.map((t) => (
                  <option key={t.valor} value={t.valor}>
                    {t.rotulo}
                  </option>
                ))}
              </select>
            </div>
          </div>

          {modo === 'link' ? (
            <div>
              <label className={rotulo} htmlFor={`doc-url-${lesson.id}`}>
                Endereço (http ou https)
              </label>
              <input
                id={`doc-url-${lesson.id}`}
                type="text"
                value={rascunho.url}
                onChange={(e) => setRascunho({ ...rascunho, url: e.target.value })}
                placeholder="https://..."
                className={`${campo} font-mono`}
              />
            </div>
          ) : (
            <div>
              <label className={rotulo} htmlFor={`doc-file-${lesson.id}`}>
                Arquivo
              </label>
              <input
                id={`doc-file-${lesson.id}`}
                type="file"
                onChange={(e) => {
                  const file = e.target.files?.[0];
                  if (file) void enviarArquivo(file);
                }}
                className="w-full cursor-pointer rounded-lg border border-slate-200 bg-white p-2 text-[11px]"
              />
              {enviando && <p className="mt-1 text-[10px] font-bold text-slate-500">Enviando arquivo...</p>}
              {!enviando && rascunho.url !== '' && (
                <p className="mt-1 truncate font-mono text-[9px] text-emerald-700">
                  Enviado: {rascunho.url}
                  {rascunho.size ? ` (${rascunho.size})` : ''}
                </p>
              )}
            </div>
          )}

          {erro !== null && (
            <p
              role="alert"
              className="flex items-start gap-1.5 rounded-lg border border-rose-200 bg-rose-50 p-2 text-[10px] font-bold text-rose-700"
            >
              <AlertTriangle className="mt-px h-3 w-3 shrink-0" />
              {erro}
            </p>
          )}

          <div className="flex justify-end gap-2">
            <button
              type="button"
              onClick={() => {
                setAberto(false);
                setRascunho(RASCUNHO_VAZIO);
                setErro(null);
              }}
              className="cursor-pointer rounded-lg bg-slate-100 px-3 py-1.5 text-[10px] font-semibold text-slate-600 transition-colors hover:bg-slate-200"
            >
              Cancelar
            </button>
            <button
              type="button"
              onClick={anexar}
              disabled={salvando || enviando}
              className="cursor-pointer rounded-lg bg-teal-600 px-3 py-1.5 text-[10px] font-bold text-white transition-colors hover:bg-teal-500 disabled:cursor-not-allowed disabled:bg-teal-300"
            >
              {salvando ? 'Anexando...' : 'Anexar documento'}
            </button>
          </div>
        </div>
      )}
    </li>
  );
};

export const DocumentosDisciplinaPage: React.FC<DocumentosDisciplinaPageProps> = ({
  courseTitle,
  lessons,
  onSave,
  onUpload,
  confirmar,
  notify,
  onBack,
}) => {
  const ordenadas = React.useMemo(
    () => lessons.slice().sort((a, b) => (a.order ?? 0) - (b.order ?? 0)),
    [lessons]
  );
  const total = ordenadas.reduce((acc, l) => acc + (l.documents?.length ?? 0), 0);
  const semMaterial = ordenadas.filter((l) => (l.documents?.length ?? 0) === 0).length;

  return (
    <section className="space-y-5 text-left">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <button
          type="button"
          onClick={onBack}
          className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-[11px] font-bold text-slate-600 transition-colors hover:bg-slate-200"
        >
          <ArrowLeft className="h-3.5 w-3.5" /> Voltar à gestão
        </button>
        <div className="text-right">
          <h3 className="flex items-center justify-end gap-2 text-sm font-black text-slate-900">
            <FileText className="h-4 w-4 text-teal-600" />
            Documentos da disciplina
          </h3>
          <p className="text-[10px] font-semibold text-slate-500">{courseTitle}</p>
        </div>
      </div>

      <div className="flex flex-wrap gap-3">
        <div className="rounded-xl border border-slate-200 bg-white px-4 py-2">
          <span className="block text-[9px] font-black uppercase tracking-wider text-slate-400">
            Documentos
          </span>
          <strong className="font-mono text-lg font-bold text-slate-900">{total}</strong>
        </div>
        <div className="rounded-xl border border-slate-200 bg-white px-4 py-2">
          <span className="block text-[9px] font-black uppercase tracking-wider text-slate-400">
            Aulas
          </span>
          <strong className="font-mono text-lg font-bold text-slate-900">{ordenadas.length}</strong>
        </div>
        {/*
          Aula sem material é a informação que a gestão por aula não dava: era
          preciso abrir uma por uma para descobrir onde falta.
        */}
        {semMaterial > 0 && (
          <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2">
            <span className="block text-[9px] font-black uppercase tracking-wider text-amber-700">
              Aulas sem material
            </span>
            <strong className="font-mono text-lg font-bold text-amber-800">{semMaterial}</strong>
          </div>
        )}
      </div>

      {ordenadas.length === 0 ? (
        <p className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-xs font-semibold text-slate-500">
          Esta disciplina ainda não tem aulas. Crie as aulas na Grade Curricular para
          poder anexar material a elas.
        </p>
      ) : (
        <ul className="space-y-3">
          {ordenadas.map((lesson, i) => (
            <BlocoDaAula
              key={lesson.id}
              lesson={lesson}
              indice={i}
              onSave={onSave}
              onUpload={onUpload}
              confirmar={confirmar}
              notify={notify}
            />
          ))}
        </ul>
      )}
    </section>
  );
};
