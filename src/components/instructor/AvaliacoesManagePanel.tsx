/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Área de gestão de avaliações de quem dá aula.
 *
 * Antes isto era um `fixed inset-0` chamado "Elaborar Nova Avaliação": um balão
 * sobre a página de gestão da disciplina, e só para CRIAR. Três problemas
 * concretos que a mudança resolve:
 *
 * 1. Não havia como editar. Publicada a avaliação, corrigir um erro no enunciado
 *    exigia apagá-la — e apagar avaliação apaga as respostas já entregues.
 * 2. O rascunho vivia em estados soltos do dashboard; sair do balão perdia tudo
 *    sem aviso.
 * 3. Excluir era um clique só, sem confirmação, ao lado do título.
 *
 * Aqui a lista e o editor são a própria página. Nada de `fixed`.
 */

import React from 'react';
import { CheckSquare, Plus, Trash2, Pencil, ArrowLeft, AlertTriangle, Check } from 'lucide-react';
import { Course, Quiz, QuizQuestion, QuizSubmission } from '../../types';
import { QUIZ_PASS_THRESHOLD } from '../../config/constants';

type Resultado = { ok: boolean; error?: string };

interface AvaliacoesManagePanelProps {
  /** Cursos que esta pessoa administra — a lista já vem filtrada por posse. */
  courses: Course[];
  quizzes: Quiz[];
  submissions: QuizSubmission[];
  onCreate: (courseId: string, title: string, questions: QuizQuestion[]) => Promise<Resultado>;
  onUpdate: (
    quizId: string, courseId: string, title: string, questions: QuizQuestion[]
  ) => Promise<Resultado>;
  onDelete: (quizId: string) => Promise<Resultado>;
  /** Confirmação destrutiva — o painel não chama window.confirm direto. */
  confirmar: (pergunta: string) => boolean;
  notify: (mensagem: string) => void;
}

/** Questão em edição. Opções como texto livre: a validação é explícita abaixo. */
export interface RascunhoQuestao {
  id: string;
  questionText: string;
  options: string[];
  correctOptionIndex: number;
  explanation: string;
  reviewMessage: string;
  recommendedModule: string;
  allowRetry: boolean;
}

export const LETRAS = ['A', 'B', 'C', 'D', 'E', 'F'];
const MIN_OPCOES = 2;
const MAX_OPCOES = 6;

let sequenciaRascunho = 0;

export const questaoVazia = (): RascunhoQuestao => {
  // `id` só serve de chave de lista enquanto a questão é rascunho; o id que vale
  // é o que o servidor devolve depois de gravar. Sequência em vez de Date.now()
  // para não colidir quando duas questões são criadas no mesmo milissegundo.
  sequenciaRascunho += 1;

  return {
    id: `rascunho-${sequenciaRascunho}`,
    questionText: '',
    options: ['', '', '', ''],
    correctOptionIndex: 0,
    explanation: '',
    reviewMessage: '',
    recommendedModule: '',
    allowRetry: true,
  };
};

/** Converte uma questão já gravada em rascunho editável. */
export const paraRascunho = (q: QuizQuestion): RascunhoQuestao => ({
  id: q.id,
  questionText: q.questionText,
  options: [...q.options],
  correctOptionIndex: q.correctOptionIndex,
  explanation: q.explanation ?? '',
  reviewMessage: q.reviewMessage ?? '',
  recommendedModule: q.recommendedModule ?? '',
  allowRetry: q.allowRetry !== false,
});

/**
 * Primeiro problema de uma questão, ou null.
 *
 * A alternativa correta é validada CONTRA as opções preenchidas: o balão antigo
 * deixava escolher "Opção 4 (D)" e publicar com a opção 4 em branco — gerando
 * uma questão cujo gabarito aponta para uma alternativa que o aluno não vê.
 */
export const problemaNaQuestao = (q: RascunhoQuestao): string | null => {
  if (q.questionText.trim() === '') return 'Escreva o enunciado da questão.';

  const preenchidas = q.options.filter((o) => o.trim() !== '');
  if (preenchidas.length < MIN_OPCOES) {
    return `Preencha ao menos ${MIN_OPCOES} alternativas.`;
  }
  // Alternativa em branco no meio deslocaria o gabarito ao salvar, porque as
  // vazias são descartadas em paraQuestao.
  const primeiraVazia = q.options.findIndex((o) => o.trim() === '');
  if (primeiraVazia !== -1 && primeiraVazia < preenchidas.length) {
    return `A alternativa ${LETRAS[primeiraVazia]} está em branco entre outras preenchidas.`;
  }
  if ((q.options[q.correctOptionIndex] ?? '').trim() === '') {
    return 'A alternativa marcada como correta está em branco.';
  }

  const normalizadas = preenchidas.map((o) => o.trim().toLowerCase());
  if (new Set(normalizadas).size !== normalizadas.length) {
    return 'Há alternativas repetidas — o aluno teria duas respostas certas.';
  }

  return null;
};

