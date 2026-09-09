/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Endereços do painel do aluno.
 *
 * O painel tinha seis estados de navegação guardados em `useState` — curso
 * escolhido, aula aberta, avaliações, prova em andamento, exercícios e sala ao
 * vivo — e **nenhum deles aparecia no endereço**: `/app` era a URL da prova em
 * andamento, da aula com vídeo e da lista de cursos, todas iguais. O efeito
 * prático é que nada disso era linkável e um F5 no meio da prova voltava para a
 * lista de cursos.
 *
 * Aqui o endereço passa a ser a fonte da verdade. Tudo o que a tela mostra tem
 * de caber neste tipo — se não cabe, é estado que o F5 perde, e isso agora é
 * visível em vez de silencioso.
 *
 * `curso` e `catalogo` são segmentos reservados: nenhuma seção do painel pode se
 * chamar assim, senão `/aluno/curso` seria ambíguo. O teste de colisão prende
 * isso.
 *
 * Puro e sem React de propósito — caminho montado de um jeito e lido de outro dá
 * tela em branco, e isto se testa sem navegador.
 */

import { DashboardTab } from '../context/LMSContext';

/** Raiz do painel do aluno. */
export const RAIZ_ALUNO = '/aluno';

/** Segmentos que não podem ser nome de seção. */
export const SEGMENTOS_RESERVADOS = ['curso', 'catalogo'] as const;

/**
 * Seção do painel ↔ aba interna.
 *
 * `general` é a entrada e NÃO aparece no endereço: `/aluno` já é o Ambiente de
 * Estudos. Acrescentar `/geral` daria dois endereços para a mesma tela.
 */
const SECAO_PARA_ABA: Record<string, DashboardTab> = {
  certificados: 'certificates',
  solicitacoes: 'documents',
  mensagens: 'messages',
  biblioteca: 'library',
  eventos: 'events',
  perfil: 'settings',
  duvidas: 'faq',
};

/** Nome da seção no endereço, a partir da aba. Vazio para a aba de entrada. */
export function secaoDaAbaAluno(aba: DashboardTab): string {
  for (const [secao, mapeada] of Object.entries(SECAO_PARA_ABA)) {
    if (mapeada === aba) return secao;
  }

  // Aba que o painel do aluno não expõe (curriculum, students…) cai na entrada:
  // melhor o Ambiente de Estudos que um endereço inventado.
  return '';
}

export const abaDaSecaoAluno = (secao: string): DashboardTab => SECAO_PARA_ABA[secao] ?? 'general';

/** Telas do painel. `painel` é a lista/aba; as outras vivem dentro de um curso. */
export type TelaAluno =
  | 'painel'
  | 'catalogo'
  | 'curso'
  | 'aula'
  | 'avaliacoes'
  | 'exercicios'
  | 'ao-vivo';

/** O que um endereço do painel do aluno diz. */
export interface DestinoAluno {
  tela: TelaAluno;
  /** Aba do painel. Só significa algo quando `tela` é `painel`. */
  aba: DashboardTab;
  /**
   * Curso aberto, como veio no endereço (telas `curso`, `aula`, `avaliacoes`,
   * `exercicios`, `ao-vivo`).
   *
   * É o SLUG do curso, não o id (ADR 13) — `ux-ui-design-...`, não `course-1`.
   * Chamava-se `courseId` e o nome passou a mentir quando o endereço deixou de
   * carregar id. Ainda aceita um id, porque link antigo tem de continuar
   * abrindo; quem transforma isto em curso é `cursoPorRef`, em utils/cursoRef.
   */
  cursoRef: string | null;
  /** Aula aberta, na tela `aula`. */
  lessonId: string | null;
  /** Prova em andamento, na tela `avaliacoes`. Null = lista de avaliações. */
  quizId: string | null;
  /** Encontro ao vivo, na tela `ao-vivo`. */
  sessionId: string | null;
  /** Curso da vitrine, na tela `catalogo`. */
  catalogoId: string | null;
  /** Módulo aberto dentro do curso (vem de `?modulo=`; é nome, não id). */
  modulo: string | null;
  /** Janela aberta sobre a tela (vem de `?janela=`). */
  janela: string | null;
}

const VAZIO: DestinoAluno = {
  tela: 'painel',
  aba: 'general',
  cursoRef: null,
  lessonId: null,
  quizId: null,
  sessionId: null,
  catalogoId: null,
  modulo: null,
  janela: null,
};

