/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import { createPortal } from 'react-dom';

/** Classe do contêiner de impressão — filho direto do <body>. */
export const CLASSE_AREA_IMPRESSAO = 'area-impressao';

/**
 * Regras de impressão, num lugar só. Três telas copiavam a mesma regra quebrada
 * (histórico, certificado e visualizador de documento do admin):
 *
 * 1. `body * { visibility: hidden }` esconde SEM tirar do layout — a tela por
 *    trás do modal continuava ocupando a altura dela, e o PDF saía com tantas
 *    páginas quanto a tela de fundo (4 no caso relatado).
 * 2. O documento vivia dentro de um overlay `position: fixed`, e o navegador
 *    repete elemento fixo em TODA página impressa: a mesma folha 4 vezes.
 * 3. O overlay tinha a altura de uma tela e cortava o resto — a seção de
 *    certificados do histórico não saía em página nenhuma.
 *
 * Aqui o documento é filho direto do <body>, o resto sai com `display: none`
 * (fora do layout de verdade) e nada é `fixed`: o navegador pagina pelo tamanho
 * real do conteúdo.
 *
 * `max-height: none` e `overflow: visible` valem para todos os descendentes
 * porque um documento impresso nunca deve ser recortado: o visualizador do
 * admin, por exemplo, tinha `max-h-[70vh] overflow-y-auto` no próprio papel.
 */
const CSS_IMPRESSAO = `
.${CLASSE_AREA_IMPRESSAO} { display: none; }

@media print {
  @page { size: A4; margin: 15mm; }

  html, body {
    height: auto !important;
    overflow: visible !important;
    background: #ffffff !important;
  }

  body > *:not(.${CLASSE_AREA_IMPRESSAO}) { display: none !important; }

  .${CLASSE_AREA_IMPRESSAO} {
    display: block !important;
    position: static !important;
    width: 100% !important;
    background: #ffffff !important;
    color: #000000 !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .${CLASSE_AREA_IMPRESSAO} * {
    max-height: none !important;
    overflow: visible !important;
    box-shadow: none !important;
  }

  .${CLASSE_AREA_IMPRESSAO} table { width: 100% !important; table-layout: auto; }
  /* Cabeçalho da tabela repetido no topo de cada página. */
  .${CLASSE_AREA_IMPRESSAO} thead { display: table-header-group; }
  .${CLASSE_AREA_IMPRESSAO} th,
  .${CLASSE_AREA_IMPRESSAO} td { white-space: normal !important; overflow-wrap: anywhere; }

  /* Linha de tabela e título não se partem no pé da página. */
  .${CLASSE_AREA_IMPRESSAO} tr { break-inside: avoid; page-break-inside: avoid; }
  .${CLASSE_AREA_IMPRESSAO} h1,
  .${CLASSE_AREA_IMPRESSAO} h2,
  .${CLASSE_AREA_IMPRESSAO} h3 { break-after: avoid; page-break-after: avoid; }
}
`;

/**
 * Cópia do documento que existe SÓ para a impressão: invisível na tela, filho
 * direto do <body>, montada e desmontada junto com quem a usa.
 */
export const AreaDeImpressao: React.FC<{ children: React.ReactNode }> = ({ children }) =>
  createPortal(
    <div className={CLASSE_AREA_IMPRESSAO}>
      <style>{CSS_IMPRESSAO}</style>
      {children}
    </div>,
    document.body,
  );

/**
 * O documento como ele deve aparecer na tela (dentro do modal) E a cópia de
 * impressão, a partir de um único trecho de JSX. Renderizar o mesmo elemento
 * duas vezes é seguro no React: o elemento é só uma descrição.
 *
 * Por isso quem usa não precisa mover nem duplicar o markup do documento — basta
 * envolvê-lo. E a tela continua exatamente como era.
 */
export const DocumentoImprimivel: React.FC<{ children: React.ReactNode }> = ({ children }) => (
  <>
    {children}
    <AreaDeImpressao>{children}</AreaDeImpressao>
  </>
);