/** Primeiro problema da avaliação inteira, ou null. */
export const problemaNaAvaliacao = (
  titulo: string,
  courseId: string,
  questoes: RascunhoQuestao[]
): string | null => {
  if (titulo.trim() === '') return 'Dê um título à avaliação.';
  if (courseId.trim() === '') return 'Escolha a disciplina desta avaliação.';
  if (questoes.length === 0) return 'Uma avaliação precisa de pelo menos uma questão.';

  for (let i = 0; i < questoes.length; i += 1) {
    const problema = problemaNaQuestao(questoes[i]);
    if (problema !== null) return `Questão ${i + 1}: ${problema}`;
  }

  return null;
};

/** Rascunho -> payload. Descarta alternativa em branco no fim e campos vazios. */
export const paraQuestao = (q: RascunhoQuestao): QuizQuestion => {
  const options = q.options.map((o) => o.trim()).filter((o) => o !== '');
  const texto = (v: string) => (v.trim() === '' ? undefined : v.trim());

  return {
    id: q.id,
    questionText: q.questionText.trim(),
    options,
    correctOptionIndex: q.correctOptionIndex,
    explanation: texto(q.explanation),
    reviewMessage: texto(q.reviewMessage),
    recommendedModule: texto(q.recommendedModule),
    allowRetry: q.allowRetry,
  };
};

const rotuloCampo = 'block text-[9px] font-bold text-slate-500 uppercase mb-1';
const campo =
  'w-full rounded-lg border border-slate-200 p-2 text-[11px] text-slate-800 focus:ring-1 focus:ring-amber-500 focus:outline-hidden';

