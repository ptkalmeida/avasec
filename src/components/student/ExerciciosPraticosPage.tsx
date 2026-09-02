/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import {
  ArrowLeft, FileCheck, Info, Clock, CheckCircle, HelpCircle, Send,
  FileText, Paperclip, AlertTriangle, Award,
} from 'lucide-react';
import { PracticalExercise, ExerciseSubmission } from '../../types';
import {
  exerciciosDoCurso, progressoDoAluno, rotuloSituacao, aceitaEntrega, prazoDe, Tom,
} from '../../utils/exerciseStatus';

/**
 * Página de exercícios práticos de UM curso, na visão do aluno.
 *
 * Antes isto era uma aba dentro da primeira aula — mas exercício pertence ao
 * CURSO (`courseId`), não à aula. Quem abria "Atividades Práticas" era jogado na
 * aula 1 e via uma aba; se o curso tivesse dez aulas, o mesmo exercício aparecia
 * em qualquer uma delas. Agora é um lugar só, no nível certo.
 *
 * Sem estado global: recebe dados e devoluções por props, para poder ser testada
 * sem montar o provider do LMS.
 */

interface ExerciciosPraticosPageProps {
  courseTitle: string;
  courseId: string;
  /** Todos os exercícios conhecidos; a página filtra e ordena por prazo. */
  exercises: PracticalExercise[];
  submissions: ExerciseSubmission[];
  userId: string;
  onBack: () => void;
  /** Envia (ou reenvia) a entrega. Devolve erro para a tela poder mostrá-lo. */
  onSubmit: (
    exerciseId: string,
    text: string,
    fileUrl?: string,
    fileName?: string
  ) => Promise<{ ok: boolean; error?: string }>;
  /** Upload do anexo — o componente não conhece a rota. */
  onUpload: (file: File) => Promise<{ name: string; url: string } | { error: string }>;
  onDownload: (fileUrl: string, fileName?: string) => Promise<string | null>;
  /** Mensagens ao usuário (o portal usa modal próprio, não window.alert). */
  notify: (mensagem: string) => void;
  /** Flag uploadArquivos: sem ela a entrega é só texto. */
  permiteAnexo: boolean;
}

const TOM_CHIP: Record<Tom, string> = {
  ok: 'bg-emerald-50 text-emerald-800 border-emerald-200',
  aguardando: 'bg-indigo-50 text-indigo-800 border-indigo-200',
  atencao: 'bg-amber-50 text-amber-800 border-amber-200',
  neutro: 'bg-slate-100 text-slate-600 border-slate-200',
};

const ICONE_SITUACAO: Record<string, React.ReactNode> = {
  approved: <CheckCircle className="h-3.5 w-3.5" />,
  pending: <Clock className="h-3.5 w-3.5" />,
  revision: <HelpCircle className="h-3.5 w-3.5" />,
  rejected: <AlertTriangle className="h-3.5 w-3.5" />,
};

