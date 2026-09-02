/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import {
  ArrowLeft, ArrowRight, CheckSquare, CheckCircle, Lightbulb, Info,
  PartyPopper, BookOpen, Sparkles, AlertTriangle, Award,
} from 'lucide-react';
import { Quiz, QuizQuestion, QuizSubmission } from '../../types';
import { QUIZ_PASS_THRESHOLD } from '../../config/constants';

/**
 * Área própria dos testes e avaliações de um curso.
 *
 * Antes o teste abria num modal `fixed inset-0` sobre o portal: backdrop
 * desfocado, clique fora fechando, altura travada em 85vh com rolagem interna.
 * Prova é a atividade mais longa e mais concentrada que o aluno faz aqui —
 * fazê-la numa janelinha flutuante que fecha por clique acidental é o pior
 * lugar possível. Aqui é página: a lista de avaliações e, ao começar, a prova
 * no mesmo lugar, usando a largura toda.
 *
 * Sem estado global: o andamento da prova vive neste componente.
 */

interface AvaliacoesPageProps {
  courseTitle: string;
  courseId: string;
  /** Todas as avaliações conhecidas; a página filtra pelo curso. */
  quizzes: Quiz[];
  submissions: QuizSubmission[];
  userId: string;
  onBack: () => void;
  /**
   * Registra a tentativa. A nota que vale é a que o SERVIDOR devolve — o
   * backend recalcula a partir das respostas e ignora o que o cliente mandar.
   */
  onSubmit: (
    quizId: string,
    scorePercent: number,
    passed: boolean,
    answers: Record<string, number>
  ) => Promise<{ ok: boolean; error?: string; scorePercent?: number; passed?: boolean }>;
  /** Avaliação a abrir já ao entrar (o card do curso aponta para uma delas). */
  quizInicial?: string | null;
  notify: (mensagem: string) => void;
}

/** Nota e situação da última tentativa do aluno naquela avaliação. */
const tentativaDe = (
  submissions: QuizSubmission[],
  quizId: string,
  userId: string
): QuizSubmission | undefined =>
  submissions.find((s) => s.quizId === quizId && s.userId === userId);

/** Acertos de um conjunto de respostas — usado só para o feedback imediato. */
export const contarAcertos = (
  questions: QuizQuestion[],
  answers: Record<string, number>
): number => questions.filter((q) => answers[q.id] === q.correctOptionIndex).length;

export const percentual = (acertos: number, total: number): number =>
  total === 0 ? 0 : Math.round((acertos / total) * 100);