/** Editor de UMA questão, dentro da lista do editor da avaliação. */
const EditorQuestao: React.FC<{
  questao: RascunhoQuestao;
  indice: number;
  onChange: (q: RascunhoQuestao) => void;
  onRemove: () => void;
}> = ({ questao, indice, onChange, onRemove }) => {
  const problema = problemaNaQuestao(questao);
  const alterar = (campos: Partial<RascunhoQuestao>) => onChange({ ...questao, ...campos });

  return (
    <li className="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
      <div className="flex items-start justify-between gap-3">
        <span className="text-[10px] font-black uppercase tracking-wider text-amber-700">
          Questão {indice + 1}
        </span>
        <button
          type="button"
          onClick={onRemove}
          aria-label={`Remover questão ${indice + 1}`}
          className="cursor-pointer rounded-md p-1 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600"
        >
          <Trash2 className="h-3.5 w-3.5" />
        </button>
      </div>

      <div>
        <label className={rotuloCampo} htmlFor={`q-enunciado-${questao.id}`}>
          Enunciado / Pergunta
        </label>
        <textarea
          id={`q-enunciado-${questao.id}`}
          rows={2}
          value={questao.questionText}
          onChange={(e) => alterar({ questionText: e.target.value })}
          placeholder="Escreva a pergunta claramente..."
          className={campo}
        />
      </div>

      <fieldset className="space-y-2">
        <legend className={rotuloCampo}>Alternativas — marque a correta</legend>
        {questao.options.map((opcao, i) => (
          <div key={i} className="flex items-center gap-2">
            <input
              type="radio"
              name={`correta-${questao.id}`}
              checked={questao.correctOptionIndex === i}
              onChange={() => alterar({ correctOptionIndex: i })}
              aria-label={`Alternativa ${LETRAS[i]} é a correta`}
              className="h-3.5 w-3.5 shrink-0 cursor-pointer border-slate-300 text-emerald-600 focus:ring-emerald-500"
            />
            <span className="w-4 shrink-0 text-[10px] font-black text-slate-400">{LETRAS[i]}</span>
            <input
              type="text"
              value={opcao}
              onChange={(e) =>
                alterar({ options: questao.options.map((o, idx) => (idx === i ? e.target.value : o)) })
              }
              aria-label={`Texto da alternativa ${LETRAS[i]}`}
              placeholder={`Alternativa ${LETRAS[i]}...`}
              className={campo}
            />
            {questao.options.length > MIN_OPCOES && (
              <button
                type="button"
                onClick={() => {
                  const options = questao.options.filter((_, idx) => idx !== i);
                  // Remover alternativa antes da correta deslocaria o gabarito.
                  const corrigido =
                    questao.correctOptionIndex > i
                      ? questao.correctOptionIndex - 1
                      : Math.min(questao.correctOptionIndex, options.length - 1);
                  alterar({ options, correctOptionIndex: corrigido });
                }}
                aria-label={`Excluir alternativa ${LETRAS[i]}`}
                className="cursor-pointer px-1 text-[10px] font-bold text-slate-400 hover:text-rose-600"
              >
                ✕
              </button>
            )}
          </div>
        ))}
        {questao.options.length < MAX_OPCOES && (
          <button
            type="button"
            onClick={() => alterar({ options: [...questao.options, ''] })}
            className="cursor-pointer text-[10px] font-bold text-teal-700 hover:underline"
          >
            + Acrescentar alternativa
          </button>
        )}
      </fieldset>

      <details className="rounded-lg bg-slate-50 p-2">
        <summary className="cursor-pointer text-[10px] font-bold uppercase tracking-wide text-slate-500">
          Feedback e direcionamento (opcionais)
        </summary>
        <div className="mt-2 space-y-2">
          <div>
            <label className={rotuloCampo} htmlFor={`q-explicacao-${questao.id}`}>
              Explicação (por que a resposta está certa)
            </label>
            <textarea
              id={`q-explicacao-${questao.id}`}
              rows={2}
              value={questao.explanation}
              onChange={(e) => alterar({ explanation: e.target.value })}
              className={campo}
            />
          </div>
          <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <div>
              <label className={rotuloCampo} htmlFor={`q-revisao-${questao.id}`}>
                Mensagem se errar
              </label>
              <input
                id={`q-revisao-${questao.id}`}
                type="text"
                value={questao.reviewMessage}
                onChange={(e) => alterar({ reviewMessage: e.target.value })}
                className={campo}
              />
            </div>
            <div>
              <label className={rotuloCampo} htmlFor={`q-modulo-${questao.id}`}>
                Recomendar módulo ou aula
              </label>
              <input
                id={`q-modulo-${questao.id}`}
                type="text"
                value={questao.recommendedModule}
                onChange={(e) => alterar({ recommendedModule: e.target.value })}
                className={campo}
              />
            </div>
          </div>
          <label className="flex cursor-pointer items-center gap-2 text-[10px] font-bold text-slate-600">
            <input
              type="checkbox"
              checked={questao.allowRetry}
              onChange={(e) => alterar({ allowRetry: e.target.checked })}
              className="h-3.5 w-3.5 rounded border-slate-300 text-amber-600 focus:ring-amber-500"
            />
            Permitir tentar responder novamente esta questão
          </label>
        </div>
      </details>

      {problema !== null && (
        <p className="flex items-start gap-1.5 text-[10px] font-semibold text-amber-700">
          <AlertTriangle className="mt-px h-3 w-3 shrink-0" />
          {problema}
        </p>
      )}
    </li>
  );
};

