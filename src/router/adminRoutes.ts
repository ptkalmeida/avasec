/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Endereços do painel administrativo.
 *
 * O painel tem dez seções na barra lateral, uma escada de sub-abas de relatório
 * e a ficha de um aluno — e tudo isso vivia em `useState`, sob o endereço `/app`.
 * Quem precisava mandar "olha a ficha deste aluno" mandava um print.
 *
 * Duas colisões de segmento moldaram o desenho, e os testes as prendem:
 *
 * 1. A sub-aba de relatório **Alunos** e a seção de lista **Alunos** são telas
 *    diferentes. `/admin/alunos` não pode significar as duas, então as sub-abas
 *    ficam sob o segmento reservado `relatorio`.
 * 2. `analytics` é a entrada e não aparece no endereço — `/admin` já é o
 *    Dashboard. Um `/admin/analytics` daria dois endereços para a mesma tela.
 *
 * Puro e sem React de propósito: é a parte que erra em silêncio.
 */

/** Raiz do painel administrativo. */
export const RAIZ_ADMIN = '/admin';

/** Seções da barra lateral, como o painel as nomeia internamente. */
export type AbaAdmin =
  | 'analytics' | 'professors' | 'courses' | 'students' | 'requests'
  | 'settings' | 'exercicios' | 'export_bi' | 'templates' | 'site_content';

/** Sub-abas de relatório, dentro do Dashboard. */
export type SubAbaRelatorio = 'consolidado' | 'alunos' | 'professores' | 'cursos' | 'inscricoes';

/** Segmento reservado: separa as sub-abas de relatório das seções. */
export const SEGMENTO_RELATORIO = 'relatorio';

/**
 * Seção ↔ nome no endereço. `analytics` é a entrada e fica fora do caminho.
 *
 * Os nomes seguem o rótulo da barra lateral, não o identificador interno em
 * inglês: quem lê a URL tem de reconhecer o menu que clicou.
 */
const ABA_PARA_SECAO: Record<Exclude<AbaAdmin, 'analytics'>, string> = {
  professors: 'equipe',
  students: 'alunos',
  courses: 'cursos',
  requests: 'documentos',
  exercicios: 'exercicios',
  export_bi: 'dados-gerenciais',
  templates: 'templates',
  site_content: 'paginas-do-site',
  settings: 'configuracoes',
};

const SUB_ABAS: readonly SubAbaRelatorio[] = ['consolidado', 'alunos', 'professores', 'cursos', 'inscricoes'];

export function secaoDaAbaAdmin(aba: AbaAdmin): string {
  return aba === 'analytics' ? '' : ABA_PARA_SECAO[aba];
}

export function abaDaSecaoAdmin(secao: string): AbaAdmin {
  for (const [aba, nome] of Object.entries(ABA_PARA_SECAO) as [Exclude<AbaAdmin, 'analytics'>, string][]) {
    if (nome === secao) return aba;
  }

  // Seção desconhecida cai no Dashboard, que é a entrada.
  return 'analytics';
}

/** O que um endereço do painel administrativo diz. */
export interface DestinoAdmin {
  aba: AbaAdmin;
  /** Sub-aba de relatório; só significa algo quando `aba` é `analytics`. */
  subAba: SubAbaRelatorio;
  /** Ficha de um aluno, dentro da seção Alunos. */
  alunoId: string | null;
  /** Janela aberta sobre a tela (vem de `?janela=`). */
  janela: string | null;
}

/** Lê um endereço do painel administrativo. */
export function parseAdmin(pathname: string, search = ''): DestinoAdmin {
  const partes = pathname.replace(/\/+$/, '').split('/').filter((p) => p !== '');
  const base: DestinoAdmin = {
    aba: 'analytics',
    subAba: 'consolidado',
    alunoId: null,
    janela: new URLSearchParams(search).get('janela'),
  };

  if (partes[0] !== 'admin') return base;

  const primeiro = partes[1];
  if (primeiro === undefined) return base;

  if (primeiro === SEGMENTO_RELATORIO) {
    const sub = partes[2];
    // `/admin/relatorio` sem sub-aba é o Dashboard, que já abre no consolidado.
    return SUB_ABAS.includes(sub as SubAbaRelatorio)
      ? { ...base, subAba: sub as SubAbaRelatorio }
      : base;
  }

  const aba = abaDaSecaoAdmin(primeiro);

  // A ficha do aluno só existe abaixo da seção Alunos. Um id pendurado em
  // outra seção seria estado sem tela para mostrar.
  if (aba === 'students' && typeof partes[2] === 'string' && partes[2] !== '') {
    return { ...base, aba, alunoId: decodeURIComponent(partes[2]) };
  }

  return { ...base, aba };
}

/** Monta o endereço de um destino. */
export function caminhoAdmin(destino: {
  aba?: AbaAdmin;
  subAba?: SubAbaRelatorio;
  alunoId?: string | null;
  janela?: string | null;
}): string {
  const { aba = 'analytics', subAba = 'consolidado', alunoId = null, janela = null } = destino;

  let caminho = RAIZ_ADMIN;

  if (aba === 'analytics') {
    // A sub-aba padrão fica FORA do endereço: `/admin` e
    // `/admin/relatorio/consolidado` seriam a mesma tela com dois endereços.
    if (subAba !== 'consolidado') caminho += `/${SEGMENTO_RELATORIO}/${subAba}`;
  } else {
    caminho += `/${secaoDaAbaAdmin(aba)}`;
    if (aba === 'students' && alunoId !== null && alunoId !== '') {
      caminho += `/${encodeURIComponent(alunoId)}`;
    }
  }

  return janela !== null && janela !== '' ? `${caminho}?janela=${encodeURIComponent(janela)}` : caminho;
}
