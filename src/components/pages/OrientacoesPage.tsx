/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import { PageShell } from './PageShell';
import { SitePageContent, SitePageItem } from '../../types';
import { pageField, pageItems } from '../../utils/sitePageContent';

interface OrientacoesPageProps {
  /** Conteúdo editado pelo admin; ausente = usa os padrões abaixo. */
  content?: SitePageContent;
}

/** Padrão de fábrica, usado quando a API não respondeu (espelha o backend). */
const DEFAULT_ITEMS: SitePageItem[] = [
  {
    id: 'diretriz-1',
    title: 'Matrícula Simples',
    description: 'Qualquer aluno cadastrado pode se matricular em um curso ativo por vez. A troca de cursos é permitida de forma simples pelo painel.'
  },
  {
    id: 'diretriz-2',
    title: 'Roteiro Letivo',
    description: 'Os módulos são sequenciais. É altamente recomendável assistir às videoaulas na ordem cronológica proposta para melhor absorção.'
  },
  {
    id: 'diretriz-3',
    title: 'Apoio e Tutoria',
    description: 'Caso tenha dúvidas nas aulas, envie mensagens diretas aos tutores através da aba de Suporte ou utilize as salas de chat comunitárias.'
  },
  {
    id: 'diretriz-4',
    title: 'Acessibilidade Total',
    description: 'A plataforma conta com leitor de tela nativo, reguladores de contraste e aumentador de fontes para garantir a inclusão de todos.'
  }
];

/** Numeração romana das diretrizes — derivada da ordem, não editável. */
const ROMANOS = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

export const OrientacoesPage: React.FC<OrientacoesPageProps> = ({ content }) => {
  const items = pageItems(content, DEFAULT_ITEMS);

  return (
    <PageShell
      eyebrow={pageField(content, 'eyebrow', 'Manual do Estudante')}
      title={pageField(content, 'title', 'Orientações Gerais')}
      description={pageField(content, 'description', 'Consulte as orientações e diretrizes de como interagir com o AVA da Escola Estadual da Cultura e garanta uma experiência de aprendizado transformadora.')}
      align="center"
    >
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 text-left">
        {items.map((o, index) => (
          <div
            key={o.id}
            className="bg-white rounded-2xl p-5 border border-ava-borda shadow-3xs hover:shadow-md transition-all space-y-3"
          >
            <span className="text-sobretitulo text-ava-tinta uppercase bg-ava-icone-fundo rounded-full px-3 py-1 inline-block">
              Diretriz {ROMANOS[index] ?? index + 1}
            </span>
            <h4 className="text-cartao text-ava-tinta font-titulo">{o.title}</h4>
            <p className="text-rotulo text-escult-ink-2 leading-relaxed">{o.description}</p>
          </div>
        ))}
      </div>
    </PageShell>
  );
};