/** Editor da avaliação — o mesmo para criar e para editar. */
const EditorAvaliacao: React.FC<{
  courses: Course[];
  quiz?: Quiz;
  respostasEntregues: number;
  onCancel: () => void;
  onSave: (courseId: string, titulo: string, questoes: QuizQuestion[]) => Promise<Resultado>;
}> = ({ courses, quiz, respostasEntregues, onCancel, onSave }) => {
  const [titulo, setTitulo] = React.useState(quiz?.title ?? '');
  const [courseId, setCourseId] = React.useState(quiz?.courseId ?? courses[0]?.id ?? '');
  const [questoes, setQuestoes] = React.useState<RascunhoQuestao[]>(
    quiz ? quiz.questions.map(paraRascunho) : [questaoVazia()]
  );
  const [erro, setErro] = React.useState<string | null>(null);
  const [salvando, setSalvando] = React.useState(false);

  const editando = quiz !== undefined;

  const salvar = async () => {
    const problema = problemaNaAvaliacao(titulo, courseId, questoes);
    if (problema !== null) {
      setErro(problema);
      return;
    }
    setErro(null);
    setSalvando(true);
    const r = await onSave(courseId, titulo.trim(), questoes.map(paraQuestao));
    setSalvando(false);
    if (!r.ok) {
      setErro(r.error ?? 'Não foi possível gravar a avaliação.');
    }
  };

  return (
    <section className="space-y-5 text-left">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <button
          type="button"
          onClick={onCancel}
          className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-[11px] font-bold text-slate-600 transition-colors hover:bg-slate-200"
        >
          <ArrowLeft className="h-3.5 w-3.5" /> Voltar às avaliações
        </button>
        <h3 className="text-sm font-black text-slate-900">
          {editando ? 'Editar avaliação' : 'Elaborar nova avaliação'}
        </h3>
      </div>

      {/*
        Editar avaliação já respondida não recalcula as notas entregues: o aluno
        foi avaliado pelo gabarito de então. Dizer isso é mais honesto do que
        bloquear a edição — e do que deixar a pessoa supor o contrário.
      */}
      {editando && respostasEntregues > 0 && (
        <p className="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-[11px] font-semibold text-amber-900">
          <AlertTriangle className="mt-px h-3.5 w-3.5 shrink-0" />
          <span>
            {respostasEntregues}{' '}
            {respostasEntregues === 1 ? 'aluno já respondeu' : 'alunos já responderam'} esta avaliação.
            As notas já lançadas não mudam — elas valem pelo gabarito vigente na hora da resposta.
          </span>
        </p>
      )}

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
          <label className={rotuloCampo} htmlFor="av-titulo">
            Título da avaliação
          </label>
          <input
            id="av-titulo"
            type="text"
            value={titulo}
            onChange={(e) => setTitulo(e.target.value)}
            placeholder="Ex: Teste de fixação — Módulo 1"
            className={campo}
          />
        </div>
        <div>
          <label className={rotuloCampo} htmlFor="av-curso">
            Disciplina
          </label>
          <select
            id="av-curso"
            value={courseId}
            onChange={(e) => setCourseId(e.target.value)}
            className={`${campo} bg-white font-semibold`}
          >
            {courses.map((c) => (
              <option key={c.id} value={c.id}>
                {c.title}
              </option>
            ))}
          </select>
        </div>
      </div>

      <ul className="space-y-3">
        {questoes.map((q, i) => (
          <EditorQuestao
            key={q.id}
            questao={q}
            indice={i}
            onChange={(nova) =>
              setQuestoes((prev) => prev.map((item, idx) => (idx === i ? nova : item)))
            }
            onRemove={() => setQuestoes((prev) => prev.filter((_, idx) => idx !== i))}
          />
        ))}
      </ul>

      <button
        type="button"
        onClick={() => setQuestoes((prev) => [...prev, questaoVazia()])}
        className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-[11px] font-bold text-slate-600 transition-colors hover:border-teal-400 hover:text-teal-700"
      >
        <Plus className="h-3.5 w-3.5" /> Acrescentar questão
      </button>

      {erro !== null && (
        <p
          role="alert"
          className="rounded-xl border border-rose-200 bg-rose-50 p-3 text-[11px] font-bold text-rose-700"
        >
          {erro}
        </p>
      )}

      <div className="flex flex-wrap justify-end gap-2 border-t border-slate-150 pt-4">
        <button
          type="button"
          onClick={onCancel}
          className="cursor-pointer rounded-lg bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-200"
        >
          Cancelar
        </button>
        <button
          type="button"
          onClick={salvar}
          disabled={salvando}
          className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-amber-600 px-4 py-2 text-xs font-bold text-white shadow-xs transition-colors hover:bg-amber-500 disabled:cursor-not-allowed disabled:bg-amber-300"
        >
          <Check className="h-3.5 w-3.5" />
          {salvando ? 'Gravando...' : editando ? 'Salvar alterações' : 'Publicar avaliação'}
        </button>
      </div>
    </section>
  );
};

