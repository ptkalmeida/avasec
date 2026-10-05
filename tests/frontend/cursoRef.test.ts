import { describe, it, expect } from 'vitest';
import { cursoPorRef, refDoCurso, refEhCanonica } from '../../src/utils/cursoRef';

const curso = (id: string, slug?: string) => ({ id, slug });

// Os cursos reais do banco, com os slugs que a migration gerou.
const CATALOGO = [
  curso('course-1', 'ux-ui-design-interfaces-de-alta-performance'),
  curso('course-2', 'desenvolvimento-full-stack-react-nodejs-e-apis-modernas'),
  curso('course-3', 'metodologias-ageis-e-kanban-na-gestao-escolar-e-ti'),
  curso('course-1787928131660', 'curso-de-fotografia'),
];

describe('o que vai para o endereço', () => {
  it('usa o slug quando existe', () => {
    expect(refDoCurso(CATALOGO[0])).toBe('ux-ui-design-interfaces-de-alta-performance');
  });

  it('cai no id quando o curso não tem slug', () => {
    /*
     * Curso de `mockData`, ou vindo de uma resposta anterior à ADR 13, não tem
     * slug. O endereço fica feio e continua funcionando — melhor que um link
     * quebrado ou um `/aluno/curso/undefined`.
     */
    expect(refDoCurso(curso('course-9'))).toBe('course-9');
    expect(refDoCurso({ id: 'course-9', slug: '' })).toBe('course-9');
    expect(refDoCurso({ id: 'course-9', slug: '   ' })).toBe('course-9');
  });

  it('curso ausente não produz "undefined" no caminho', () => {
    expect(refDoCurso(null)).toBe('');
    expect(refDoCurso(undefined)).toBe('');
  });
});

describe('leitura do endereço', () => {
  it('acha o curso pelo slug', () => {
    expect(cursoPorRef(CATALOGO, 'curso-de-fotografia')?.id).toBe('course-1787928131660');
  });

  it('acha o curso pelo ID antigo, para o link salvo não morrer', () => {
    // Todo link que circula hoje tem esta forma.
    expect(cursoPorRef(CATALOGO, 'course-1')?.slug)
      .toBe('ux-ui-design-interfaces-de-alta-performance');
  });

  it('referência desconhecida devolve null, e não o primeiro da lista', () => {
    /*
     * O defeito que este null evita já existiu no painel do instrutor:
     * `courses[0]?.id` fazia quem abria o painel entrar num curso que não
     * escolheu. Endereço errado tem de virar "não encontrei", não "abri outro".
     */
    expect(cursoPorRef(CATALOGO, 'curso-que-nao-existe')).toBeNull();
    expect(cursoPorRef(CATALOGO, '')).toBeNull();
    expect(cursoPorRef(CATALOGO, null)).toBeNull();
    expect(cursoPorRef([], 'course-1')).toBeNull();
    expect(cursoPorRef(null, 'course-1')).toBeNull();
  });

  it('slug tem precedência sobre id', () => {
    /*
     * Cenário construído: um curso cujo ID é igual ao SLUG de outro. O backend
     * impede que isso nasça (CursoSlug::ehFormaDeId), e a precedência aqui é a
     * segunda barreira — se a primeira falhar, o slug ainda ganha de um id
     * homônimo, em vez de a resposta depender da ordem da lista.
     */
    const conflito = [curso('curso-de-fotografia', 'outro-curso'), curso('course-x', 'curso-de-fotografia')];

    expect(cursoPorRef(conflito, 'curso-de-fotografia')?.id).toBe('course-x');
  });
});

describe('endereço canônico', () => {
  it('slug é canônico; id antigo não é', () => {
    expect(refEhCanonica(CATALOGO[0], 'ux-ui-design-interfaces-de-alta-performance')).toBe(true);
    expect(refEhCanonica(CATALOGO[0], 'course-1')).toBe(false);
  });

  it('sem curso ou sem referência não manda trocar a URL', () => {
    // Trocar a URL enquanto a lista ainda carrega jogaria a pessoa fora da tela.
    expect(refEhCanonica(null, 'course-1')).toBe(true);
    expect(refEhCanonica(CATALOGO[0], null)).toBe(true);
    expect(refEhCanonica(CATALOGO[0], '')).toBe(true);
  });

  it('curso sem slug: o id É o canônico', () => {
    expect(refEhCanonica(curso('course-9'), 'course-9')).toBe(true);
  });
});
