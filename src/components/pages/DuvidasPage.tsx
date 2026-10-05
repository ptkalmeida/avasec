/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState } from 'react';
import { ChevronDown } from 'lucide-react';
import { PageShell } from './PageShell';
import { SitePageContent, SitePageItem } from '../../types';
import { pageField, pageItems } from '../../utils/sitePageContent';

interface DuvidasPageProps {
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

export const DuvidasPage: React.FC<DuvidasPageProps> = ({ content }) => {
  const [expandedId, setExpandedId] = useState<string | null>(null);
  const items = pageItems(content, DEFAULT_ITEMS);

  const toggle = (id: string) => setExpandedId((prev) => (prev === id ? null : id));

  /*
   * `accent` colore o sobretitulo. Era #FFD23F: amarelo sobre branco da
   * 1,44:1, e o piso e 4,5:1 — o texto ficava praticamente invisivel.
   * Amarelo e "destaque pontual sobre fundo ESCURO" (Bloco 4).
   */
  return (
    <PageShell
      eyebrow={pageField(content, 'eyebrow', 'Suporte ao Aluno')}
      title={pageField(content, 'title', 'Dúvidas Frequentes')}
      description={pageField(content, 'description', 'Tem dúvidas sobre como utilizar o Portal AVA? Acesse nosso FAQ rápido:')}
      align="center"
    >
      <div className="space-y-3 max-w-3xl mx-auto text-left">
        {items.map((faq) => (
          <div key={faq.id} className="border border-ava-borda rounded-xl bg-white transition-all">
            <button
              onClick={() => toggle(faq.id)}
              aria-expanded={expandedId === faq.id}
              className="w-full flex justify-between items-center gap-3 px-5 py-4 text-rotulo font-bold text-ava-tinta hover:text-ava-acao text-left cursor-pointer rounded-xl"
            >
              <span>{faq.question}</span>
              <ChevronDown className={`h-5 w-5 text-ava-acao shrink-0 transition-transform ${expandedId === faq.id ? 'rotate-180' : ''}`} aria-hidden="true" />
            </button>
            {expandedId === faq.id && (
              <div className="px-5 pb-4 pt-3 border-t border-ava-borda text-apoio text-escult-ink-2 leading-relaxed animate-in fade-in duration-200">
                {faq.answer}
              </div>
            )}
          </div>
        ))}
      </div>
    </PageShell>
  );
};
