/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useEffect, useState } from 'react';
import {
  Pencil, X, Save, ArrowLeft, Video, FileText, BookOpen, Clock, Paperclip, Eye
} from 'lucide-react';
import { Lesson } from '../../types';
import { parseLessonContent } from '../../utils/lessonContent';
import { LessonContent } from '../student/LessonContent';
import { LessonVideoField } from '../shared/LessonVideoField';
import { VideoPlayer } from '../shared/VideoPlayer';
import { parseVideoSource } from '../../utils/videoSource';
import { LessonContentEditor } from './LessonContentEditor';

/** Zonas que o lápis abre. Só uma fica aberta por vez, para o foco não se perder. */
type Zone = 'header' | 'video' | 'content' | 'docs' | null;

interface LessonManagePageProps {
  lesson: Lesson;
  courseTitle: string;
  courseCategory: string;
  totalLessons: number;
  onBack: () => void;
  onSaveHeader: (title: string, duration: string) => void;
  onSaveVideo: (videoUrl: string) => void;
  onSaveContent: (content: string) => void;
  onToggleOptional: () => void;
  /** Lista + formulário de anexos, montados pelo painel (o estado de upload é dele). */
  documentsSlot: React.ReactNode;
}

/** Cabeçalho de cada bloco, com o lápis que alterna entre ver e editar. */
const ZoneHeader: React.FC<{
  icon: React.ReactNode;
  title: string;
  hint?: string;
  editing: boolean;
  onToggle: () => void;
}> = ({ icon, title, hint, editing, onToggle }) => (
  <div className="flex items-start justify-between gap-3 border-b border-slate-150 px-4 py-2.5">
    <div className="min-w-0">
      <span className="flex items-center gap-1.5 text-[11px] font-black uppercase tracking-widest text-slate-700">
        {icon}
        {title}
      </span>
      {hint && <p className="mt-0.5 text-[10px] text-slate-400">{hint}</p>}
    </div>
    <button
      type="button"
      onClick={onToggle}
      aria-label={editing ? `Fechar edição de ${title}` : `Editar ${title}`}
      title={editing ? 'Fechar edição' : `Editar ${title.toLowerCase()}`}
      className={`shrink-0 inline-flex items-center gap-1 rounded-xl border px-2.5 py-1.5 text-[10px] font-black uppercase tracking-wider transition-colors cursor-pointer ${
        editing
          ? 'border-slate-300 bg-slate-900 text-white hover:bg-slate-800'
          : 'border-slate-200 bg-white text-slate-600 hover:border-[#540D6E]/40 hover:text-[#540D6E]'
      }`}
    >
      {editing ? <X className="h-3.5 w-3.5" /> : <Pencil className="h-3.5 w-3.5" />}
      <span className="hidden sm:inline">{editing ? 'Fechar' : 'Editar'}</span>
    </button>
  </div>
);

/**
 * Página de gestão de UMA aula: mostra os blocos na mesma ordem em que o aluno os
 * recebe (vídeo, material didático, anexos) e cada um abre para edição pelo lápis.
 * Substitui o painel que expandia dentro da lista — a mesma tarefa não fica em
 * dois lugares.
 */
