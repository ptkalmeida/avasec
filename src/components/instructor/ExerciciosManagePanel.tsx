/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import {
  FileCheck, Plus, Pencil, Trash2, Save, X, AlertTriangle, Clock,
  CheckCircle, HelpCircle, FileText, Inbox,
} from 'lucide-react';
import { Course, PracticalExercise, ExerciseSubmission } from '../../types';
import { exerciciosDoCurso, filaDeCorrecao, parseDataBr, prazoDe } from '../../utils/exerciseStatus';

/**
 * Área de exercícios práticos de quem dá aula: lançar, editar, remover e
 * corrigir entregas.
 *
 * Isto não existia. A gestão de exercícios morava só no painel do admin, e o
 * professor — que é quem conhece a turma e a atividade — não tinha por onde
 * lançar nem corrigir. O backend já escopa por posse do curso, então o que
 * chega aqui em `courses` é o que esta pessoa pode mexer.
 */

type Resultado = { ok: boolean; error?: string };

interface ExerciciosManagePanelProps {
  /** Cursos que esta pessoa administra — a lista já vem filtrada. */
  courses: Course[];
  exercises: PracticalExercise[];
  submissions: ExerciseSubmission[];
  onCreate: (
    courseId: string, title: string, description: string,
    instructions: string, maxPoints: number, dueDate?: string
  ) => Promise<Resultado>;
  onUpdate: (exerciseId: string, updates: Partial<PracticalExercise>) => Promise<Resultado>;
  onDelete: (exerciseId: string) => Promise<Resultado>;
  onGrade: (
    submissionId: string, score: number, feedback: string,
    status: 'approved' | 'rejected' | 'revision'
  ) => Promise<Resultado>;
  /** Confirmação destrutiva — o painel não chama window.confirm direto. */
  confirmar: (pergunta: string) => boolean;
  notify: (mensagem: string) => void;
}

interface Rascunho {
  title: string;
  description: string;
  instructions: string;
  maxPoints: string;
  dueDate: string;
}

const RASCUNHO_VAZIO: Rascunho = {
  title: '', description: '', instructions: '', maxPoints: '100', dueDate: '',
};

/** Valida o rascunho e devolve o primeiro problema, ou null. */
export const problemaNoRascunho = (r: Rascunho): string | null => {
  if (r.title.trim() === '') return 'Dê um título ao exercício.';
  if (r.description.trim() === '') return 'Escreva a descrição (o objetivo da atividade).';
  if (r.instructions.trim() === '') return 'Escreva as instruções de entrega.';

  const pontos = Number(r.maxPoints);
  if (!Number.isInteger(pontos) || pontos < 1 || pontos > 1000) {
    return 'A nota máxima deve ser um número inteiro entre 1 e 1000.';
  }
  // Prazo é opcional, mas se vier tem de ser dd/mm/aaaa: como texto livre, um
  // "julho" entraria no banco e a página do aluno nunca mostraria vencimento.
  if (r.dueDate.trim() !== '' && parseDataBr(r.dueDate) === null) {
    return 'O prazo deve estar no formato dd/mm/aaaa (ou ficar em branco).';
  }

  return null;
};

