/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import { ChevronDown } from 'lucide-react';
import { AnchoredMenu } from '../shared/AnchoredMenu';
import { MENU_PUBLICO, entradaAtiva } from '../../config/menuPublico';
import { PortalView } from '../../router/portalRoutes';

/**
 * Menu do portal público: dois links diretos e dois grupos.
 *
 * Substitui nove botões de 11 px em caixa alta, todos do mesmo peso e cor, sem
 * indicação de página atual. A estrutura e a regra de "qual entrada está ativa"
 * vivem em `config/menuPublico.ts`, testadas fora do navegador; aqui fica só a
 * apresentação e o comportamento de abrir/fechar.
 *
 * O painel reaproveita `AnchoredMenu`, que já resolve clique-fora, `Escape` e
 * não vazar da janela — os três lugares onde um menu suspenso escrito à mão
 * falha. Ele foi criado para o menu de três pontos de uma tabela e ganhou
 * `align="left"` para este uso.
 *
 * Abre no CLIQUE, não no hover: hover exige mira e mão firme, e o público
 * declarado é de adultos com pouca familiaridade digital. Um grupo aberto fecha
 * o outro, porque dois painéis abertos ao mesmo tempo se sobrepõem.
 */
interface NavegacaoPublicaProps {
  /** Tela atual, para acender a entrada correspondente. */
  view: PortalView;
  /** Navega para a tela, anunciando o destino ao leitor de tela. */
  irPara: (view: PortalView, anuncio: string) => void;
}

export const NavegacaoPublica: React.FC<NavegacaoPublicaProps> = ({ view, irPara }) => {
  const [aberto, setAberto] = React.useState<'escola' | 'ajuda' | null>(null);
  const gatilhos = React.useRef<Record<string, HTMLButtonElement | null>>({});
  const ativa = entradaAtiva(view);

  const navegar = (destino: PortalView, rotulo: string): void => {
    // Fecha o painel antes de navegar: deixá-lo aberto sobre a tela nova é o
    // erro clássico de menu em portal, e a pessoa clica atrás dele sem entender.
    setAberto(null);
    irPara(destino, rotulo);
  };

  return (
    <nav className="hidden lg:flex items-center gap-1" aria-label="Navegação principal">
      {MENU_PUBLICO.map((entrada) => {
        const estaAtiva = ativa === entrada.rotulo;

        if (entrada.tipo === 'link') {
          return (
            <button
              key={entrada.rotulo}
              onClick={() => navegar(entrada.view, entrada.rotulo)}
              aria-current={estaAtiva ? 'page' : undefined}
              className="px-4 py-2.5 rounded-[10px] text-[15px] font-semibold text-[#1d2432] hover:bg-[#f4f2ef] transition-colors cursor-pointer whitespace-nowrap flex items-center gap-2"
            >
              <span>{entrada.rotulo}</span>
              {/*
                Ponto de 6px em vez de sublinhado ou negrito: o menu ja e todo
                peso 600, entao engrossar o item ativo nao o distinguiria.
                `aria-hidden` porque quem usa leitor de tela recebe a mesma
                informacao por `aria-current`, e nao por uma bolinha.
              */}
              {estaAtiva && <span className="h-1.5 w-1.5 rounded-full bg-[#EE4266]" aria-hidden="true" />}
            </button>
          );
        }

        const estaAberto = aberto === entrada.id;

        return (
          <div key={entrada.rotulo} className="relative">
            <button
              ref={(el) => { gatilhos.current[entrada.id] = el; }}
              onClick={() => setAberto(estaAberto ? null : entrada.id)}
              aria-haspopup="true"
              aria-expanded={estaAberto}
              aria-current={estaAtiva ? 'page' : undefined}
              className="px-4 py-2.5 rounded-[10px] text-[15px] font-semibold text-[#1d2432] hover:bg-[#f4f2ef] transition-colors cursor-pointer whitespace-nowrap flex items-center gap-1.5"
            >
              <span>{entrada.rotulo}</span>
              {estaAtiva && <span className="h-1.5 w-1.5 rounded-full bg-[#EE4266]" aria-hidden="true" />}
              <ChevronDown
                className={`h-4 w-4 text-[#6b7385] transition-transform ${estaAberto ? 'rotate-180' : ''}`}
                aria-hidden="true"
              />
            </button>

            {estaAberto && (
              <AnchoredMenu
                anchor={gatilhos.current[entrada.id] ?? null}
                onClose={() => setAberto(null)}
                width={280}
                align="left"
              >
                <div className="p-2 space-y-0.5">
                  {entrada.itens.map((item) => (
                    <button
                      key={item.rotulo}
                      onClick={() => navegar(item.view, item.rotulo)}
                      className="w-full text-left px-3.5 py-3 rounded-[10px] hover:bg-[#f4f2ef] transition-colors cursor-pointer"
                    >
                      <span className="block text-[15px] font-semibold text-[#1d2432] leading-snug">
                        {item.rotulo}
                      </span>
                      <span className="block text-[13px] text-[#6b7385] leading-snug mt-0.5">
                        {item.descricao}
                      </span>
                    </button>
                  ))}
                </div>
              </AnchoredMenu>
            )}
          </div>
        );
      })}
    </nav>
  );
};