export const LessonManagePage: React.FC<LessonManagePageProps> = ({
  lesson, courseTitle, courseCategory, totalLessons, onBack,
  onSaveHeader, onSaveVideo, onSaveContent, onToggleOptional, documentsSlot,
}) => {
  const [zone, setZone] = useState<Zone>(null);

  const [title, setTitle] = useState(lesson.title);
  const [duration, setDuration] = useState(lesson.duration);
  const [videoUrl, setVideoUrl] = useState(lesson.videoUrl || '');
  const [content, setContent] = useState(lesson.content || '');

  // Trocar de aula sem sair da página precisa recarregar os rascunhos.
  useEffect(() => {
    setTitle(lesson.title);
    setDuration(lesson.duration);
    setVideoUrl(lesson.videoUrl || '');
    setContent(lesson.content || '');
    setZone(null);
  }, [lesson.id, lesson.title, lesson.duration, lesson.videoUrl, lesson.content]);

  const parsed = parseLessonContent(lesson.content || '');
  // Mesmo critério do aluno (ADR 08): URL inválida não conta como vídeo.
  const temVideo = parseVideoSource(lesson.videoUrl) !== null;
  const docs = lesson.documents || [];

  const toggle = (alvo: Zone) => setZone((atual) => (atual === alvo ? null : alvo));
  const caixa = 'rounded-2xl border border-slate-200 bg-white shadow-3xs overflow-hidden';
  const salvar = 'inline-flex items-center gap-1.5 rounded-xl bg-teal-600 hover:bg-teal-500 px-4 py-2 text-[10px] font-black uppercase tracking-wider text-white shadow-xs transition-colors cursor-pointer';

  return (
    <div className="space-y-5 text-left animate-in fade-in duration-200">
      {/* Barra da página */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <button
          type="button"
          onClick={onBack}
          className="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-slate-900 transition-colors cursor-pointer"
        >
          <ArrowLeft className="h-4 w-4" />
          Voltar ao currículo
        </button>

        <div className="flex items-center gap-2">
          <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-widest text-slate-500 border border-slate-200">
            {courseCategory}
          </span>
          <button
            type="button"
            onClick={onToggleOptional}
            title="Aula opcional não entra no cálculo de frequência obrigatória"
            className={`rounded-full px-3 py-1 text-[9px] font-black uppercase tracking-wider transition-colors cursor-pointer ${
              lesson.isOptional
                ? 'bg-slate-200 text-slate-700 hover:bg-slate-300'
                : 'bg-[#540D6E]/10 text-[#540D6E] border border-purple-300/30 hover:bg-[#540D6E]/15'
            }`}
          >
            {lesson.isOptional ? 'Aula opcional' : 'Aula obrigatória'}
          </button>
        </div>
      </div>

      {/* 1. Cabeçalho da aula */}
      <div className={caixa}>
        <ZoneHeader
          icon={<BookOpen className="h-3.5 w-3.5 text-teal-600" />}
          title="Identificação da aula"
          hint={`${courseTitle} • aula ${lesson.order} de ${totalLessons}`}
          editing={zone === 'header'}
          onToggle={() => toggle('header')}
        />

        {zone === 'header' ? (
          <div className="p-4 space-y-3">
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div className="sm:col-span-2">
                <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1 tracking-wider">Título da aula</label>
                <input
                  type="text"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  className="w-full rounded-xl border border-slate-200 p-2.5 text-sm font-semibold focus:outline-hidden focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500"
                />
              </div>
              <div>
                <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1 tracking-wider">Carga</label>
                <input
                  type="text"
                  value={duration}
                  onChange={(e) => setDuration(e.target.value)}
                  placeholder="20 min"
                  className="w-full rounded-xl border border-slate-200 p-2.5 text-sm font-mono focus:outline-hidden focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500"
                />
              </div>
            </div>
            <div className="flex justify-end">
              <button
                type="button"
                className={salvar}
                onClick={() => {
                  onSaveHeader(title.trim(), duration.trim());
                  setZone(null);
                }}
              >
                <Save className="h-3.5 w-3.5" /> Salvar identificação
              </button>
            </div>
          </div>
        ) : (
          <div className="p-4">
            <h3 className="text-lg md:text-xl font-black text-slate-900 font-serif leading-tight">{lesson.title}</h3>
            <div className="mt-1 flex flex-wrap items-center gap-3 text-[11px] font-bold text-teal-700">
              <span className="inline-flex items-center gap-1">
                <Clock className="h-3 w-3" />
                {lesson.duration || 'sem carga definida'}
              </span>
              <span className="inline-flex items-center gap-1 text-slate-500">
                <Paperclip className="h-3 w-3" />
                {docs.length} {docs.length === 1 ? 'documento' : 'documentos'}
              </span>
            </div>
          </div>
        )}
      </div>

      {/* 2. Vídeo */}
      <div className={caixa}>
        <ZoneHeader
          icon={<Video className="h-3.5 w-3.5 text-teal-600" />}
          title="Vídeo da aula"
          hint={temVideo ? 'O aluno vê o player no topo da aula.' : 'Sem vídeo: a aula abre como conteúdo de leitura.'}
          editing={zone === 'video'}
          onToggle={() => toggle('video')}
        />

        {zone === 'video' ? (
          <div className="p-4 space-y-3">
            <LessonVideoField value={videoUrl} onChange={setVideoUrl} />
            <div className="flex justify-end">
              <button
                type="button"
                className={salvar}
                onClick={() => {
                  onSaveVideo(videoUrl.trim());
                  setZone(null);
                }}
              >
                <Save className="h-3.5 w-3.5" /> Salvar vídeo
              </button>
            </div>
          </div>
        ) : (
          <div className="p-4">
            {temVideo ? (
              <div className="mx-auto max-w-2xl overflow-hidden rounded-xl border border-slate-800 bg-slate-950">
                <VideoPlayer videoUrl={lesson.videoUrl} title={lesson.title} />
              </div>
            ) : (
              <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50/60 p-6 text-center">
                <BookOpen className="mx-auto mb-1.5 h-6 w-6 text-slate-300" />
                <p className="text-xs font-bold text-slate-600">Aula sem vídeo</p>
                <p className="mt-0.5 text-[10px] text-slate-400">
                  O aluno recebe esta aula como leitura. Use o lápis para anexar um vídeo.
                </p>
              </div>
            )}
          </div>
        )}
      </div>

      {/* 3. Material didático */}
      <div className={caixa}>
        <ZoneHeader
          icon={<FileText className="h-3.5 w-3.5 text-teal-600" />}
          title="Material didático"
          hint={`${parsed.sections.filter((s) => s.level === 2).length} seções${parsed.hasCode ? ' • contém bloco de código' : ''}`}
          editing={zone === 'content'}
          onToggle={() => toggle('content')}
        />

        {zone === 'content' ? (
          <div className="p-4 space-y-3">
            <LessonContentEditor value={content} onChange={setContent} />
            <div className="flex justify-end">
              <button
                type="button"
                className={salvar}
                onClick={() => {
                  onSaveContent(content.trim());
                  setZone(null);
                }}
              >
                <Save className="h-3.5 w-3.5" /> Salvar material
              </button>
            </div>
          </div>
        ) : (
          <div className="p-4">
            <span className="mb-2.5 flex items-center gap-1 text-[9px] font-black uppercase tracking-widest text-slate-400">
              <Eye className="h-2.5 w-2.5" /> Como o aluno vê
            </span>
            {parsed.blocks.length === 0 ? (
              <p className="text-xs italic text-slate-400">
                Esta aula ainda não tem material escrito. Use o lápis para começar.
              </p>
            ) : (
              <LessonContent blocks={parsed.blocks} />
            )}
          </div>
        )}
      </div>

      {/* 4. Documentos anexos */}
      <div className={caixa}>
        <ZoneHeader
          icon={<Paperclip className="h-3.5 w-3.5 text-teal-600" />}
          title={`Material de apoio (${docs.length})`}
          hint="Arquivos e links que o aluno abre no fim da aula."
          editing={zone === 'docs'}
          onToggle={() => toggle('docs')}
        />

        {zone === 'docs' ? (
          <div className="p-4">{documentsSlot}</div>
        ) : (
          <div className="p-4">
            {docs.length === 0 ? (
              <p className="text-xs italic text-slate-400">
                Nenhum documento vinculado. Use o lápis para anexar arquivos ou links.
              </p>
            ) : (
              <ul className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                {docs.map((doc) => (
                  <li
                    key={doc.id}
                    className="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5"
                  >
                    <span className="rounded bg-white px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-500 border border-slate-200">
                      {doc.type}
                    </span>
                    <span className="min-w-0 flex-1 truncate text-xs font-bold text-slate-800">{doc.title}</span>
                    {doc.size && <span className="shrink-0 font-mono text-[9px] text-slate-400">{doc.size}</span>}
                  </li>
                ))}
              </ul>
            )}
          </div>
        )}
      </div>
    </div>
  );
};
