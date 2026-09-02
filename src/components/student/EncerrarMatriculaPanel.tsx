/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import { BookOpen, AlertTriangle, LogOut } from 'lucide-react';
import { Course } from '../../types';

/**
 * Encerramento de matrícula, no Perfil.
 *
 * Antes era um bloco vermelho fixo na página do curso, ao lado do conteúdo de
 * estudo: dava destaque de call-to-action para a ação mais destrutiva que o
 * aluno pode tomar, no lugar onde ele vai para estudar. Aqui fica junto com o
 * resto da gestão da conta, discreto, e a confirmação acontece na própria
 * linha — sem `window.confirm` e sem botão vermelho solto.
 */

interface EncerrarMatriculaPanelProps {
  /** Cursos em que o aluno está matriculado agora. */
  matriculas: Course[];
  /** Dias sem penalidade; só é exibido quando a flag de penalidade está ativa. */
  diasSemPenalidade: number | null;
  onDrop: (courseId: string) => Promise<{ ok: boolean; penaltyApplied: boolean; error?: string }>;
  /** Mensagem lida em voz alta / exibida após a ação. */
  notify: (mensagem: string) => void;
}

export const EncerrarMatriculaPanel: React.FC<EncerrarMatriculaPanelProps> = ({
  matriculas, diasSemPenalidade, onDrop, notify,
}) => {
  const [confirmando, setConfirmando] = React.useState<string | null>(null);
  const [saindo, setSaindo] = React.useState<string | null>(null);
  const [erro, setErro] = React.useState<string | null>(null);
  /** Confirmação fica NO painel: o Perfil não tem toast, e `notify` só fala. */
  const [aviso, setAviso] = React.useState<string | null>(null);

  const sair = async (curso: Course) => {
    setSaindo(curso.id);
    setErro(null);
    setAviso(null);
    // A decisão de penalidade é do SERVIDOR (dias reais desde a matrícula + flag).
    const res = await onDrop(curso.id);
    setSaindo(null);
    if (!res.ok) {
      setErro(res.error ?? 'Não foi possível encerrar a matrícula.');
      return;
    }
    setConfirmando(null);
    const mensagem = res.penaltyApplied
      ? `Matrícula em ${curso.title} encerrada. Como o pedido veio após o prazo sem penalidade, uma restrição temporária para nova matrícula foi aplicada.`
      : `Matrícula em ${curso.title} encerrada.`;
    setAviso(mensagem);
    notify(mensagem);
  };

  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-6 text-left shadow-3xs">
      <span className="mb-4 flex items-center gap-1.5 text-[9px] font-bold uppercase tracking-widest text-slate-450">
        <BookOpen className="h-3.5 w-3.5" />
        <span>Minhas matrículas</span>
      </span>

      {aviso !== null && (
        <p className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-[11px] font-bold leading-relaxed text-emerald-800">
          {aviso}
        </p>
      )}

      {matriculas.length === 0 ? (
        <p className="text-[11px] leading-relaxed text-slate-500">
          Você não tem matrícula ativa. Cursos já concluídos continuam disponíveis
          para revisão e não precisam ser encerrados.
        </p>
      ) : (
        <>
          <p className="mb-4 text-[11px] leading-relaxed text-slate-500">
            Encerrar uma matrícula devolve a vaga e interrompe o seu acesso ao conteúdo
            do curso.
            {diasSemPenalidade !== null && (
              <> Pedidos após {diasSemPenalidade} dias de matrícula geram restrição
              temporária para nova inscrição.</>
            )}
          </p>

          <ul className="space-y-2">
            {matriculas.map((curso) => (
              <li key={curso.id} className="rounded-xl border border-slate-150 bg-slate-50/60 p-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div className="min-w-0">
                    <strong className="block text-xs font-bold leading-snug text-slate-900">
                      {curso.title}
                    </strong>
                    <span className="block text-[10px] text-slate-450">{curso.category}</span>
                  </div>

                  {confirmando !== curso.id && (
                    <button
                      type="button"
                      onClick={() => { setConfirmando(curso.id); setErro(null); }}
                      className="shrink-0 cursor-pointer text-[10px] font-bold uppercase tracking-wider text-slate-500 underline decoration-slate-300 underline-offset-2 hover:text-rose-600 hover:decoration-rose-300"
                    >
                      Solicitar saída
                    </button>
                  )}
                </div>

                {/* Confirmação na própria linha: a ação é irreversível, então
                    precisa de um segundo passo — mas sem alarme visual antes de
                    a pessoa demonstrar intenção. */}
                {confirmando === curso.id && (
                  <div className="mt-3 space-y-2 border-t border-slate-200 pt-3">
                    <p className="flex items-start gap-1.5 text-[11px] font-bold leading-relaxed text-slate-700">
                      <AlertTriangle className="mt-px h-3.5 w-3.5 shrink-0 text-amber-500" />
                      Encerrar sua matrícula em {curso.title}? Você perde o acesso às
                      aulas deste curso.
                    </p>

                    {erro !== null && (
                      <p className="rounded-lg border border-rose-200 bg-rose-50 p-2 text-[10.5px] font-bold text-rose-700">
                        {erro}
                      </p>
                    )}

                    <div className="flex flex-wrap gap-2">
                      <button
                        type="button"
                        disabled={saindo === curso.id}
                        onClick={() => sair(curso)}
                        className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-rose-600 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-white hover:bg-rose-500 disabled:opacity-60"
                      >
                        <LogOut className="h-3.5 w-3.5" />
                        {saindo === curso.id ? 'Encerrando...' : 'Confirmar saída'}
                      </button>
                      <button
                        type="button"
                        onClick={() => { setConfirmando(null); setErro(null); }}
                        className="cursor-pointer rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-600 hover:bg-slate-100"
                      >
                        Manter matrícula
                      </button>
                    </div>
                  </div>
                )}
              </li>
            ))}
          </ul>
        </>
      )}
    </div>
  );
};
