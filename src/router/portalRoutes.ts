/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Mapa entre as telas do portal e os endereços do navegador.
 *
 * Até aqui a navegação era só estado (`currentView` no App.tsx): a URL ficava
 * sempre em `/`, então recarregar a página jogava a pessoa na landing, não havia
 * como mandar o link de uma tela para alguém, favoritar não servia para nada e o
 * botão Voltar do navegador saía do sistema inteiro.
 *
 * Este arquivo é a fonte única da tradução, nos dois sentidos, e fica separado
 * do componente porque é a parte que erra em silêncio: um caminho escrito de
 * duas formas diferentes em dois lugares dá tela em branco, e isso se testa.
 *
 * NÃO reescrevemos a árvore de render do App.tsx. `currentView` continua
 * existindo e continua governando o que aparece — passa apenas a ser DERIVADO
 * da URL em vez de guardado em `useState`. A cadeia de ternários de ~600 linhas
 * que renderiza as telas segue intacta, e com ela os 12 pontos que chamam
 * `setCurrentView`.
 */

/** As telas do portal. Espelha o `PortalView` do App.tsx. */
export type PortalView =
  | 'landing'
  | 'active_app'
  | 'perfil'
  | 'cursos'
  | 'o-ava'
  | 'certificados'
  | 'o-projeto'
  | 'noticias'
  | 'duvidas'
  | 'calendario'
  | 'orientacoes';

/**
 * Endereço de cada tela.
 *
 * Os nomes saem do próprio menu do portal ("O AVA" → `/o-ava`), para que o
 * endereço diga a mesma coisa que o item clicado — quem manda o link consegue
 * dizer o que vai abrir sem precisar abrir.
 *
 * `active_app` é a área autenticada: por ora um caminho só, que as fases
 * seguintes desdobram em `/inst/...`, `/aluno/...` e `/admin/...`.
 */
export const PORTAL_PATHS: Record<PortalView, string> = {
  landing: '/',
  'o-ava': '/o-ava',
  'o-projeto': '/o-projeto',
  cursos: '/cursos',
  certificados: '/certificados',
  calendario: '/calendario',
  noticias: '/noticias',
  duvidas: '/duvidas',
  orientacoes: '/orientacoes',
  perfil: '/perfil',
  active_app: '/app',
};

/** Caminho de uma tela. */
export const pathFromView = (view: PortalView): string => PORTAL_PATHS[view];

/**
 * Tela de um caminho, ou null quando o caminho não é de nenhuma.
 *
 * Devolver null em vez de cair na landing é deliberado: quem decide o que fazer
 * com endereço desconhecido é quem chama (hoje, redirecionar para a raiz).
 * Fingir que `/qualquer-coisa` é a landing esconderia link quebrado — inclusive
 * link nosso, escrito errado.
 *
 * A área autenticada casa também com os caminhos ABAIXO dela (`/app/...`,
 * `/inst/...`), porque as fases seguintes penduram as seções dos painéis ali e
 * elas não podem ser lidas como endereço inválido.
 */
export function viewFromPath(pathname: string): PortalView | null {
  const limpo = normalizarCaminho(pathname);

  for (const [view, caminho] of Object.entries(PORTAL_PATHS) as [PortalView, string][]) {
    if (caminho === limpo) return view;
  }

  if (CAMINHOS_AUTENTICADOS.some((raiz) => limpo === raiz || limpo.startsWith(`${raiz}/`))) {
    return 'active_app';
  }

  return null;
}

/** Raízes que pertencem à área autenticada, com o que vier abaixo delas. */
const CAMINHOS_AUTENTICADOS: readonly string[] = ['/app', '/inst', '/aluno', '/admin'];

/** O caminho está dentro da área autenticada? */
export function ehCaminhoAutenticado(pathname: string): boolean {
  const limpo = normalizarCaminho(pathname);

  // Comparação por segmento, nunca por prefixo cru: `/institucional` começa com
  // as letras de `/inst` e é página pública.
  return CAMINHOS_AUTENTICADOS.some((raiz) => limpo === raiz || limpo.startsWith(`${raiz}/`));
}

/**
 * Raiz da área autenticada de cada papel.
 *
 * Quem escolhe o painel é o papel, não o caminho — o `App` despacha
 * `StudentDashboard`/`InstructorDashboard`/`AdminDashboard` por `activeUser.role`.
 * Sem esta raiz canônica, um aluno em `/admin` veria o painel do aluno sob um
 * endereço dizendo "admin": nada vazaria, mas o endereço mentiria, e endereço é
 * exatamente o que a pessoa manda para outra.
 */
export function raizDoPapel(papel: 'student' | 'instructor' | 'admin'): string {
  switch (papel) {
    case 'instructor':
      return '/inst';
    case 'admin':
      return '/admin';
    default:
      return '/aluno';
  }
}

/** O caminho é a área autenticada CORRETA para este papel? */
export function caminhoBateComPapel(
  pathname: string,
  papel: 'student' | 'instructor' | 'admin'
): boolean {
  const raiz = raizDoPapel(papel);
  const limpo = normalizarCaminho(pathname);

  return limpo === raiz || limpo.startsWith(`${raiz}/`);
}

/**
 * Normaliza para comparar: sem barra final e em minúsculas.
 *
 * `/cursos/` e `/Cursos` são a mesma tela para quem digitou, e tratá-los como
 * endereço inválido seria pedantismo que só produz tela em branco.
 */
export function normalizarCaminho(pathname: string): string {
  const semBarraFinal = pathname.replace(/\/+$/, '');

  return (semBarraFinal === '' ? '/' : semBarraFinal).toLowerCase();
}

/** O caminho corresponde a alguma tela conhecida? */
export const caminhoConhecido = (pathname: string): boolean => viewFromPath(pathname) !== null;