/** Um exercício: enunciado, situação, feedback e o formulário de entrega. */
const ExercicioCard: React.FC<{
  exercicio: PracticalExercise;
  submissao?: ExerciseSubmission;
  agora: Date;
  props: ExerciciosPraticosPageProps;
}> = ({ exercicio, submissao, agora, props }) => {
  const [texto, setTexto] = React.useState('');
  const [anexo, setAnexo] = React.useState<{ name: string; url: string } | null>(null);
  const [enviando, setEnviando] = React.useState(false);
  const [subindo, setSubindo] = React.useState(false);
  const [erro, setErro] = React.useState<string | null>(null);

  const rotulo = rotuloSituacao(submissao, exercicio.maxPoints);
  const prazo = prazoDe(exercicio.dueDate, agora);
  const podeEntregar = aceitaEntrega(submissao);
  // O rascunho começa com o que já foi entregue: reenviar é editar, não redigitar.
  const valor = texto !== '' ? texto : (submissao?.submissionText ?? '');
  const nomeAnexo = anexo?.name ?? submissao?.fileName;

  const enviar = async () => {
    const conteudo = valor.trim();
    if (conteudo === '') {
      setErro('Escreva sua resposta antes de enviar.');
      return;
    }
    setEnviando(true);
    setErro(null);
    const res = await props.onSubmit(
      exercicio.id,
      conteudo,
      anexo?.url ?? submissao?.fileUrl,
      anexo?.name ?? submissao?.fileName
    );
    setEnviando(false);
    if (!res.ok) {
      // A mensagem fica NO CARD, não num alerta que desaparece: antes a tela
      // dizia "enviado com sucesso" sem olhar a resposta do servidor.
      setErro(res.error ?? 'Não foi possível registrar sua entrega.');
      return;
    }
    setTexto('');
    setAnexo(null);
    props.notify('Entrega registrada. O professor foi notificado na área de correção.');
  };

  return (
    <article className="rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-2xs space-y-4">
      <header className="flex flex-col gap-3 border-b border-slate-100 pb-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="min-w-0 space-y-1">
          <h3 className="text-sm font-bold leading-snug text-slate-900">{exercicio.title}</h3>
          <p className="text-[11px] leading-relaxed text-slate-600">{exercicio.description}</p>
        </div>

        <div className="flex shrink-0 flex-wrap items-center gap-2 sm:flex-col sm:items-end">
          <span
            className={`inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[10px] font-black uppercase ${TOM_CHIP[rotulo.tom]}`}
          >
            {ICONE_SITUACAO[rotulo.situacao]}
            {rotulo.texto}
          </span>
          <span className="rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 font-mono text-[10px] font-bold text-slate-600">
            vale {exercicio.maxPoints} pts
          </span>
          {prazo !== null && (
            <span
              className={`text-[10px] font-bold ${prazo.atrasado ? 'text-rose-600' : 'text-slate-450'}`}
            >
              {prazo.texto}
            </span>
          )}
        </div>
      </header>

      <div className="space-y-1 rounded-xl border border-teal-100 bg-teal-50/30 p-3">
        <strong className="flex items-center gap-1 text-[10px] font-black uppercase tracking-wider text-teal-800">
          <Info className="h-3.5 w-3.5" /> Como entregar
        </strong>
        <p className="whitespace-pre-line text-[11px] leading-relaxed text-slate-700">
          {exercicio.instructions}
        </p>
      </div>

      {submissao !== undefined && (
        <div className="space-y-2 rounded-xl border border-slate-200 bg-slate-50/50 p-3">
          <span className="text-[10px] font-bold text-slate-500">
            Entregue em {submissao.submittedAt}
          </span>
          <p className="max-h-40 overflow-y-auto whitespace-pre-wrap rounded-lg border border-slate-200 bg-white p-2.5 font-mono text-[10px] leading-normal text-slate-700">
            {submissao.submissionText}
          </p>

          {submissao.fileName && (
            <div className="flex items-center gap-1.5 rounded-md border border-dashed border-slate-250 bg-white p-1.5 text-[10px]">
              <FileText className="h-3.5 w-3.5 text-slate-500" />
              <span className="min-w-0 truncate">{submissao.fileName}</span>
              <button
                type="button"
                onClick={async () => {
                  const err = await props.onDownload(submissao.fileUrl ?? '', submissao.fileName);
                  if (err) props.notify(err);
                }}
                className="ml-auto shrink-0 cursor-pointer font-bold text-teal-700 hover:underline"
              >
                Baixar
              </button>
            </div>
          )}

          {submissao.feedback && (
            <div className="space-y-1 rounded-xl border border-slate-200 bg-white p-3">
              <strong className="block text-[11px] font-bold text-slate-900">
                Feedback de {submissao.gradedBy ?? 'professor'}
                {submissao.gradedAt ? ` em ${submissao.gradedAt}` : ''}
              </strong>
              <p className="whitespace-pre-line text-[10.5px] italic leading-relaxed text-slate-650">
                {submissao.feedback}
              </p>
            </div>
          )}
        </div>
      )}

      {podeEntregar ? (
        <div className="space-y-3 border-t border-slate-100 pt-3">
          <label className="block space-y-1">
            <span className="text-[10.5px] font-bold text-slate-700">
              {submissao ? 'Atualizar sua resposta' : 'Sua resposta'}
            </span>
            <textarea
              value={valor}
              onChange={(e) => { setTexto(e.target.value); setErro(null); }}
              rows={5}
              placeholder="Escreva sua justificativa, roteiro ou resposta detalhada."
              className="w-full rounded-xl border border-slate-300 bg-white p-3 text-xs text-slate-800 focus:border-teal-500 focus:ring-1 focus:ring-teal-500/30 focus:outline-hidden"
            />
          </label>

          {props.permiteAnexo && (
            <div className="flex flex-wrap items-center gap-2">
              {nomeAnexo !== undefined ? (
                <span className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-100 px-2.5 py-1.5 text-[10.5px] text-slate-700">
                  <Paperclip className="h-3.5 w-3.5 text-teal-600" />
                  <span className="min-w-0 max-w-[16rem] truncate font-bold">{nomeAnexo}</span>
                  {anexo !== null && (
                    <button
                      type="button"
                      onClick={() => setAnexo(null)}
                      className="cursor-pointer text-[9px] font-black uppercase text-rose-600 hover:text-rose-700"
                    >
                      remover
                    </button>
                  )}
                </span>
              ) : (
                <label
                  className={`inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-semibold text-slate-700 hover:bg-slate-50 ${subindo ? 'pointer-events-none opacity-50' : ''}`}
                >
                  <Paperclip className="h-3.5 w-3.5 text-slate-500" />
                  <span>{subindo ? 'Enviando arquivo...' : 'Anexar documento (opcional)'}</span>
                  <input
                    type="file"
                    accept=".pdf,.docx,.png,.jpg,.jpeg,.webp,.gif"
                    className="hidden"
                    disabled={subindo}
                    onChange={async (e) => {
                      const file = e.target.files?.[0];
                      e.target.value = '';
                      if (!file) return;
                      setSubindo(true);
                      const res = await props.onUpload(file);
                      setSubindo(false);
                      if ('error' in res) { setErro(res.error); return; }
                      setAnexo(res);
                      setErro(null);
                    }}
                  />
                </label>
              )}
              <span className="text-[10px] text-slate-400">
                Visível apenas para você e para os professores.
              </span>
            </div>
          )}

          {erro !== null && (
            <p className="flex items-start gap-1.5 rounded-lg border border-rose-200 bg-rose-50 p-2.5 text-[10.5px] font-bold leading-relaxed text-rose-700">
              <AlertTriangle className="mt-px h-3.5 w-3.5 shrink-0" />
              {erro}
            </p>
          )}

          <button
            type="button"
            onClick={enviar}
            disabled={enviando}
            className="ml-auto flex cursor-pointer items-center gap-1.5 rounded-xl bg-teal-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition-colors hover:bg-teal-500 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <Send className="h-3.5 w-3.5" />
            <span>
              {enviando ? 'Enviando...' : submissao ? 'Reenviar resposta' : 'Enviar para correção'}
            </span>
          </button>
        </div>
      ) : (
        <p className="border-t border-slate-100 pt-3 text-[10.5px] font-bold text-emerald-700">
          Atividade concluída e aprovada. Nada mais a entregar aqui.
        </p>
      )}
    </article>
  );
};