/** Correção de UMA entrega: nota, feedback e o desfecho. */
const CorrigirEntrega: React.FC<{
  submissao: ExerciseSubmission;
  maxPoints: number;
  onGrade: ExerciciosManagePanelProps['onGrade'];
  notify: (m: string) => void;
}> = ({ submissao, maxPoints, onGrade, notify }) => {
  const [aberto, setAberto] = React.useState(submissao.status === 'pending');
  const [nota, setNota] = React.useState(String(submissao.score ?? ''));
  const [feedback, setFeedback] = React.useState(submissao.feedback ?? '');
  const [erro, setErro] = React.useState<string | null>(null);
  const [salvando, setSalvando] = React.useState(false);

  const lancar = async (status: 'approved' | 'rejected' | 'revision') => {
    const valor = Number(nota);
    if (nota.trim() === '' || !Number.isFinite(valor) || valor < 0 || valor > maxPoints) {
      setErro(`Informe uma nota entre 0 e ${maxPoints}.`);
      return;
    }
    if (status !== 'approved' && feedback.trim() === '') {
      // Devolver trabalho sem dizer o que corrigir não ajuda ninguém.
      setErro('Ao pedir ajustes ou reprovar, escreva o feedback para o aluno.');
      return;
    }
    setSalvando(true);
    setErro(null);
    const res = await onGrade(submissao.id, valor, feedback.trim(), status);
    setSalvando(false);
    if (!res.ok) {
      setErro(res.error ?? 'Não foi possível lançar a correção.');
      return;
    }
    setAberto(false);
    notify('Correção registrada. O aluno já vê a nota e o feedback.');
  };

  const chip = submissao.status === 'approved'
    ? { classe: 'bg-emerald-50 text-emerald-800 border-emerald-200', icone: <CheckCircle className="h-3 w-3" />, texto: `Aprovado · ${submissao.score ?? 0}/${maxPoints}` }
    : submissao.status === 'pending'
      ? { classe: 'bg-indigo-50 text-indigo-800 border-indigo-200', icone: <Clock className="h-3 w-3" />, texto: 'Aguardando correção' }
      : submissao.status === 'revision'
        ? { classe: 'bg-amber-50 text-amber-800 border-amber-200', icone: <HelpCircle className="h-3 w-3" />, texto: 'Ajustes solicitados' }
        : { classe: 'bg-rose-50 text-rose-800 border-rose-200', icone: <AlertTriangle className="h-3 w-3" />, texto: 'Reprovado' };

  return (
    <li className="space-y-2 rounded-xl border border-slate-200 bg-white p-3">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <strong className="block text-[11px] font-bold text-slate-900">{submissao.studentName}</strong>
          <span className="block font-mono text-[9px] text-slate-450">
            entregue em {submissao.submittedAt}
          </span>
        </div>
        <span className={`inline-flex shrink-0 items-center gap-1 rounded-md border px-2 py-0.5 text-[9px] font-black uppercase ${chip.classe}`}>
          {chip.icone}
          {chip.texto}
        </span>
      </div>

      <p className="max-h-32 overflow-y-auto whitespace-pre-wrap rounded-lg border border-slate-150 bg-slate-50 p-2 font-mono text-[10px] leading-normal text-slate-700">
        {submissao.submissionText}
      </p>

      {submissao.fileName && (
        <p className="flex items-center gap-1.5 text-[10px] text-slate-600">
          <FileText className="h-3.5 w-3.5 shrink-0 text-slate-450" />
          <span className="min-w-0 truncate">Anexo: {submissao.fileName}</span>
        </p>
      )}

      {aberto ? (
        <div className="space-y-2 border-t border-slate-100 pt-2">
          <div className="flex flex-wrap items-end gap-2">
            <label className="space-y-1">
              <span className="block text-[10px] font-bold uppercase tracking-wide text-slate-500">
                Nota (0 a {maxPoints})
              </span>
              <input
                type="number"
                min={0}
                max={maxPoints}
                value={nota}
                onChange={(e) => { setNota(e.target.value); setErro(null); }}
                className="w-24 rounded-lg border border-slate-300 p-2 font-mono text-xs text-slate-800"
              />
            </label>
          </div>

          <label className="block space-y-1">
            <span className="block text-[10px] font-bold uppercase tracking-wide text-slate-500">
              Feedback para o aluno
            </span>
            <textarea
              value={feedback}
              onChange={(e) => { setFeedback(e.target.value); setErro(null); }}
              rows={3}
              placeholder="O que ficou bom, o que precisa mudar e por quê."
              className="w-full rounded-lg border border-slate-300 p-2 text-xs text-slate-800"
            />
          </label>

          {erro !== null && (
            <p className="flex items-start gap-1.5 rounded-lg border border-rose-200 bg-rose-50 p-2 text-[10px] font-bold leading-relaxed text-rose-700">
              <AlertTriangle className="mt-px h-3 w-3 shrink-0" />
              {erro}
            </p>
          )}

          <div className="flex flex-wrap gap-1.5">
            <button
              type="button"
              disabled={salvando}
              onClick={() => lancar('approved')}
              className="cursor-pointer rounded-lg bg-emerald-600 px-3 py-1.5 text-[10px] font-black uppercase text-white hover:bg-emerald-500 disabled:opacity-60"
            >
              Aprovar
            </button>
            <button
              type="button"
              disabled={salvando}
              onClick={() => lancar('revision')}
              className="cursor-pointer rounded-lg bg-amber-500 px-3 py-1.5 text-[10px] font-black uppercase text-white hover:bg-amber-400 disabled:opacity-60"
            >
              Pedir ajustes
            </button>
            <button
              type="button"
              disabled={salvando}
              onClick={() => lancar('rejected')}
              className="cursor-pointer rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-[10px] font-black uppercase text-rose-700 hover:bg-rose-50 disabled:opacity-60"
            >
              Reprovar
            </button>
            <button
              type="button"
              onClick={() => setAberto(false)}
              className="ml-auto cursor-pointer rounded-lg px-2 py-1.5 text-[10px] font-bold uppercase text-slate-500 hover:text-slate-800"
            >
              Fechar
            </button>
          </div>
        </div>
      ) : (
        <button
          type="button"
          onClick={() => setAberto(true)}
          className="cursor-pointer text-[10px] font-black uppercase tracking-wider text-[#540D6E] hover:underline"
        >
          {submissao.status === 'pending' ? 'Corrigir entrega' : 'Rever correção'}
        </button>
      )}
    </li>
  );
};