/** Lê um endereço do painel do aluno. Devolve sempre um destino utilizável. */
export function parseAluno(pathname: string, search = ''): DestinoAluno {
  const partes = pathname.replace(/\/+$/, '').split('/').filter((p) => p !== '');
  const q = new URLSearchParams(search);
  const base: DestinoAluno = { ...VAZIO, modulo: q.get('modulo'), janela: q.get('janela') };

  if (partes[0] !== 'aluno') return base;

  const primeiro = partes[1];
  if (primeiro === undefined) return base;

  if (primeiro === 'catalogo') {
    const id = segmento(partes[2]);
    // `/aluno/catalogo` sem curso não é tela: cai no Ambiente de Estudos.
    return id === null ? base : { ...base, tela: 'catalogo', catalogoId: id };
  }

  if (primeiro === 'curso') {
    const cursoRef = segmento(partes[2]);
    // Não há como pedir "a aula" sem dizer de qual curso.
    if (cursoRef === null) return base;

    const dentro = { ...base, cursoRef };
    switch (partes[3]) {
      case undefined:
        return { ...dentro, tela: 'curso' };
      case 'aula': {
        const lessonId = segmento(partes[4]);
        // Aula sem id é o curso aberto, não uma aula em branco.
        return lessonId === null ? { ...dentro, tela: 'curso' } : { ...dentro, tela: 'aula', lessonId };
      }
      case 'avaliacoes':
        // Sem quiz é a lista; com quiz é a prova em andamento.
        return { ...dentro, tela: 'avaliacoes', quizId: segmento(partes[4]) };
      case 'exercicios':
        return { ...dentro, tela: 'exercicios' };
      case 'ao-vivo': {
        const sessionId = segmento(partes[4]);
        return sessionId === null ? { ...dentro, tela: 'curso' } : { ...dentro, tela: 'ao-vivo', sessionId };
      }
      default:
        // Tela desconhecida vira o curso aberto, e não a lista: o curso do
        // endereço é informação boa mesmo com o resto errado.
        return { ...dentro, tela: 'curso' };
    }
  }

  // Seção do painel. Nome desconhecido cai na entrada.
  return { ...base, aba: abaDaSecaoAluno(primeiro) };
}

/** Um segmento de caminho, decodificado; null quando ausente ou vazio. */
function segmento(parte: string | undefined): string | null {
  if (typeof parte !== 'string' || parte === '') return null;

  return decodeURIComponent(parte);
}

/** Monta o endereço de um destino. */
export function caminhoAluno(destino: {
  tela?: TelaAluno;
  aba?: DashboardTab;
  cursoRef?: string | null;
  lessonId?: string | null;
  quizId?: string | null;
  sessionId?: string | null;
  catalogoId?: string | null;
  modulo?: string | null;
  janela?: string | null;
}): string {
  const {
    tela = 'painel', aba = 'general', cursoRef = null, lessonId = null,
    quizId = null, sessionId = null, catalogoId = null, modulo = null, janela = null,
  } = destino;

  let caminho = RAIZ_ALUNO;

  if (tela === 'catalogo' && catalogoId !== null && catalogoId !== '') {
    caminho += `/catalogo/${encodeURIComponent(catalogoId)}`;
  } else if (tela !== 'painel' && tela !== 'catalogo' && cursoRef !== null && cursoRef !== '') {
    // Sem curso, qualquer tela de dentro do curso é a raiz do painel: é a mesma
    // regra do painel do instrutor — seção sem curso é endereço que não existe.
    caminho += `/curso/${encodeURIComponent(cursoRef)}`;
    if (tela === 'aula' && lessonId !== null && lessonId !== '') {
      caminho += `/aula/${encodeURIComponent(lessonId)}`;
    } else if (tela === 'avaliacoes') {
      caminho += '/avaliacoes';
      if (quizId !== null && quizId !== '') caminho += `/${encodeURIComponent(quizId)}`;
    } else if (tela === 'exercicios') {
      caminho += '/exercicios';
    } else if (tela === 'ao-vivo' && sessionId !== null && sessionId !== '') {
      caminho += `/ao-vivo/${encodeURIComponent(sessionId)}`;
    }
  } else if (tela === 'painel') {
    const secao = secaoDaAbaAluno(aba);
    if (secao !== '') caminho += `/${secao}`;
  }

  const q = new URLSearchParams();
  // `modulo` só faz sentido dentro de um curso; fora dele sujaria o endereço.
  if (modulo !== null && modulo !== '' && caminho.includes('/curso/')) q.set('modulo', modulo);
  if (janela !== null && janela !== '') q.set('janela', janela);
  const busca = q.toString();

  return busca === '' ? caminho : `${caminho}?${busca}`;
}
