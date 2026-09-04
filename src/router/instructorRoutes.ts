/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Endereços do painel do instrutor.
 *
 * `/inst/curso/<courseId>/<seção>`, e a ordem é a regra: **o curso vem antes da
 * seção**. Não é estética de URL — é a regra de uso que o painel não tinha.
 * Antes, `selectedCourseId` nascia em `courses[0]?.id`, então quem abria o painel
 * já estava dentro de um curso que não escolheu, e as abas Grade Curricular,
 * Avaliações e Gestão de Alunos operavam sobre ele. Com o curso no caminho, seção
 * sem curso é endereço que não existe.
 *
 * O identificador é o **id do curso**, não um slug do título. Duas razões:
 * categoria não serve (dois cursos dividem "Design Digital", então
 * `/inst/design-digital` não diria qual), e o título é editável — slug de título
 * quebraria todo link salvo no dia em que a coordenação corrigisse uma palavra.
 * O custo é a URL não dizer de que curso se trata.
 *
 * Puro e sem React de propósito: é a parte que erra em silêncio. Caminho escrito
 * de duas formas em dois lugares dá tela em branco, e isto se testa.
 */

import { DashboardTab } from '../context/LMSContext';

/** Raiz do painel: onde se escolhe o curso. */
export const RAIZ_INSTRUTOR = '/inst';

/** Seções de um curso, como aparecem no endereço. */
export type SecaoInstrutor = 'gestao' | 'grade-curricular' | 'avaliacoes' | 'alunos' | 'mensagens';

/** Sub-abas de Avaliações. */
export type SubAbaAvaliacoes = 'provas' | 'exercicios';

/**
 * Seção ↔ aba interna do painel.
 *
 * `gestao` é a seção de entrada e NÃO aparece no endereço: o curso sozinho
 * (`/inst/curso/course-1`) já é a Gestão do Curso. Acrescentar `/gestao` daria
 * dois endereços para a mesma tela.
 */
const SECAO_PARA_ABA: Record<SecaoInstrutor, DashboardTab> = {
  gestao: 'general',
  'grade-curricular': 'curriculum',
  avaliacoes: 'avaliacoes',
  alunos: 'students',
  mensagens: 'messages',
};

/** Nomes das seções que aparecem no endereço (todas menos a de entrada). */
const SECOES_NO_CAMINHO = Object.keys(SECAO_PARA_ABA)
  .filter((s): s is SecaoInstrutor => s !== 'gestao');

export const abaDaSecao = (secao: SecaoInstrutor): DashboardTab => SECAO_PARA_ABA[secao];

/** Aba interna → seção. Usado por quem ainda chama `setActiveDashboardTab`. */
export function secaoDaAba(aba: DashboardTab): SecaoInstrutor {
  for (const [secao, mapeada] of Object.entries(SECAO_PARA_ABA) as [SecaoInstrutor, DashboardTab][]) {
    if (mapeada === aba) return secao;
  }

  // Aba que o painel do instrutor não expõe (certificados, biblioteca…) cai na
  // entrada: melhor a Gestão do Curso que um endereço inventado.
  return 'gestao';
}

/** O que um endereço do painel do instrutor diz. */
export interface DestinoInstrutor {
  /** Curso escolhido, ou null quando ainda não houve escolha. */
  courseId: string | null;
  secao: SecaoInstrutor;
  /** Só em Avaliações; `provas` é o padrão. */
  subAba: SubAbaAvaliacoes;
  /** Janela aberta sobre a tela, quando houver (vem de `?janela=`). */
  janela: string | null;
}

/**
 * Lê um endereço do painel.
 *
 * Devolve sempre um destino utilizável: caminho estranho abaixo de `/inst` cai
 * em "sem curso", que é a tela de escolha — não uma tela em branco.
 */
export function parseInstrutor(pathname: string, search = ''): DestinoInstrutor {
  const partes = pathname.replace(/\/+$/, '').split('/').filter((p) => p !== '');
  const janela = new URLSearchParams(search).get('janela');
  const vazio: DestinoInstrutor = { courseId: null, secao: 'gestao', subAba: 'provas', janela };

  // ['inst'] | ['inst','curso',<id>] | ['inst','curso',<id>,<secao>] | (+ subaba)
  if (partes[0] !== 'inst' || partes[1] !== 'curso' || typeof partes[2] !== 'string' || partes[2] === '') {
    return vazio;
  }

  const courseId = decodeURIComponent(partes[2]);
  const nomeSecao = partes[3];
  if (nomeSecao === undefined) {
    return { ...vazio, courseId };
  }
  if (!SECOES_NO_CAMINHO.includes(nomeSecao as SecaoInstrutor)) {
    // Seção desconhecida vira a entrada do curso, e não a tela de escolha: o
    // curso do endereço é informação boa mesmo com o resto errado.
    return { ...vazio, courseId };
  }

  const secao = nomeSecao as SecaoInstrutor;
  const subAba: SubAbaAvaliacoes = secao === 'avaliacoes' && partes[4] === 'exercicios' ? 'exercicios' : 'provas';

  return { courseId, secao, subAba, janela };
}

/** Monta o endereço de um destino. */
export function caminhoInstrutor(destino: {
  courseId?: string | null;
  secao?: SecaoInstrutor;
  subAba?: SubAbaAvaliacoes;
  janela?: string | null;
}): string {
  const { courseId, secao = 'gestao', subAba = 'provas', janela = null } = destino;
  const comJanela = (base: string): string =>
    janela !== null && janela !== '' ? `${base}?janela=${encodeURIComponent(janela)}` : base;

  if (courseId === null || courseId === undefined || courseId === '') {
    // A janela sobrevive à ausência de curso: "Cadastrar Novo Curso" abre na
    // tela de escolha, quando ainda não há curso nenhum para escolher.
    return comJanela(RAIZ_INSTRUTOR);
  }

  let caminho = `${RAIZ_INSTRUTOR}/curso/${encodeURIComponent(courseId)}`;
  if (secao !== 'gestao') caminho += `/${secao}`;
  // A sub-aba padrão fica FORA do endereço: `/avaliacoes` e
  // `/avaliacoes/provas` seriam a mesma tela com dois endereços.
  if (secao === 'avaliacoes' && subAba !== 'provas') caminho += `/${subAba}`;

  return comJanela(caminho);
}
