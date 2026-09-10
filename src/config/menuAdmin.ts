/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Navegação do painel administrativo, agrupada.
 *
 * Eram dez itens numa lista plana sob um rótulo único, "Gestão", misturando
 * pessoas, conteúdo pedagógico e administração de sistema. O efeito prático é
 * que "Documentos" e "Templates de Documentos" ficavam a quatro linhas de
 * distância, **não são a mesma coisa**, e nada na tela dizia isso.
 *
 * Nada foi removido e nenhum `id` mudou: os grupos são uma camada de leitura
 * sobre a mesma lista, e a aba continua sendo a mesma aba.
 *
 * Vive em módulo próprio, e não no JSX, porque o que importa aqui é *decisão*:
 * qual item pertence a qual grupo, qual desaparece com qual flag, e o que cada
 * seção diz de si. Num componente de 4.900 linhas essas três coisas se perdem.
 */

/** Um grupo de itens da barra lateral. */
export interface GrupoDoAdmin {
  /** Rótulo do grupo. Vazio = itens sem cabeçalho, no topo. */
  titulo: string;
  itens: ItemDoAdmin[];
}

/** Um item da navegação administrativa. */
export interface ItemDoAdmin {
  /** O mesmo `activeTab` de sempre — renomear quebraria a navegação. */
  id: string;
  rotulo: string;
  /**
   * Subtítulo da seção, mostrado no cabeçalho quando ela está aberta.
   *
   * Antes havia UM subtítulo — "Gestão global de professores, alunos, turmas e
   * cursos" — repetido nas dez telas, inclusive em Configurações e em Páginas
   * do Site, onde ele não descrevia nada do que estava na tela.
   */
  subtitulo: string;
  /** Nome da flag de produto que controla o item. Ausente = sempre visível. */
  flag?: string;
}

/**
 * Os grupos, na ordem em que aparecem.
 *
 * O primeiro não tem título: um cabeçalho de grupo para um item só é ruído, e
 * "Dashboard & Relatórios" é a entrada do painel.
 */
export const GRUPOS_DO_ADMIN: GrupoDoAdmin[] = [
  {
    titulo: '',
    itens: [
      {
        id: 'analytics',
        rotulo: 'Dashboard & Relatórios',
        subtitulo: 'Indicadores da rede e relatórios de acompanhamento.',
      },
    ],
  },
  {
    titulo: 'Pessoas',
    itens: [
      {
        id: 'professors',
        rotulo: 'Equipe Pedagógica',
        subtitulo: 'Cadastro, vínculo e permissões de professores e gestores.',
      },
      {
        id: 'students',
        rotulo: 'Alunos',
        subtitulo: 'Cadastro, matrícula e situação acadêmica dos alunos.',
      },
    ],
  },
  {
    titulo: 'Ensino',
    itens: [
      {
        id: 'courses',
        rotulo: 'Cursos & Trilhas',
        subtitulo: 'Cursos, aulas e material didático da grade.',
        flag: 'catalogoCursos',
      },
      {
        id: 'exercicios',
        rotulo: 'Exercícios Práticos',
        subtitulo: 'Exercícios propostos e acompanhamento das correções.',
        flag: 'atividadesPraticasAvancadas',
      },
      {
        id: 'requests',
        // Era "Documentos", a quatro linhas de "Templates de Documentos".
        rotulo: 'Solicitações Acadêmicas',
        subtitulo: 'Requerimentos enviados pelos alunos à secretaria.',
        flag: 'solicitacoesAcademicas',
      },
    ],
  },
  {
    titulo: 'Sistema',
    itens: [
      {
        id: 'export_bi',
        rotulo: 'Dados Gerenciais',
        subtitulo: 'Exportação de bases para uso gerencial e BI.',
        flag: 'dadosGerenciais',
      },
      {
        id: 'templates',
        /*
         * Era "Templates de Documentos". O handoff sugeriu "Modelos de
         * certificado e declaração", mas os tipos que o backend aceita são
         * `certificado` e `historico` (`DocumentTemplateService::TYPES`) —
         * declaração não existe. O nome diz o que a tela edita de fato.
         */
        rotulo: 'Modelos de Certificado e Histórico',
        subtitulo: 'Cabeçalho, assinaturas e rodapé dos documentos emitidos.',
      },
      {
        id: 'site_content',
        rotulo: 'Páginas do Site',
        subtitulo: 'Textos e imagens das páginas públicas do portal.',
        flag: 'gestaoConteudoSite',
      },
      {
        id: 'settings',
        rotulo: 'Configurações',
        subtitulo: 'Parâmetros letivos, incluindo a exigência de presença para o certificado.',
        flag: 'perfilBasico',
      },
    ],
  },
];

/** O que este módulo precisa saber das flags de produto. */
export type FlagsDoAdmin = Record<string, boolean | undefined>;

/**
 * Grupos com os itens que a configuração atual deixa visíveis.
 *
 * Grupo que ficou sem item nenhum não é devolvido: um cabeçalho "Ensino" com
 * nada embaixo parece falha de carregamento.
 */
export function gruposVisiveisDoAdmin(features: FlagsDoAdmin | null | undefined): GrupoDoAdmin[] {
  const f = features ?? {};

  return GRUPOS_DO_ADMIN
    .map((grupo) => ({
      titulo: grupo.titulo,
      itens: grupo.itens.filter((item) => item.flag === undefined || f[item.flag] === true),
    }))
    .filter((grupo) => grupo.itens.length > 0);
}

/** Todos os itens visíveis, na ordem da barra — para o `<select>` e atalhos. */
export function itensVisiveisDoAdmin(features: FlagsDoAdmin | null | undefined): ItemDoAdmin[] {
  return gruposVisiveisDoAdmin(features).flatMap((grupo) => grupo.itens);
}

/** O item de uma aba, visível ou não (o cabeçalho precisa dele mesmo desligada). */
export function itemDoAdmin(id: string): ItemDoAdmin | undefined {
  return GRUPOS_DO_ADMIN.flatMap((grupo) => grupo.itens).find((item) => item.id === id);
}

/** Nome do grupo a que a aba pertence. Vazio para o item do topo. */
export function grupoDaAba(id: string): string | undefined {
  const grupo = GRUPOS_DO_ADMIN.find((g) => g.itens.some((item) => item.id === id));

  return grupo?.titulo;
}
