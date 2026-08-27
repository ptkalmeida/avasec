/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState } from 'react';
import { PageShell } from './PageShell';
import { SitePageContent, SitePageItem } from '../../types';
import { pageField, pageItems } from '../../utils/sitePageContent';

interface DuvidasPageProps {
  onBack: () => void;
  /** Conteúdo editado pelo admin; ausente = usa os padrões abaixo. */
  content?: SitePageContent;
}

/** Padrão de fábrica, usado quando a API não respondeu (espelha o backend). */
const DEFAULT_ITEMS: SitePageItem[] = [
  {
    id: 'faq-1',
    question: 'Como faço para emitir o meu certificado homologado?',
    answer: 'O certificado é gerado automaticamente na plataforma assim que você atingir um mínimo de 70% de frequência de participação letiva (somadas as visualizações de aulas teóricas gravadas, tarefas e presenças síncronas de mentoria).'
  },
  {
    id: 'faq-2',
    question: 'Sou aluno novo, como faço para ingressar no Ambient Virtual de Estudo?',
    answer: "Você só precisa clicar no botão 'Entrar' no topo direito e selecionar o seu perfil correspondente (Student João Silva ou Ana Souza para simular o LMS) e clicar em Conectar."
  },
  {
    id: 'faq-3',
    question: 'O que é o fomento das trilhas de Economia Criativa?',
    answer: 'As trilhas auxiliam profissionais a gerenciarem portfólios, captarem recursos em editais de fomento públicos (Lei Paulo Gustavo, Aldir Blanc) e gerarem declarações contábeis de forma simplificada.'
  }
];

export const DuvidasPage: React.FC<DuvidasPageProps> = ({ onBack, content }) => {
  const [expandedId, setExpandedId] = useState<string | null>(null);
  const items = pageItems(content, DEFAULT_ITEMS);

  const toggle = (id: string) => setExpandedId((prev) => (prev === id ? null : id));

  return (
    <PageShell
      eyebrow={pageField(content, 'eyebrow', 'Suporte ao Aluno')}
      title={pageField(content, 'title', 'Dúvidas Frequentes')}
      description={pageField(content, 'description', 'Tem dúvidas sobre como utilizar o Portal AVA? Acesse nosso FAQ rápido:')}
      align="center"
      accent="#FFD23F"
      onBack={onBack}
    >
      <div className="space-y-3.5 max-w-2xl mx-auto text-left">
        {items.map((faq) => (
          <div key={faq.id} className="border border-slate-200/70 rounded-2xl bg-slate-55/30 transition-all">
            <button
              onClick={() => toggle(faq.id)}
              aria-expanded={expandedId === faq.id}
              className="w-full flex justify-between items-center gap-3 p-4.5 text-xs font-black text-slate-800 hover:text-[#540D6E] text-left cursor-pointer"
            >
              <span>{faq.question}</span>
              <span className="text-[#540D6E] font-mono text-xs shrink-0">{expandedId === faq.id ? '▲' : '▼'}</span>
            </button>
            {expandedId === faq.id && (
              <div className="p-4.5 pt-0 border-t border-slate-100 text-xs text-slate-500 leading-relaxed font-light animate-in fade-in duration-200">
                {faq.answer}
              </div>
            )}
          </div>
        ))}
      </div>
    </PageShell>
  );
};
