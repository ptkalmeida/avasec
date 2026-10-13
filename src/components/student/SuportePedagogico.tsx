import React from 'react';
import { CheckCircle, HelpCircle, Send, User } from 'lucide-react';

interface SuportePedagogicoProps {
  /** Nome do instrutor responsável pelo curso. */
  instrutor: string;
  mensagem: string;
  onMensagemChange: (valor: string) => void;
  onEnviar: () => void;
  /** Liga a confirmação "Mensagem enviada!" por alguns segundos. */
  enviada: boolean;
  /**
   * `lateral` é o cartão da barra da tela 07, a partir de `lg`. `corpo` é a aba
   * dentro do conteúdo da aula, usada abaixo de `lg`, onde não há barra lateral.
   */
  variante: 'lateral' | 'corpo';
  className?: string;
}

/**
 * "Suporte Pedagógico" — o canal de dúvidas da aula.
 *
 * Na referência 07 ele é **cartão da lateral**, não aba: o texto da entrega diz
 * "aba de Anotações Privadas e card de Suporte Pedagógico". A diferença importa.
 * Como aba, o aluno só descobre que existe canal de dúvidas se sair do conteúdo
 * para procurar; como cartão, ele está à vista enquanto se lê a aula.
 *
 * Abaixo de `lg` não há lateral, então volta a ser aba — mesmo componente, mesmo
 * estado, só o arranjo muda. Os dois nunca aparecem juntos.
 *
 * O QUE FOI DEIXADO DE FORA, DE PROPÓSITO
 *
 * A versão em aba trazia um selo "ONLINE/OFFLINE" e a linha "Tempo de resposta
 * esperado: < 2 horas". Nenhum dos dois tem dado por trás: o selo vinha de
 * `localStorage` do navegador do PRÓPRIO ALUNO (com 'online' por padrão, isto é,
 * verde para todo mundo sempre), e o prazo estava escrito no código. Um aluno que
 * lê "ONLINE" e "< 2 horas" acredita que há alguém do outro lado agora, e o
 * sistema não sabe disso. Promover o cartão para a lateral deixaria as duas
 * afirmações à vista o tempo todo, em vez de escondidas atrás de uma aba.
 * Decisão do responsável em 13/10/2026: sair sem os dois.
 *
 * A mensagem continua indo para `sendDirectMessage`, que é real e verificável no
 * canal de mensagens — essa parte não mudou.
 */
export const SuportePedagogico: React.FC<SuportePedagogicoProps> = ({
  instrutor,
  mensagem,
  onMensagemChange,
  onEnviar,
  enviada,
  variante,
  className = '',
}) => {
  const naLateral = variante === 'lateral';

  const corpo = (
    <>
      <p className="text-rotulo text-escult-ink-2 leading-relaxed">
        Tem dúvidas sobre o conteúdo desta aula ou sobre algum problema técnico? Envie sua mensagem
        diretamente ao Gestor de Conteúdos abaixo — ela é registrada no seu canal de mensagens e
        respondida por lá.
      </p>

      <div className="bg-slate-50 border border-slate-150 rounded-xl p-3 flex items-center gap-3">
        <User className="h-8 w-8 text-escult-ink-2 p-1 bg-slate-200 rounded-full shrink-0" />
        <strong className="text-slate-900 font-bold leading-tight min-w-0">
          Prof. {instrutor}
        </strong>
      </div>

      <div className="space-y-2">
        <textarea
          value={mensagem}
          onChange={(e) => onMensagemChange(e.target.value)}
          aria-label="Sua dúvida ou relato sobre esta aula"
          placeholder="Escreva sua dúvida ou relate um problema sobre esta aula..."
          className="w-full rounded-xl border border-slate-200 bg-slate-50/50 p-3 h-24 focus:outline-hidden focus:ring-1 focus:ring-ava-acao text-xs font-sans text-slate-800"
        />
        <div className="flex items-center justify-end gap-3 flex-wrap">
          {enviada && (
            <span className="text-apoio font-bold text-emerald-600 flex items-center gap-1">
              <CheckCircle className="h-3.5 w-3.5" />
              Mensagem enviada!
            </span>
          )}
          <button
            onClick={onEnviar}
            disabled={!mensagem.trim()}
            className="rounded-lg bg-ava-acao hover:bg-ava-acao-escuro disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-semibold text-xs px-4 py-1.8 shadow-xs transition-colors cursor-pointer flex items-center gap-1.5"
          >
            <Send className="h-3.5 w-3.5" />
            <span>Enviar Mensagem</span>
          </button>
        </div>
      </div>
    </>
  );

  if (naLateral) {
    return (
      <div className={`rounded-2xl border border-slate-200 bg-white p-4 space-y-3 ${className}`}>
        <h4 className="text-slate-950 text-sobretitulo uppercase flex items-center gap-2">
          <HelpCircle className="h-4 w-4 text-ava-ciano-texto" />
          Suporte Pedagógico
        </h4>
        {corpo}
      </div>
    );
  }

  return (
    <div className={`space-y-4 ${className}`}>
      <h4 className="font-bold text-slate-900 flex items-center gap-1.5 pb-2 border-b border-slate-100">
        <HelpCircle className="h-4 w-4 text-ava-ciano" />
        <span>Suporte Pedagógico</span>
      </h4>
      {corpo}
    </div>
  );
};
