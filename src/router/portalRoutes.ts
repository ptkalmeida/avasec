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
 * Rótulo e pai de cada tela, para a trilha de navegação (breadcrumb).
 *
 * Vive aqui, e não no componente, porque é a MESMA pergunta que o mapa de
 * caminhos responde — "que tela é esta?" — só com outra resposta. Espalhar o
 * rótulo pelos chamadores foi como o portal chegou a cinco desenhos diferentes
 * do botão "Voltar", cada um com seu texto.
 *
 * `PORTAL_PATHS` fica intacto, e `pathFromView`/`viewFromPath` também: este é um
 * mapa NOVO, ao lado. Trocar a forma do primeiro obrigaria a mexer nos 12 pontos
 * que já o consomem, sem ganho nenhum.
 *
 * `pai: null` significa que a tela pende direto de Início. A área autenticada e
 * o perfil ficam fora: os painéis têm trilha própria, derivada da hierarquia de
 * cada um, e o perfil é alcançado de qualquer lugar — dar-lhe um pai fixo faria
 * a trilha afirmar um caminho que a pessoa não percorreu.
 */
interface TelaNaTrilha {
  rotulo: string;
  pai: PortalView | null;
}

const TRILHA_DO_PORTAL: Partial<Record<PortalView, TelaNaTrilha>> = {
  cursos: { rotulo: 'Cursos', pai: null },
  certificados: { rotulo: 'Certificados', pai: null },
  // Os agrupamentos "A Escola" e "Ajuda" do menu NÃO entram como pai: são rótulos
  // de menu, não telas — não há `/a-escola` para clicar. Uma trilha com item
  // morto no meio é pior que uma trilha curta.
  'o-ava': { rotulo: 'O que é o AVA', pai: null },
  'o-projeto': { rotulo: 'O Projeto', pai: null },
  noticias: { rotulo: 'Notícias', pai: null },
  duvidas: { rotulo: 'Dúvidas frequentes', pai: null },
  orientacoes: { rotulo: 'Orientações ao estudante', pai: null },
  calendario: { rotulo: 'Calendário', pai: null },
};

/** Um degrau da trilha. `view` ausente = degrau atual, não clicável. */
export interface DegrauDaTrilha {
  rotulo: string;
  view?: PortalView;
}

/**
 * Trilha de uma tela do portal, SEM o "Início" — quem o insere é o componente.
 *
 * Devolve lista vazia para a landing (não se mostra trilha na página inicial) e
 * para telas sem entrada no mapa. Vazio é o sinal de "não desenhe a barra", e é
 * melhor que uma barra com um item só dizendo "Início".
 */
export function trilhaDaView(view: PortalView): DegrauDaTrilha[] {
  const degraus: DegrauDaTrilha[] = [];

  let atual: PortalView | null = view;
  // Guarda contra pai ciclico: um `pai` mal escrito travaria a tela em laco
  // infinito, e o numero de telas do portal e conhecido.
  let limite = Object.keys(PORTAL_PATHS).length + 1;

  while (atual !== null && limite-- > 0) {
    const entrada: TelaNaTrilha | undefined = TRILHA_DO_PORTAL[atual];
    if (entrada === undefined) break;

    // O primeiro (a tela pedida) entra sem `view`: é o degrau atual.
    degraus.unshift(atual === view ? { rotulo: entrada.rotulo } : { rotulo: entrada.rotulo, view: atual });
    atual = entrada.pai;
  }

  return degraus;
}

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
