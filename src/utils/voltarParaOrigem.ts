/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Para onde um "Voltar" leva quando a tela abre de mais de um lugar.
 *
 * O Perfil e as abas do painel do aluno (Certificados, Biblioteca, Documentos,
 * Mensagens, Eventos, Dúvidas, Configurações) abrem de vários pontos — a barra
 * de navegação dentro de um curso, o cabeçalho, o atalho do painel. O "Voltar"
 * delas ia SEMPRE para um lugar fixo: a raiz do papel ou a aba "Painel". Um
 * aluno que clicava em Certificados no meio de uma aula voltava para a escolha
 * de curso e tinha de achar o curso e a aula de novo.
 *
 * Agora quem abre a tela guarda o endereço de onde ela foi aberta, e o Voltar
 * leva para lá. Sem origem (F5, link direto) vale o destino fixo de antes — o
 * `history.back()` foi evitado de propósito, porque numa aba do navegador
 * aberta direto na tela ele tira a pessoa do sistema.
 *
 * Puro e sem React, para testar sem navegador.
 */

import { DashboardTab } from '../context/LMSContext';
import { caminhoBateComPapel, ehCaminhoAutenticado, viewFromPath } from '../router/portalRoutes';
import { parseAluno } from '../router/studentRoutes';

type Papel = 'student' | 'instructor' | 'admin';

/**
 * Endereço a guardar como origem do Perfil, ou null quando não há o que guardar.
 *
 * O próprio Perfil não é origem: clicar em "Perfil" estando no Perfil não pode
 * apagar o caminho de volta.
 */
export function origemDoPerfil(pathname: string, search = ''): string | null {
  const view = viewFromPath(pathname);
  if (view === null || view === 'perfil') return null;

  return `${pathname}${search}`;
}

/**
 * Destino do Voltar. `raiz` é o destino fixo de antes, usado quando não há
 * origem ou quando ela deixou de valer.
 *
 * Origem autenticada de OUTRO papel não vale: depois de uma troca de perfil
 * (atalho de dev) ou de um novo login, voltar para o painel antigo só
 * produziria um redirecionamento com a tela errada piscando no meio.
 */
export function destinoDoVoltar(origem: string | null, papel: Papel | null, raiz: string): string {
  if (origem === null) return raiz;

  const pathname = origem.split('?')[0];
  if (ehCaminhoAutenticado(pathname) && (papel === null || !caminhoBateComPapel(pathname, papel))) {
    return raiz;
  }

  return origem;
}

/** Nome de cada aba do painel do aluno no botão de Voltar. */
const VOLTAR_PARA_ABA: Partial<Record<DashboardTab, string>> = {
  certificates: 'Voltar aos Certificados',
  documents: 'Voltar aos Documentos',
  library: 'Voltar à Biblioteca',
  messages: 'Voltar às Mensagens',
  events: 'Voltar aos Eventos',
  faq: 'Voltar às Dúvidas',
  settings: 'Voltar às Configurações',
};

/**
 * Rótulo do botão, dizendo para onde ele leva.
 *
 * `rotuloDoPainel` é o texto da entrada do painel (e de quando não há origem):
 * o Perfil diz "Voltar ao Painel"; as abas do painel do aluno mantêm o
 * "Voltar ao Meu Painel de Estudos" que já usavam.
 */
export function rotuloDoVoltar(origem: string | null, rotuloDoPainel = 'Voltar ao Painel'): string {
  if (origem === null) return rotuloDoPainel;

  const [pathname, search = ''] = origem.split('?');
  if (!ehCaminhoAutenticado(pathname)) return 'Voltar';

  const destino = parseAluno(pathname, search);
  switch (destino.tela) {
    case 'aula':
      return 'Voltar à aula';
    case 'curso':
    case 'avaliacoes':
    case 'exercicios':
    case 'ao-vivo':
      return 'Voltar ao curso';
    case 'catalogo':
      return 'Voltar ao catálogo';
    default:
      return VOLTAR_PARA_ABA[destino.aba] ?? rotuloDoPainel;
  }
}