export const ExerciciosPraticosPage: React.FC<ExerciciosPraticosPageProps> = (props) => {
  const { courseTitle, courseId, exercises, submissions, userId, onBack } = props;

  // Um "agora" por render: dois cards da mesma lista precisam comparar o prazo
  // com o mesmo instante.
  const agora = React.useMemo(() => new Date(), [exercises, submissions]);
  const doCurso = React.useMemo(() => exerciciosDoCurso(exercises, courseId), [exercises, courseId]);
  const progresso = React.useMemo(
    () => progressoDoAluno(doCurso, submissions, userId),
    [doCurso, submissions, userId]
  );

  return (
    <div className="mx-auto w-full max-w-4xl space-y-6 text-left">
      <button
        type="button"
        onClick={onBack}
        className="inline-flex cursor-pointer items-center gap-1.5 text-[11px] font-black uppercase tracking-wider text-slate-500 hover:text-slate-800"
      >
        <ArrowLeft className="h-3.5 w-3.5" />
        Voltar ao curso
      </button>

      <header className="space-y-3 rounded-2xl border border-teal-100 bg-teal-50/25 p-5">
        <div className="space-y-1">
          <span className="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-teal-700">
            <FileCheck className="h-3.5 w-3.5" />
            Exercícios práticos
          </span>
          <h2 className="text-lg font-black leading-tight text-slate-900">{courseTitle}</h2>
        </div>

        {doCurso.length > 0 && (
          <dl className="flex flex-wrap gap-x-6 gap-y-2 border-t border-teal-100 pt-3 text-[11px]">
            <div>
              <dt className="font-bold uppercase tracking-wide text-slate-450">Atividades</dt>
              <dd className="font-mono text-sm font-black text-slate-900">{progresso.total}</dd>
            </div>
            <div>
              <dt className="font-bold uppercase tracking-wide text-slate-450">Entregues</dt>
              <dd className="font-mono text-sm font-black text-slate-900">
                {progresso.entregues} de {progresso.total}
              </dd>
            </div>
            <div>
              <dt className="font-bold uppercase tracking-wide text-slate-450">Corrigidas</dt>
              <dd className="font-mono text-sm font-black text-slate-900">{progresso.corrigidos}</dd>
            </div>
            {progresso.corrigidos > 0 && (
              <div>
                <dt className="flex items-center gap-1 font-bold uppercase tracking-wide text-slate-450">
                  <Award className="h-3 w-3 text-emerald-600" /> Pontos
                </dt>
                {/* Só o que já foi corrigido entra na conta — a fila do professor
                    não deve fazer a nota do aluno parecer pior do que é. */}
                <dd className="font-mono text-sm font-black text-emerald-700">
                  {progresso.pontos} de {progresso.pontosPossiveis}
                </dd>
              </div>
            )}
          </dl>
        )}
      </header>

      {doCurso.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-slate-250 bg-slate-50/60 p-10 text-center">
          <FileCheck className="mx-auto mb-3 h-8 w-8 text-slate-300" />
          <strong className="block text-sm font-bold text-slate-700">
            Este curso ainda não tem exercícios práticos.
          </strong>
          <span className="mx-auto mt-1 block max-w-md text-xs leading-relaxed text-slate-500">
            Quando o professor lançar uma atividade, ela aparece aqui com o enunciado,
            o prazo e o espaço para você entregar.
          </span>
        </div>
      ) : (
        <div className="space-y-5">
          {doCurso.map((exercicio) => (
            <ExercicioCard
              key={exercicio.id}
              exercicio={exercicio}
              submissao={submissions.find(
                (s) => s.exerciseId === exercicio.id && s.userId === userId
              )}
              agora={agora}
              props={props}
            />
          ))}
        </div>
      )}
    </div>
  );
};
