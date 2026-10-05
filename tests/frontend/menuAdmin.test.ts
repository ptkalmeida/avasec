import { describe, it, expect } from 'vitest';
import {
  GRUPOS_DO_ADMIN,
  grupoDaAba,
  gruposVisiveisDoAdmin,
  itemDoAdmin,
  itensVisiveisDoAdmin,
} from '../../src/config/menuAdmin';

/**
 * Todas as abas que existiam na lista plana, com a flag que cada uma tinha.
 *
 * Este é o contrato: agrupar é mudança de leitura, não de função. Se um `id`
 * sair daqui, alguém perdeu acesso a uma tela — e o teste cai antes de o
 * administrador descobrir.
 */
const COMO_ERA: { id: string; flag?: string }[] = [
  { id: 'analytics' },
  { id: 'professors' },
  { id: 'students' },
  { id: 'courses', flag: 'catalogoCursos' },
  { id: 'requests', flag: 'solicitacoesAcademicas' },
  { id: 'exercicios', flag: 'atividadesPraticasAvancadas' },
  { id: 'export_bi', flag: 'dadosGerenciais' },
  { id: 'templates' },
  { id: 'site_content', flag: 'gestaoConteudoSite' },
  { id: 'settings', flag: 'perfilBasico' },
];

/** Tudo ligado, para exercitar a lista inteira. */
const TUDO_LIGADO = Object.fromEntries(
  COMO_ERA.filter((i) => i.flag !== undefined).map((i) => [i.flag as string, true])
);

describe('agrupar não pode remover função', () => {
  it('nenhuma aba desapareceu', () => {
    const ids = itensVisiveisDoAdmin(TUDO_LIGADO).map((i) => i.id).sort();

    expect(ids).toEqual(COMO_ERA.map((i) => i.id).sort());
  });

  it('cada aba mantém exatamente a flag que tinha', () => {
    for (const { id, flag } of COMO_ERA) {
      expect(itemDoAdmin(id)?.flag, id).toBe(flag);
    }
  });

  it('desligar uma flag esconde só a aba dela', () => {
    const semCursos = itensVisiveisDoAdmin({ ...TUDO_LIGADO, catalogoCursos: false });

    expect(semCursos.map((i) => i.id)).not.toContain('courses');
    expect(semCursos.map((i) => i.id)).toContain('exercicios');
    expect(semCursos).toHaveLength(COMO_ERA.length - 1);
  });

  it('sem flag nenhuma, restam as abas que não dependem de flag', () => {
    // Era o comportamento do `.filter((t) => t.visible)` original.
    expect(itensVisiveisDoAdmin({}).map((i) => i.id)).toEqual([
      'analytics',
      'professors',
      'students',
      'templates',
    ]);
    expect(itensVisiveisDoAdmin(null).map((i) => i.id)).toHaveLength(4);
  });
});

describe('os grupos', () => {
  it('o item do topo não tem cabeçalho de grupo', () => {
    // Cabeçalho para um item só é ruído.
    expect(GRUPOS_DO_ADMIN[0].titulo).toBe('');
    expect(GRUPOS_DO_ADMIN[0].itens).toHaveLength(1);
    expect(grupoDaAba('analytics')).toBe('');
  });

  it('pessoas, ensino e sistema, nesta ordem', () => {
    expect(GRUPOS_DO_ADMIN.map((g) => g.titulo)).toEqual(['', 'Pessoas', 'Ensino', 'Sistema']);
  });

  it('cada aba está no grupo que descreve o trabalho dela', () => {
    expect(grupoDaAba('professors')).toBe('Pessoas');
    expect(grupoDaAba('students')).toBe('Pessoas');
    expect(grupoDaAba('courses')).toBe('Ensino');
    expect(grupoDaAba('requests')).toBe('Ensino');
    expect(grupoDaAba('templates')).toBe('Sistema');
    expect(grupoDaAba('settings')).toBe('Sistema');
  });

  it('grupo que ficou vazio não é desenhado', () => {
    /*
     * Um cabeçalho "Ensino" com nada embaixo parece falha de carregamento. Com
     * as três flags de Ensino desligadas, o grupo não existe.
     */
    const grupos = gruposVisiveisDoAdmin({
      ...TUDO_LIGADO,
      catalogoCursos: false,
      atividadesPraticasAvancadas: false,
      solicitacoesAcademicas: false,
    });

    expect(grupos.map((g) => g.titulo)).not.toContain('Ensino');
    expect(grupos.every((g) => g.itens.length > 0)).toBe(true);
  });
});

describe('os nomes', () => {
  it('"Documentos" e "Templates de Documentos" deixam de ser confundíveis', () => {
    /*
     * Ficavam a quatro linhas de distância e não são a mesma coisa: um é
     * requerimento de aluno, o outro é o modelo do documento emitido.
     */
    const rotulos = itensVisiveisDoAdmin(TUDO_LIGADO).map((i) => i.rotulo);

    expect(rotulos).not.toContain('Documentos');
    expect(rotulos).not.toContain('Templates de Documentos');
    expect(itemDoAdmin('requests')?.rotulo).toBe('Solicitações Acadêmicas');
  });

  it('o modelo de documento é nomeado pelos tipos que existem de verdade', () => {
    /*
     * `DocumentTemplateService::TYPES` é ['certificado', 'historico'].
     * O handoff sugeriu "certificado e declaração" — declaração não existe, e
     * um menu que promete uma tela inexistente é pior que um nome feio.
     */
    const rotulo = itemDoAdmin('templates')?.rotulo ?? '';

    expect(rotulo.toLowerCase()).toContain('certificado');
    expect(rotulo.toLowerCase()).toContain('histórico');
    expect(rotulo.toLowerCase()).not.toContain('declaração');
  });

  it('nenhum rótulo se repete', () => {
    const rotulos = itensVisiveisDoAdmin(TUDO_LIGADO).map((i) => i.rotulo);

    expect(new Set(rotulos).size).toBe(rotulos.length);
  });
});

describe('o subtítulo de cada seção', () => {
  it('cada aba tem o seu, e nenhum se repete', () => {
    /*
     * Havia UM subtítulo — "Gestão global de professores, alunos, turmas e
     * cursos" — nas dez telas, inclusive em Configurações e Páginas do Site,
     * onde não descrevia nada do que estava ali.
     */
    const subtitulos = COMO_ERA.map(({ id }) => itemDoAdmin(id)?.subtitulo ?? '');

    expect(subtitulos.every((s) => s.trim().length > 12)).toBe(true);
    expect(new Set(subtitulos).size).toBe(subtitulos.length);
  });

  it('nenhum repete o texto global antigo', () => {
    for (const { id } of COMO_ERA) {
      expect(itemDoAdmin(id)?.subtitulo, id).not.toContain('Gestão global de professores');
    }
  });

  it('a aba desligada ainda tem cabeçalho', () => {
    // Quem chega por link antigo precisa ler onde está, mesmo com a flag em false.
    expect(itemDoAdmin('export_bi')?.subtitulo).toBeTruthy();
    expect(itensVisiveisDoAdmin({}).map((i) => i.id)).not.toContain('export_bi');
  });
});