/** Prova em andamento: uma questão por vez, com gabarito comentado ao responder. */
const ProvaEmAndamento: React.FC<{
  quiz: Quiz;
  onSair: () => void;
  onSubmit: AvaliacoesPageProps['onSubmit'];
  notify: (m: string) => void;
}> = ({ quiz, onSair, onSubmit, notify }) => {
  const [idx, setIdx] = React.useState(0);
  const [respostas, setRespostas] = React.useState<Record<string, number>>({});
  const [respondidas, setRespondidas] = React.useState<Record<string, boolean>>({});
  const [resultado, setResultado] = React.useState<{ scorePercent: number; passed: boolean } | null>(null);
  const [enviando, setEnviando] = React.useState(false);
  const [erro, setErro] = React.useState<string | null>(null);
  const [confirmandoSaida, setConfirmandoSaida] = React.useState(false);

  const total = quiz.questions.length;
  const questao = quiz.questions[idx];

  const finalizar = async () => {
    const acertos = contarAcertos(quiz.questions, respostas);
    const score = percentual(acertos, total);
    const passou = score >= QUIZ_PASS_THRESHOLD;

    setEnviando(true);
    setErro(null);
    const res = await onSubmit(quiz.id, score, passou, respostas);
    setEnviando(false);
    if (!res.ok) {
      // Sem isso a nota podia não ter sido registrada e o aluno ia embora
      // achando que tinha.
      setErro(res.error ?? 'Não foi possível registrar sua tentativa. Tente enviar de novo.');
      return;
    }
    // A nota do servidor é a que vale; a local era só para o feedback na hora.
    setResultado({
      scorePercent: res.scorePercent ?? score,
      passed: res.passed ?? passou,
    });
    notify(`Avaliação enviada. Rendimento de ${res.scorePercent ?? score}%.`);
  };

  const recomecar = () => {
    setIdx(0);
    setRespostas({});
    setRespondidas({});
    setResultado(null);
    setErro(null);
  };

  if (resultado !== null) {
    const acertos = contarAcertos(quiz.questions, respostas);
    const erradas = quiz.questions.filter((q) => respostas[q.id] !== q.correctOptionIndex);

    return (
      <div className="space-y-6">
        <div
          className={`space-y-3 rounded-2xl border p-6 text-center ${
            resultado.passed
              ? 'border-emerald-200 bg-emerald-50 text-emerald-950'
              : 'border-amber-200 bg-amber-50/50 text-amber-950'
          }`}
        >
          <span className="mx-auto flex h-14 w-14 items-center justify-center rounded-full border border-current/15 bg-white/70">
            {resultado.passed
              ? <PartyPopper className="h-7 w-7 text-emerald-600" />
              : <BookOpen className="h-7 w-7 text-amber-600" />}
          </span>
          <h3 className="text-sm font-extrabold uppercase tracking-wide">
            {resultado.passed ? 'Aprovado nesta avaliação' : 'Avaliação concluída — revisão recomendada'}
          </h3>
          <div className="inline-flex items-center gap-1.5 rounded-full border border-slate-200/20 bg-white/60 px-3 py-1 text-xs font-black">
            <span>Acertos: <span className="font-bold text-teal-600">{acertos}</span> de {total}</span>
            <span className="text-slate-350">•</span>
            <span>Rendimento: <span className="font-bold text-teal-600">{resultado.scorePercent}%</span></span>
          </div>
          <p className="mx-auto max-w-md text-[11px] font-medium leading-relaxed text-slate-700">
            {resultado.passed
              ? `Você atingiu ${resultado.scorePercent}% de aproveitamento.`
              : `Você atingiu ${resultado.scorePercent}% de aproveitamento. O mínimo recomendado para consolidar o conteúdo é ${QUIZ_PASS_THRESHOLD}%.`}
          </p>
        </div>

        {erradas.length > 0 && (
          <div className="space-y-3 rounded-2xl border border-amber-200/50 bg-amber-50/10 p-4">
            <h4 className="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-amber-800">
              <Info className="h-4 w-4" />
              <span>Tópicos recomendados para revisão</span>
            </h4>
            <div className="space-y-2.5">
              {erradas.map((q) => (
                <div key={q.id} className="space-y-1 rounded-lg border border-slate-200 bg-white p-3 text-left text-[11px]">
                  <span className="block font-bold text-slate-800">
                    Questão {quiz.questions.indexOf(q) + 1}: {q.questionText}
                  </span>
                  {q.recommendedModule && (
                    <div className="mt-1.5 flex items-center gap-1.5 text-xs">
                      <span className="rounded bg-amber-100 px-2 py-0.5 font-mono text-[9px] font-bold uppercase tracking-wider text-amber-800">
                        Módulo recomendado
                      </span>
                      <strong className="font-semibold text-amber-750">{q.recommendedModule}</strong>
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}

        {resultado.passed && erradas.length === 0 && (
          <p className="rounded-2xl border border-emerald-100 bg-emerald-50/20 p-4 text-center text-[11px] text-slate-650">
            <Sparkles className="mr-1 -mt-0.5 inline-block h-3.5 w-3.5 text-amber-500" />
            Você acertou todas as questões.
          </p>
        )}

        <div className="flex flex-wrap justify-end gap-2">
          {!resultado.passed && (
            <button
              type="button"
              onClick={recomecar}
              className="cursor-pointer rounded-xl bg-amber-600 px-6 py-3 text-xs font-black uppercase tracking-wider text-white transition-all hover:bg-amber-500"
            >
              Tentar novamente
            </button>
          )}
          <button
            type="button"
            onClick={onSair}
            className="cursor-pointer rounded-xl bg-slate-900 px-6 py-3 text-xs font-bold text-white transition-all hover:bg-slate-800"
          >
            Voltar às avaliações
          </button>
        </div>
      </div>
    );
  }

  if (!questao) {
    return (
      <p className="rounded-2xl border border-dashed border-slate-250 p-8 text-center text-xs text-slate-500">
        Esta avaliação não tem questões cadastradas.
      </p>
    );
  }

  const escolhida = respostas[questao.id];
  const respondida = respondidas[questao.id] === true;
  const acertou = escolhida === questao.correctOptionIndex;
  const ultima = idx === total - 1;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between text-[11px] font-semibold text-slate-500">
        <span>Questão {idx + 1} de {total}</span>
        <span className="font-mono">{percentual(idx, total)}% concluído</span>
      </div>
      <div className="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
        <div
          className="h-full bg-teal-500 transition-all duration-300"
          style={{ width: `${percentual(idx, total)}%` }}
        />
      </div>

      <div className="space-y-3 rounded-2xl border border-slate-150 bg-slate-50/50 p-5">
        <span className="text-[10px] font-black uppercase tracking-wider text-teal-600">Enunciado</span>
        <h3 className="text-sm font-bold leading-relaxed text-slate-800">{questao.questionText}</h3>
      </div>

      <div className="space-y-2.5">
        <span className="block text-[10px] font-black uppercase tracking-wider text-slate-400">
          Alternativas
        </span>
        {questao.options.map((opt, optIdx) => {
          const selecionada = escolhida === optIdx;
          const correta = questao.correctOptionIndex === optIdx;

          let caixa = 'border-slate-200 bg-white text-slate-700 hover:border-teal-500 hover:bg-slate-50/50';
          let bolinha = 'border-slate-300 bg-white text-slate-400';
          if (selecionada && !respondida) {
            caixa = 'border-teal-500 bg-teal-50/10 font-bold text-teal-950';
            bolinha = 'border-teal-600 bg-teal-600 text-white';
          } else if (respondida && correta) {
            caixa = 'border-emerald-500 bg-emerald-50/60 font-bold text-emerald-950';
            bolinha = 'border-emerald-600 bg-emerald-600 text-white';
          } else if (respondida && selecionada) {
            caixa = 'border-amber-400 bg-amber-50/30 font-bold text-slate-700';
            bolinha = 'border-amber-500 bg-amber-500 text-white';
          } else if (respondida) {
            caixa = 'border-slate-100 bg-slate-50/30 text-slate-400 cursor-not-allowed';
            bolinha = 'border-slate-200 bg-slate-50 text-slate-300';
          }

          return (
            <button
              type="button"
              key={`${questao.id}-${optIdx}`}
              disabled={respondida}
              onClick={() => setRespostas((prev) => ({ ...prev, [questao.id]: optIdx }))}
              className={`flex w-full cursor-pointer items-center gap-3 rounded-xl border p-3.5 text-left text-xs transition-all ${caixa}`}
            >
              <span className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full border text-[10px] font-bold ${bolinha}`}>
                {String.fromCharCode(65 + optIdx)}
              </span>
              <span className="flex-1 leading-snug">{opt}</span>
            </button>
          );
        })}
      </div>

      {!respondida ? (
        <button
          type="button"
          disabled={escolhida === undefined}
          onClick={() => setRespondidas((prev) => ({ ...prev, [questao.id]: true }))}
          className={`flex w-full items-center justify-center gap-2 rounded-xl px-5 py-3 text-xs font-black uppercase tracking-wider transition-all ${
            escolhida === undefined
              ? 'cursor-not-allowed border border-slate-200 bg-slate-100 text-slate-400'
              : 'cursor-pointer bg-teal-600 text-white shadow-xs hover:bg-teal-500'
          }`}
        >
          <span>Responder</span>
          <ArrowRight className="h-4 w-4" />
        </button>
      ) : (
        <div className="space-y-4">
          <div
            className={`space-y-3 rounded-2xl border p-5 leading-relaxed ${
              acertou
                ? 'border-emerald-200 bg-emerald-50 text-emerald-950'
                : 'border-amber-200 bg-amber-50/50 text-amber-950'
            }`}
          >
            <div className="flex items-center gap-2">
              {acertou
                ? <CheckCircle className="h-5 w-5 shrink-0 text-emerald-600" />
                : <Lightbulb className="h-5 w-5 shrink-0 text-amber-600" />}
              <strong className="text-xs font-extrabold">
                {acertou ? 'Resposta correta' : 'Ainda não foi desta vez'}
              </strong>
            </div>

            <div className="space-y-2 border-t border-slate-200/30 pt-3 text-[11px]">
              <span className="block text-[9px] font-bold uppercase tracking-wider text-slate-500">
                Gabarito da questão
              </span>
              <p className="font-semibold text-slate-800">
                A alternativa correta é{' '}
                <span className="font-extrabold text-teal-700">
                  {String.fromCharCode(65 + questao.correctOptionIndex)}
                </span>
                . {questao.explanation || 'Nenhuma explicação adicional fornecida.'}
              </p>

              {(questao.reviewMessage || questao.recommendedModule) && (
                <div className="mt-2 rounded-lg border border-slate-200/10 bg-white/40 p-2.5">
                  <span className="block font-mono text-[9px] font-bold uppercase tracking-wider text-slate-500">
                    Indicação de estudo
                  </span>
                  {questao.reviewMessage && (
                    <p className="mt-0.5 italic text-slate-700">{questao.reviewMessage}</p>
                  )}
                  {questao.recommendedModule && (
                    <p className="mt-1 font-bold text-amber-700">
                      Revise: <span className="underline">{questao.recommendedModule}</span>
                    </p>
                  )}
                </div>
              )}
            </div>
          </div>

          {erro !== null && (
            <p className="flex items-start gap-1.5 rounded-xl border border-rose-200 bg-rose-50 p-3 text-[11px] font-bold leading-relaxed text-rose-700">
              <AlertTriangle className="mt-px h-3.5 w-3.5 shrink-0" />
              {erro}
            </p>
          )}

          <div className="flex flex-col gap-2 sm:flex-row">
            {!acertou && questao.allowRetry !== false && (
              <button
                type="button"
                onClick={() => {
                  setRespondidas((prev) => ({ ...prev, [questao.id]: false }));
                  setRespostas((prev) => {
                    const copia = { ...prev };
                    delete copia[questao.id];

                    return copia;
                  });
                }}
                className="flex-1 cursor-pointer rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-bold text-slate-700 transition-all hover:bg-slate-50"
              >
                Tentar esta questão de novo
              </button>
            )}
            <button
              type="button"
              disabled={enviando}
              onClick={() => (ultima ? finalizar() : setIdx((i) => i + 1))}
              className="flex flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-xl bg-teal-600 px-5 py-3 text-xs font-black uppercase tracking-wider text-white transition-all hover:bg-teal-500 disabled:opacity-60"
            >
              <span>
                {enviando ? 'Enviando...' : ultima ? 'Ver resultado final' : 'Próxima pergunta'}
              </span>
              <ArrowRight className="h-4 w-4" />
            </button>
          </div>
        </div>
      )}

      {/* Saída com confirmação NA PÁGINA — o modal antigo fechava a prova por
          clique no backdrop, perdendo tudo sem aviso nenhum. */}
      <div className="border-t border-slate-100 pt-4">
        {confirmandoSaida ? (
          <div className="flex flex-wrap items-center gap-2 rounded-xl border border-amber-200 bg-amber-50/60 p-3">
            <span className="flex-1 text-[11px] font-bold text-amber-900">
              Sair agora descarta as respostas desta tentativa.
            </span>
            <button
              type="button"
              onClick={onSair}
              className="cursor-pointer rounded-lg bg-rose-600 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-white hover:bg-rose-500"
            >
              Sair e descartar
            </button>
            <button
              type="button"
              onClick={() => setConfirmandoSaida(false)}
              className="cursor-pointer rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-600 hover:bg-slate-100"
            >
              Continuar prova
            </button>
          </div>
        ) : (
          <button
            type="button"
            onClick={() => setConfirmandoSaida(true)}
            className="cursor-pointer text-[10px] font-bold uppercase tracking-wider text-slate-450 hover:text-slate-700"
          >
            Sair do teste
          </button>
        )}
      </div>
    </div>
  );
};

export const AvaliacoesPage: React.FC<AvaliacoesPageProps> = ({
  courseTitle, courseId, quizzes, submissions, userId, onBack, onSubmit, quizInicial, notify,
}) => {
  const doCurso = React.useMemo(
    () => quizzes.filter((q) => q.courseId === courseId),
    [quizzes, courseId]
  );

  const [emAndamento, setEmAndamento] = React.useState<string | null>(
    quizInicial !== undefined && quizInicial !== null
      && doCurso.some((q) => q.id === quizInicial) ? quizInicial : null
  );

  const quiz = doCurso.find((q) => q.id === emAndamento);
  const aprovadas = doCurso.filter((q) => tentativaDe(submissions, q.id, userId)?.passed).length;

  return (
    <div className="mx-auto w-full max-w-3xl space-y-6 text-left">
      <button
        type="button"
        onClick={quiz ? () => setEmAndamento(null) : onBack}
        className="inline-flex cursor-pointer items-center gap-1.5 text-[11px] font-black uppercase tracking-wider text-slate-500 hover:text-slate-800"
      >
        <ArrowLeft className="h-3.5 w-3.5" />
        {quiz ? 'Voltar às avaliações' : 'Voltar ao curso'}
      </button>

      <header className="space-y-3 rounded-2xl border border-amber-100 bg-amber-50/25 p-5">
        <div className="space-y-1">
          <span className="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-amber-700">
            <CheckSquare className="h-3.5 w-3.5" />
            Testes e avaliações
          </span>
          <h2 className="text-lg font-black leading-tight text-slate-900">
            {quiz ? quiz.title : courseTitle}
          </h2>
        </div>

        {!quiz && doCurso.length > 0 && (
          <dl className="flex flex-wrap gap-x-6 gap-y-2 border-t border-amber-100 pt-3 text-[11px]">
            <div>
              <dt className="font-bold uppercase tracking-wide text-slate-450">Avaliações</dt>
              <dd className="font-mono text-sm font-black text-slate-900">{doCurso.length}</dd>
            </div>
            <div>
              <dt className="flex items-center gap-1 font-bold uppercase tracking-wide text-slate-450">
                <Award className="h-3 w-3 text-emerald-600" /> Aprovadas
              </dt>
              <dd className="font-mono text-sm font-black text-emerald-700">
                {aprovadas} de {doCurso.length}
              </dd>
            </div>
            <div>
              <dt className="font-bold uppercase tracking-wide text-slate-450">Mínimo</dt>
              <dd className="font-mono text-sm font-black text-slate-900">{QUIZ_PASS_THRESHOLD}%</dd>
            </div>
          </dl>
        )}
      </header>

      {quiz ? (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-2xs">
          <ProvaEmAndamento
            key={quiz.id}
            quiz={quiz}
            onSair={() => setEmAndamento(null)}
            onSubmit={onSubmit}
            notify={notify}
          />
        </div>
      ) : doCurso.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-slate-250 bg-slate-50/60 p-10 text-center">
          <CheckSquare className="mx-auto mb-3 h-8 w-8 text-slate-300" />
          <strong className="block text-sm font-bold text-slate-700">
            Este curso ainda não tem avaliações.
          </strong>
          <span className="mx-auto mt-1 block max-w-md text-xs leading-relaxed text-slate-500">
            Quando o professor publicar um teste, ele aparece aqui com o número de
            questões e a sua nota.
          </span>
        </div>
      ) : (
        <ul className="space-y-3">
          {doCurso.map((q) => {
            const tentativa = tentativaDe(submissions, q.id, userId);

            return (
              <li
                key={q.id}
                className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs"
              >
                <div className="min-w-0 space-y-1">
                  <strong className="block text-xs font-bold leading-snug text-slate-900">{q.title}</strong>
                  <span className="block text-[10px] text-slate-450">
                    {q.questions.length} {q.questions.length === 1 ? 'questão' : 'questões'}
                    {tentativa && ` · última tentativa em ${tentativa.submittedAt}`}
                  </span>
                </div>

                <div className="flex shrink-0 items-center gap-2">
                  {tentativa && (
                    <span className={`rounded-md border px-2 py-0.5 text-[10px] font-black uppercase ${
                      tentativa.passed
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                        : 'border-amber-200 bg-amber-50 text-amber-800'
                    }`}>
                      {tentativa.passed ? `Aprovado · ${tentativa.scorePercent}%` : `${tentativa.scorePercent}%`}
                    </span>
                  )}
                  <button
                    type="button"
                    onClick={() => setEmAndamento(q.id)}
                    className={`cursor-pointer rounded-xl px-4 py-2 text-[10px] font-black uppercase tracking-wider transition-all ${
                      tentativa
                        ? 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-100'
                        : 'bg-amber-600 text-white shadow-xs hover:bg-amber-500'
                    }`}
                  >
                    {tentativa ? 'Refazer' : 'Começar'}
                  </button>
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
};
