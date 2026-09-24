/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Course, isCourseExpired } from '../types';

/**
 * Texto de um curso no seletor "Curso ativo" do painel do instrutor.
 *
 * Havia dois seletores ligados à mesma escolha; o antigo ("Selecione o curso a
 * gerenciar") saiu, e o que só ele dizia veio para cá: a categoria e a marca de
 * vigência encerrada — curso com contrato vencido não pode abrir sem aviso.
 */
export function rotuloDoCursoGerido(curso: Pick<Course, 'title' | 'category' | 'contractExpirationDate'>): string {
  const vigencia = isCourseExpired(curso.contractExpirationDate) ? '[VIGÊNCIA ENCERRADA] ' : '';
  const categoria = (curso.category ?? '').trim();

  return `${vigencia}${categoria === '' ? '' : `${categoria} • `}${curso.title}`;
}