export const ExerciciosManagePanel: React.FC<ExerciciosManagePanelProps> = ({
  courses, exercises, submissions, onCreate, onUpdate, onDelete, onGrade, confirmar, notify,
}) => {
  const [courseId, setCourseId] = React.useState(courses[0]?.id ?? '');
  const [formAberto, setFormAberto] = React.useState(false);
  const [editandoId, setEditandoId] = React.useState<string | null>(null);
  const [rascunho, setRascunho] = React.useState<Rascunho>(RASCUNHO_VAZIO);
  const [erro, setErro] = React.useState<string | null>(null);
  const [salvando, setSalvando] = React.useState(false);

  const agora = React.useMemo(() => new Date(), [exercises, submissions]);
  const doCurso = React.useMemo(
    () => exerciciosDoCurso(exercises, courseId),
    [exercises, courseId]
  );
  const fila = React.useMemo(() => filaDeCorrecao(doCurso, submissions), [doCurso, submissions]);

  const abrirNovo = () => {
    setEditandoId(null);
    setRascunho(RASCUNHO_VAZIO);
    setErro(null);
    setFormAberto(true);
  };

  const abrirEdicao = (ex: PracticalExercise) => {
    setEditandoId(ex.id);
    setRascunho({
      title: ex.title,
      description: ex.description,
      instructions: ex.instructions,
      maxPoints: String(ex.maxPoints),
      dueDate: ex.dueDate ?? '',
    });
    setErro(null);
    setFormAberto(true);
  };

  const salvar = async () => {
    const problema = problemaNoRascunho(rascunho);
    if (problema !== null) { setErro(problema); return; }
    if (courseId === '') { setErro('Selecione o curso do exercício.'); return; }

    setSalvando(true);
    setErro(null);
    const dueDate = rascunho.dueDate.trim() === '' ? undefined : rascunho.dueDate.trim();
    const res = editandoId === null
      ? await onCreate(
        courseId, rascunho.title.trim(), rascunho.description.trim(),
        rascunho.instructions.trim(), Number(rascunho.maxPoints), dueDate
      )
      : await onUpdate(editandoId, {
        courseId,
        title: rascunho.title.trim(),
        description: rascunho.description.trim(),
        instructions: rascunho.instructions.trim(),
        maxPoints: Number(rascunho.maxPoints),
        dueDate,
      });
    setSalvando(false);

    if (!res.ok) {
      // Antes a tela dizia "lançado com sucesso!" sem consultar a resposta.
      setErro(res.error ?? 'Não foi possível salvar o exercício.');
      return;
    }
    notify(editandoId === null ? 'Exercício publicado para a turma.' : 'Exercício atualizado.');
    setFormAberto(false);
    setEditandoId(null);
    setRascunho(RASCUNHO_VAZIO);
  };

  const remover = async (ex: PracticalExercise) => {
    const entregas = submissions.filter((s) => s.exerciseId === ex.id).length;
    const aviso = entregas > 0
      ? `Remover "${ex.title}"? ${entregas} entrega(s) de alunos serão apagadas junto.`
      : `Remover "${ex.title}"?`;
    if (!confirmar(aviso)) return;

    const res = await onDelete(ex.id);
    notify(res.ok ? 'Exercício removido.' : (res.error ?? 'Não foi possível remover.'));
  };

  const campo = (
    rotulo: string,
    chave: keyof Rascunho,
    extra: { linhas?: number; placeholder?: string; largura?: string } = {}
  ) => (
    <label className={`space-y-1 ${extra.largura ?? ''}`}>
      <span className="block text-[10px] font-bold uppercase tracking-wide text-slate-500">{rotulo}</span>
      {extra.linhas ? (
        <textarea
          value={rascunho[chave]}
          onChange={(e) => { setRascunho((r) => ({ ...r, [chave]: e.target.value })); setErro(null); }}
          rows={extra.linhas}
          placeholder={extra.placeholder}
          className="w-full rounded-lg border border-slate-300 p-2.5 text-xs text-slate-800"
        />
      ) : (
        <input
          type="text"
          value={rascunho[chave]}
          onChange={(e) => { setRascunho((r) => ({ ...r, [chave]: e.target.value })); setErro(null); }}
          placeholder={extra.placeholder}
          className="w-full rounded-lg border border-slate-300 p-2.5 text-xs text-slate-800"
        />
      )}
    </label>
  );

  return (
    <section className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-sm">
      <header className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-3">
        <div className="space-y-1">
          <h4 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-slate-800">
            <FileCheck className="h-4 w-4 text-teal-600" />
            <span>Exercícios Práticos</span>
          </h4>
          <p className="text-[11px] leading-relaxed text-slate-500">
            Lance atividades e corrija as entregas dos seus cursos. O aluno vê nota e
            feedback na página de exercícios do curso.
          </p>
        </div>

        <button
          type="button"
          onClick={abrirNovo}
          className="inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-xl bg-slate-900 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white hover:bg-slate-800"
        >
          <Plus className="h-3.5 w-3.5" />
          Novo exercício
        </button>
      </header>

      <div className="flex flex-wrap items-end gap-3">
        <label className="min-w-[14rem] flex-1 space-y-1">
          <span className="block text-[10px] font-bold uppercase tracking-wide text-slate-500">Curso</span>
          <select
            value={courseId}
            onChange={(e) => setCourseId(e.target.value)}
            className="w-full rounded-lg border border-slate-300 bg-white p-2.5 text-xs text-slate-800"
          >
            {courses.length === 0 && <option value="">Nenhum curso sob sua gestão</option>}
            {courses.map((c) => (
              <option key={c.id} value={c.id}>{c.title}</option>
            ))}
          </select>
        </label>

        {/* O número que importa para quem corrige: quantas esperam por você. */}
        <div className="flex gap-2">
          <span className={`rounded-xl border px-3 py-2 text-[10px] font-black uppercase ${
            fila.aguardando > 0
              ? 'border-indigo-200 bg-indigo-50 text-indigo-800'
              : 'border-slate-200 bg-slate-50 text-slate-500'
          }`}>
            {fila.aguardando} para corrigir
          </span>
          <span className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-[10px] font-black uppercase text-slate-500">
            {fila.corrigidas} corrigidas
          </span>
        </div>
      </div>

      {formAberto && (
        <div className="space-y-3 rounded-2xl border border-slate-250 bg-slate-50/60 p-4">
          <div className="flex items-center justify-between">
            <strong className="text-[11px] font-black uppercase tracking-wider text-slate-700">
              {editandoId === null ? 'Novo exercício' : 'Editar exercício'}
            </strong>
            <button
              type="button"
              onClick={() => { setFormAberto(false); setEditandoId(null); setErro(null); }}
              className="cursor-pointer text-slate-400 hover:text-slate-700"
              aria-label="Fechar formulário"
            >
              <X className="h-4 w-4" />
            </button>
          </div>

          <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
            {campo('Título', 'title', { placeholder: 'Ex: Análise de heurísticas de usabilidade' })}
            {campo('Nota máxima', 'maxPoints', { placeholder: '100' })}
            {campo('Descrição (objetivo)', 'description', { linhas: 2, largura: 'md:col-span-2', placeholder: 'Para que serve esta atividade.' })}
            {campo('Instruções de entrega', 'instructions', { linhas: 4, largura: 'md:col-span-2', placeholder: 'O que entregar, em que formato, e como será avaliado.' })}
            {campo('Prazo (opcional)', 'dueDate', { placeholder: 'dd/mm/aaaa' })}
          </div>

          {erro !== null && (
            <p className="flex items-start gap-1.5 rounded-lg border border-rose-200 bg-rose-50 p-2.5 text-[10.5px] font-bold leading-relaxed text-rose-700">
              <AlertTriangle className="mt-px h-3.5 w-3.5 shrink-0" />
              {erro}
            </p>
          )}

          <div className="flex justify-end gap-2 border-t border-slate-200 pt-2">
            <button
              type="button"
              onClick={() => { setFormAberto(false); setEditandoId(null); setErro(null); }}
              className="cursor-pointer rounded-xl bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100"
            >
              Cancelar
            </button>
            <button
              type="button"
              onClick={salvar}
              disabled={salvando}
              className="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-[#540D6E] px-4 py-1.5 text-xs font-bold text-white hover:bg-purple-950 disabled:opacity-60"
            >
              <Save className="h-3.5 w-3.5" />
              {salvando ? 'Salvando...' : editandoId === null ? 'Publicar' : 'Salvar'}
            </button>
          </div>
        </div>
      )}

      {doCurso.length === 0 ? (
        <p className="rounded-xl border border-dashed border-slate-250 p-6 text-center text-[11px] leading-relaxed text-slate-500">
          Nenhum exercício neste curso. Os que você lançar aparecem na página de
          exercícios práticos do aluno, com prazo e espaço para entrega.
        </p>
      ) : (
        <ul className="space-y-3">
          {doCurso.map((ex) => {
            const entregas = submissions.filter((s) => s.exerciseId === ex.id);
            const prazo = prazoDe(ex.dueDate, agora);

            return (
              <li key={ex.id} className="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/40 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div className="min-w-0 space-y-0.5">
                    <strong className="block text-xs font-bold leading-snug text-slate-900">{ex.title}</strong>
                    <span className="block text-[10px] text-slate-500">
                      {ex.maxPoints} pts
                      {prazo !== null && (
                        <span className={prazo.atrasado ? ' text-rose-600 font-bold' : ''}> · {prazo.texto}</span>
                      )}
                      {' · '}
                      {entregas.length} {entregas.length === 1 ? 'entrega' : 'entregas'}
                    </span>
                  </div>

                  <div className="flex shrink-0 items-center gap-1">
                    <button
                      type="button"
                      onClick={() => abrirEdicao(ex)}
                      title="Editar exercício"
                      className="cursor-pointer rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-indigo-50 hover:text-indigo-600"
                    >
                      <Pencil className="h-3.5 w-3.5" />
                    </button>
                    <button
                      type="button"
                      onClick={() => remover(ex)}
                      title="Remover exercício"
                      className="cursor-pointer rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>

                {entregas.length === 0 ? (
                  <p className="flex items-center gap-1.5 text-[10px] text-slate-450">
                    <Inbox className="h-3.5 w-3.5" />
                    Nenhuma entrega ainda.
                  </p>
                ) : (
                  <ul className="space-y-2">
                    {entregas.map((sub) => (
                      <CorrigirEntrega
                        key={sub.id}
                        submissao={sub}
                        maxPoints={ex.maxPoints}
                        onGrade={onGrade}
                        notify={notify}
                      />
                    ))}
                  </ul>
                )}
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
};