export const AvaliacoesManagePanel: React.FC<AvaliacoesManagePanelProps> = ({
  courses,
  quizzes,
  submissions,
  onCreate,
  onUpdate,
  onDelete,
  confirmar,
  notify,
}) => {
  const [modo, setModo] = React.useState<'lista' | 'novo' | string>('lista');
  const [erro, setErro] = React.useState<string | null>(null);

  const idsDosCursos = React.useMemo(() => new Set(courses.map((c) => c.id)), [courses]);
  // Só as avaliações das disciplinas desta pessoa — o mesmo escopo que o
  // servidor impõe em /api/quizzes.
  const minhas = React.useMemo(
    () => quizzes.filter((q) => idsDosCursos.has(q.courseId)),
    [quizzes, idsDosCursos]
  );

  const respostasDe = React.useCallback(
    (quizId: string) => submissions.filter((s) => s.quizId === quizId),
    [submissions]
  );

  const emEdicao =
    modo !== 'lista' && modo !== 'novo' ? minhas.find((q) => q.id === modo) : undefined;

  if (courses.length === 0) {
    return (
      <section className="rounded-2xl border border-slate-200 bg-white p-8 text-center">
        <CheckSquare className="mx-auto h-8 w-8 text-slate-300" />
        <p className="mt-3 text-xs font-semibold text-slate-500">
          Você ainda não tem disciplina sob sua responsabilidade para avaliar.
        </p>
      </section>
    );
  }

  if (modo === 'novo' || emEdicao !== undefined) {
    return (
      <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <EditorAvaliacao
          courses={courses}
          quiz={emEdicao}
          respostasEntregues={emEdicao ? respostasDe(emEdicao.id).length : 0}
          onCancel={() => setModo('lista')}
          onSave={async (courseId, titulo, questoes) => {
            const r = emEdicao
              ? await onUpdate(emEdicao.id, courseId, titulo, questoes)
              : await onCreate(courseId, titulo, questoes);
            if (r.ok) {
              notify(emEdicao ? 'Avaliação atualizada.' : 'Avaliação publicada para a turma.');
              setModo('lista');
            }

            return r;
          }}
        />
      </div>
    );
  }

  return (
    <section className="space-y-4 text-left">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h3 className="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-slate-800">
          <CheckSquare className="h-4 w-4 text-amber-500" />
          <span>Avaliações elaboradas ({minhas.length})</span>
        </h3>
        <button
          type="button"
          onClick={() => {
            setErro(null);
            setModo('novo');
          }}
          className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-2 text-[11px] font-bold text-white shadow-xs transition-colors hover:bg-amber-500"
        >
          <Plus className="h-3.5 w-3.5" /> Elaborar avaliação
        </button>
      </div>

      {erro !== null && (
        <p
          role="alert"
          className="rounded-xl border border-rose-200 bg-rose-50 p-3 text-[11px] font-bold text-rose-700"
        >
          {erro}
        </p>
      )}

      {minhas.length === 0 ? (
        <p className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-xs font-semibold text-slate-500">
          Nenhuma avaliação elaborada ainda. Use "Elaborar avaliação" para criar a primeira.
        </p>
      ) : (
        <ul className="grid grid-cols-1 gap-3 lg:grid-cols-2">
          {minhas.map((quiz) => {
            const respostas = respostasDe(quiz.id);
            const aprovados = respostas.filter((s) => s.passed).length;
            const curso = courses.find((c) => c.id === quiz.courseId);

            return (
              <li
                key={quiz.id}
                className="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-3xs"
              >
                <div className="min-w-0">
                  <span className="block text-[9px] font-black uppercase tracking-wider text-teal-700">
                    {curso?.title ?? 'Disciplina'}
                  </span>
                  <strong className="mt-0.5 block text-xs font-black text-slate-900">
                    {quiz.title}
                  </strong>
                  <span className="mt-1 block text-[10px] font-semibold text-slate-500">
                    {quiz.questions.length} {quiz.questions.length === 1 ? 'questão' : 'questões'} ·{' '}
                    {respostas.length} {respostas.length === 1 ? 'resposta' : 'respostas'} ·{' '}
                    {aprovados} aprovado{aprovados === 1 ? '' : 's'} (mínimo {QUIZ_PASS_THRESHOLD}%)
                  </span>
                </div>

                <div className="flex flex-wrap gap-2">
                  <button
                    type="button"
                    onClick={() => {
                      setErro(null);
                      setModo(quiz.id);
                    }}
                    className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 text-[10px] font-bold text-slate-700 transition-colors hover:bg-slate-200"
                  >
                    <Pencil className="h-3 w-3" /> Editar
                  </button>
                  <button
                    type="button"
                    onClick={async () => {
                      /*
                       * Apagar avaliação apaga as respostas dos alunos junto (o
                       * servidor remove as QuizSubmission do quiz). O botão antigo
                       * fazia isso num clique, sem perguntar nada.
                       */
                      const aviso =
                        respostas.length > 0
                          ? `Excluir "${quiz.title}" apaga também as ${respostas.length} respostas já entregues pelos alunos. Confirmar?`
                          : `Excluir a avaliação "${quiz.title}"?`;
                      if (!confirmar(aviso)) return;

                      const r = await onDelete(quiz.id);
                      if (r.ok) {
                        notify('Avaliação excluída.');
                      } else {
                        setErro(r.error ?? 'Não foi possível excluir a avaliação.');
                      }
                    }}
                    className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-rose-50 px-3 py-1.5 text-[10px] font-bold text-rose-700 transition-colors hover:bg-rose-100"
                  >
                    <Trash2 className="h-3 w-3" /> Excluir
                  </button>
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
};
