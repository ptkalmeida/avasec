/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';

/**
 * Assinatura visual do portal (planejamento 12): fio fino violeta -> ciano que
 * termina em dois losangos entrelaçados, como na referência aprovada. Fica no
 * topo do cabeçalho e no topo do rodapé, e em mais nenhum lugar — repetida em
 * toda seção ela deixaria de ser assinatura e viraria ruído.
 *
 * É enfeite: `aria-hidden`, e escondida no alto contraste pelo CSS.
 */
export const LinhaAssinatura: React.FC<{ className?: string }> = ({ className = '' }) => (
  <div className={`linha-assinatura-wrap flex items-center gap-1 ${className}`} aria-hidden="true">
    <div className="linha-assinatura h-px flex-1" />
    <svg className="h-3 w-6 shrink-0" viewBox="0 0 24 12" fill="none">
      <path d="M6 1 11 6 6 11 1 6Z" stroke="var(--color-ava-acao)" strokeWidth="1.4" />
      <path d="M15 1 20 6 15 11 10 6Z" stroke="var(--color-ava-ciano)" strokeWidth="1.4" />
    </svg>
  </div>
);
