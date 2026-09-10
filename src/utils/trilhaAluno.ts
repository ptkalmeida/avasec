/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Trilha de navegação do painel do aluno.
 *
 * A hierarquia já existia — estava escrita no `handleBack`, que desfazia um
 * nível por clique: exercícios → avaliações → aula → curso → aba → portal. O
 * problema é que ela só existia como *comportamento de um botão*: a pessoa podia
 * voltar um degrau, mas em nenhum momento via os degraus.
 *
 * Pior, a única pista de localização que havia — o cartão de saudação — **sumia
 * justamente quando um curso estava aberto**, que é quando a hierarquia fica
 * profunda e saber onde se está passa a importar.
 *
 * Aqui a mesma hierarquia vira dado: cada nível é um degrau clicável, e a barra
 * fica fixa em todos eles.
 *
 * Puro e derivado do endereço de propósito. Trilha calculada a partir de estado
 * solto desincroniza do que a tela mostra, e o sintoma é a pessoa clicar num
 * degrau e ir para um lugar que não é o que o degrau dizia.
 */

import { DashboardTab } from '../context/LMSContext';
import { DestinoAluno, TelaAluno } from '../router/studentRoutes';

/** Um degrau da trilha do aluno. `destino` ausente = degrau atual. */
export interface DegrauAluno {
  rotulo: string;
  /** Para onde o degrau leva. Ausente no degrau atual. */
  destino?: { tela: TelaAluno; aba?: DashboardTab };
}

/**
 * Nome de cada seção do painel na trilha.
 *
 * `general` não aparece: é a entrada, e o primeiro degrau ("Painel de Estudos")
 * já é ela. Repetir daria "Painel de Estudos › Painel de Estudos".
 */
const NOME_DA_ABA: Partial<Record<DashboardTab, string>> = {
  certificates: 'Certificados',
  documents: 'Documentos',
  messages: 'Mensagens & Suporte',
  library: 'Biblioteca Digital',
  events: 'Eventos & Webinars',
  settings: 'Meu Perfil',
  faq: 'Central de Ajuda',
};

/** Títulos que a trilha precisa e que só a tela conhece. */
export interface TitulosDaTrilha {
  /** Título do curso aberto. */
  curso?: string | null;
  /** Título da aula aberta. */
  aula?: string | null;
}

/**
 * Degraus DEPOIS do primeiro — o componente insere "Painel de Estudos".
 *
 * Lista vazia na entrada do painel: sem degrau não se desenha a barra, e uma
 * barra com "Painel de Estudos" sozinho não informa nada.
 */
export function trilhaDoAluno(destino: DestinoAluno, titulos: TitulosDaTrilha = {}): DegrauAluno[] {
  // Vitrine do catálogo: não pertence à hierarquia de um curso do aluno.
  if (destino.tela === 'catalogo') {
    return [{ rotulo: 'Catálogo de cursos' }];
  }

  if (destino.tela === 'painel') {
    const nome = NOME_DA_ABA[destino.aba];

    return nome === undefined ? [] : [{ rotulo: nome }];
  }

  /*
   * Dentro de um curso. O título vem da tela porque o endereço traz o slug, e
   * mostrar o slug na trilha (`ux-ui-design-interfaces...`) seria trocar um
   * identificador por outro — o ponto da trilha é dizer o nome.
   *
   * Sem título (catálogo ainda carregando) o degrau diz "Curso": some quando os
   * dados chegam, e é melhor que um degrau vazio ou o slug cru.
   */
  const tituloCurso = (titulos.curso ?? '').trim() === '' ? 'Curso' : (titulos.curso as string);
  const degraus: DegrauAluno[] = [{ rotulo: tituloCurso, destino: { tela: 'curso' } }];

  switch (destino.tela) {
    case 'curso':
      // O curso É o degrau atual: perde o link.
      return [{ rotulo: tituloCurso }];

    case 'aula': {
      const tituloAula = (titulos.aula ?? '').trim() === '' ? 'Aula' : (titulos.aula as string);
      degraus.push({ rotulo: tituloAula });
      break;
    }

    case 'avaliacoes':
      // Com prova em andamento, "Avaliações" volta a ser clicável e a prova é o
      // degrau atual — é o mesmo degrau que o `handleBack` desfazia primeiro.
      if (destino.quizId !== null) {
        degraus.push({ rotulo: 'Avaliações', destino: { tela: 'avaliacoes' } });
        degraus.push({ rotulo: 'Prova em andamento' });
      } else {
        degraus.push({ rotulo: 'Avaliações' });
      }
      break;

    case 'exercicios':
      degraus.push({ rotulo: 'Exercícios' });
      break;

    case 'ao-vivo':
      degraus.push({ rotulo: 'Encontro ao vivo' });
      break;

    default:
      break;
  }

  return degraus;
}
