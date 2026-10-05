/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Trilha de navegação do painel do instrutor.
 *
 * Era a última superfície com o padrão antigo: um botão "Voltar" próprio no
 * cabeçalho, com texto que mudava conforme o nível (`getBackLabel`), mais três
 * `<BackButton>` colados dentro das seções, cada um com o seu texto — "Voltar
 * ao Painel do Instrutor" e variantes. Nenhum deles dizia ONDE a pessoa estava.
 *
 * A hierarquia sempre existiu no endereço (`/inst/curso/<slug>/<secao>`); ela
 * só não estava na tela. Aqui vira dado, como no painel do aluno.
 */

import { DestinoInstrutor, SecaoInstrutor } from '../router/instructorRoutes';

/** Um degrau da trilha. `secao` ausente = degrau atual, não clicável. */
export interface DegrauDoInstrutor {
  rotulo: string;
  /** Seção que o degrau abre. Ausente no degrau atual. */
  secao?: SecaoInstrutor;
}

/** Nome de cada seção na trilha. */
const NOME_DA_SECAO: Record<SecaoInstrutor, string> = {
  gestao: 'Gestão do curso',
  'grade-curricular': 'Grade curricular',
  avaliacoes: 'Avaliações',
  alunos: 'Alunos',
  mensagens: 'Mensagens',
};

/**
 * Degraus DEPOIS do primeiro — o componente insere "Painel do Instrutor".
 *
 * Lista vazia na tela de escolha de curso: sem curso não há hierarquia, e uma
 * barra com um degrau só não informa nada.
 */
export function trilhaDoInstrutor(
  destino: DestinoInstrutor,
  tituloDoCurso?: string | null
): DegrauDoInstrutor[] {
  if (destino.cursoRef === null) return [];

  /*
   * O endereço traz o slug (ADR 13). Mostrar `gestao-cultural-...` na trilha
   * seria trocar um identificador por outro — o ponto da trilha é dizer o nome.
   * Sem título (catálogo ainda carregando) o degrau diz "Curso", e muda quando
   * os dados chegam.
   */
  const nome = (tituloDoCurso ?? '').trim() === '' ? 'Curso' : (tituloDoCurso as string);

  if (destino.secao === 'gestao') {
    // A Gestão do Curso É a entrada do curso: um degrau só, o atual.
    return [{ rotulo: nome }];
  }

  return [
    { rotulo: nome, secao: 'gestao' },
    { rotulo: NOME_DA_SECAO[destino.secao] },
  ];
}
