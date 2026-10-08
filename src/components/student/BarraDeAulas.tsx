/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useEffect, useRef } from 'react';
import { Check } from 'lucide-react';
import { Lesson } from '../../types';

interface BarraDeAulasProps {
  aulas: Lesson[];
  aulaAtualId: string;
  concluidas: string[];
  onAbrir: (aula: Lesson) => void;
}

/**
 * Barra de aulas fixa no rodapé da sala de aula (tela 15 aprovada).
 *
 * Por que ela existe: com a aula ABERTA não havia lista de aulas na tela — a
 * barra lateral só aparece na grade do curso (`!activeLesson`), e no fim do
 * conteúdo ficavam apenas "Aula anterior" e "Próxima aula". Para ir da aula 1
 * à aula 4 era preciso sair do curso e voltar.
 *
 * O que ela NÃO faz, de propósito:
 *
 * - não conclui aula. Quem conclui é o botão "Próxima aula" (decisão de
 *   08/09/2026), e por isso os dois botões continuam existindo. Esta barra
 *   localiza e leva; não mexe em frequência.
 * - não mostra módulo. Módulo não existe no banco (`Lesson` não tem coluna de
 *   módulo e não há tabela `Module`); a tela 15 traz "Módulo 1" e "Próximo
 *   módulo", que aqui seriam estrutura inventada — decisão da coordenação de
 *   09/09/2026.
 * - não leva barra de progresso nem ícone de play dentro dela, como a própria
 *   referência da skill pede.
 *
 * A aula concluída é marcada com ✓ **e** com texto no rótulo acessível, não só
 * com cor; a aula atual leva `aria-current`.
 */
export const BarraDeAulas: React.FC<BarraDeAulasProps> = ({
  aulas,
  aulaAtualId,
  concluidas,
  onAbrir,
}) => {
  const atualRef = useRef<HTMLButtonElement>(null);
  const trilhoRef = useRef<HTMLDivElement>(null);

  // Em tela estreita a barra rola na horizontal; a aula atual tem de estar à
  // vista ao abrir, senão a barra parece começar sempre na aula 1.
  //
  // Por que NÃO é `scrollIntoView`: ele sobe a cadeia de ancestrais roláveis e
  // acaba rolando a PÁGINA junto, não só este trilho. Em 320 px isso deslocava
  // a sala de aula verticalmente de forma não determinística — duas aberturas
  // da mesma aula paravam em posições diferentes. Aqui mexemos em `scrollLeft`
  // só do trilho, que é o único eixo que esta barra tem.
  useEffect(() => {
    const trilho = trilhoRef.current;
    const atual = atualRef.current;
    if (!trilho || !atual) return;

    const centro = atual.offsetLeft - (trilho.clientWidth - atual.offsetWidth) / 2;
    trilho.scrollLeft = Math.max(0, centro);
  }, [aulaAtualId]);

  if (aulas.length < 2) return null;

  return (
    <nav
      aria-label="Aulas do curso"
      className="fixed inset-x-0 bottom-0 z-30 border-t border-ava-borda bg-white/95 backdrop-blur-sm shadow-[0_-2px_12px_rgba(4,24,92,0.06)] print:hidden"
    >
      <div className="mx-auto max-w-7xl px-3 py-2.5">
        <div ref={trilhoRef} className="flex items-stretch gap-2 overflow-x-auto no-scrollbar">
          {aulas.map((aula, idx) => {
            const atual = aula.id === aulaAtualId;
            const concluida = concluidas.includes(aula.id);

            return (
              <button
                key={aula.id}
                ref={atual ? atualRef : undefined}
                type="button"
                onClick={() => onAbrir(aula)}
                aria-current={atual ? 'true' : undefined}
                title={aula.title}
                className={`shrink-0 max-w-[15rem] rounded-xl px-3 py-2 text-left text-xs transition-colors cursor-pointer border focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ava-acao ${
                  atual
                    ? 'border-ava-borda bg-ava-faixa text-ava-tinta font-black shadow-[inset_0_-3px_0_0_var(--color-ava-ciano)]'
                    : 'border-transparent text-escult-ink-2 font-semibold hover:bg-ava-icone-fundo'
                }`}
              >
                <span className="flex items-center gap-1.5">
                  <span className={atual ? 'text-ava-acao' : 'text-ava-ciano-texto'}>{idx + 1}</span>
                  <span className="truncate">{aula.title}</span>
                  {concluida && (
                    <>
                      <Check className="h-3.5 w-3.5 shrink-0 text-emerald-700" aria-hidden="true" />
                      <span className="sr-only">(concluída)</span>
                    </>
                  )}
                </span>
              </button>
            );
          })}
        </div>
      </div>
    </nav>
  );
};
