import { describe, it, expect } from 'vitest';
import {
  RAIZ_INSTRUTOR,
  abaDaSecao,
  secaoDaAba,
  caminhoInstrutor,
  parseInstrutor,
} from '../../src/router/instructorRoutes';

describe('curso antes da seção', () => {
  it('a raiz do painel é a escolha do curso, sem seção', () => {
    /*
     * A regra de uso, não estética de URL: `selectedCourseId` nascia em
     * `courses[0]?.id`, então quem abria o painel já estava dentro de um curso
     * que não escolheu, e a Grade Curricular mostrava a grade dele.
     */
    const d = parseInstrutor('/inst');
    expect(d.courseId).toBeNull();
    expect(d.secao).toBe('gestao');
  });

  it('seção sem curso não existe: cai na escolha', () => {
    // Não há como pedir "a grade curricular" sem dizer de qual curso.
    expect(parseInstrutor('/inst/grade-curricular').courseId).toBeNull();
    expect(parseInstrutor('/inst/curso').courseId).toBeNull();
    expect(parseInstrutor('/inst/curso/').courseId).toBeNull();
  });

  it('sem curso, o caminho é a raiz — mesmo pedindo uma seção', () => {
    expect(caminhoInstrutor({})).toBe(RAIZ_INSTRUTOR);
    expect(caminhoInstrutor({ courseId: null, secao: 'alunos' })).toBe(RAIZ_INSTRUTOR);
    expect(caminhoInstrutor({ courseId: '', secao: 'grade-curricular' })).toBe(RAIZ_INSTRUTOR);
  });
});

describe('endereço das seções', () => {
  it('o curso sozinho já é a Gestão do Curso', () => {
    // Sem `/gestao` no caminho: seriam dois endereços para a mesma tela.
    expect(caminhoInstrutor({ courseId: 'course-1' })).toBe('/inst/curso/course-1');
    expect(caminhoInstrutor({ courseId: 'course-1', secao: 'gestao' })).toBe('/inst/curso/course-1');
    expect(parseInstrutor('/inst/curso/course-1').secao).toBe('gestao');
  });

  it('monta e lê cada seção', () => {
    expect(caminhoInstrutor({ courseId: 'course-1', secao: 'grade-curricular' }))
      .toBe('/inst/curso/course-1/grade-curricular');
    expect(caminhoInstrutor({ courseId: 'course-1', secao: 'alunos' }))
      .toBe('/inst/curso/course-1/alunos');

    expect(parseInstrutor('/inst/curso/course-1/grade-curricular')).toMatchObject({
      courseId: 'course-1',
      secao: 'grade-curricular',
    });
    expect(parseInstrutor('/inst/curso/course-1/alunos').secao).toBe('alunos');
  });

  it('ida e volta em todas as seções', () => {
    for (const secao of ['gestao', 'grade-curricular', 'avaliacoes', 'alunos', 'mensagens'] as const) {
      const caminho = caminhoInstrutor({ courseId: 'course-9', secao });
      expect(parseInstrutor(caminho)).toMatchObject({ courseId: 'course-9', secao });
    }
  });

  it('seção desconhecida mantém o curso e cai na entrada dele', () => {
    // O curso do endereço é informação boa mesmo com o resto errado — melhor
    // que jogar a pessoa de volta para a escolha.
    const d = parseInstrutor('/inst/curso/course-1/secao-que-nao-existe');
    expect(d.courseId).toBe('course-1');
    expect(d.secao).toBe('gestao');
  });

  it('id com caractere especial sobrevive à ida e volta, barra inclusive', () => {
    /*
     * Os ids reais são `course-1`, mas isto é o que impede um id inesperado de
     * virar caminho: a barra sai como `%2F`, então não parte o endereço em dois
     * segmentos nem é lida como seção.
     */
    const id = 'curso com espaço/e-barra';
    const caminho = caminhoInstrutor({ courseId: id });
    expect(caminho).toContain('%2F');
    expect(parseInstrutor(caminho).courseId).toBe(id);
    expect(parseInstrutor(caminho).secao).toBe('gestao');
  });
});

describe('sub-abas de Avaliações', () => {
  it('provas é o padrão e fica FORA do endereço', () => {
    // `/avaliacoes` e `/avaliacoes/provas` seriam a mesma tela com dois endereços.
    expect(caminhoInstrutor({ courseId: 'course-1', secao: 'avaliacoes' }))
      .toBe('/inst/curso/course-1/avaliacoes');
    expect(caminhoInstrutor({ courseId: 'course-1', secao: 'avaliacoes', subAba: 'provas' }))
      .toBe('/inst/curso/course-1/avaliacoes');
    expect(parseInstrutor('/inst/curso/course-1/avaliacoes').subAba).toBe('provas');
  });

  it('exercícios têm endereço próprio dentro de avaliações', () => {
    // O pedido: exercícios práticos deixam de ser aba solta e passam a viver
    // dentro de Avaliações.
    expect(caminhoInstrutor({ courseId: 'course-1', secao: 'avaliacoes', subAba: 'exercicios' }))
      .toBe('/inst/curso/course-1/avaliacoes/exercicios');
    expect(parseInstrutor('/inst/curso/course-1/avaliacoes/exercicios')).toMatchObject({
      secao: 'avaliacoes',
      subAba: 'exercicios',
    });
  });

  it('sub-aba só vale em avaliações', () => {
    expect(caminhoInstrutor({ courseId: 'c1', secao: 'alunos', subAba: 'exercicios' }))
      .toBe('/inst/curso/c1/alunos');
    expect(parseInstrutor('/inst/curso/c1/grade-curricular/exercicios').subAba).toBe('provas');
  });
});

describe('janelas (modais) no endereço', () => {
  it('a janela entra e sai do endereço', () => {
    // É o que faz o Voltar do navegador FECHAR o modal em vez de trocar de página.
    expect(caminhoInstrutor({ courseId: 'course-1', janela: 'nova-aula' }))
      .toBe('/inst/curso/course-1?janela=nova-aula');
    expect(parseInstrutor('/inst/curso/course-1', '?janela=nova-aula').janela).toBe('nova-aula');
    expect(parseInstrutor('/inst/curso/course-1').janela).toBeNull();
  });

  it('a janela convive com seção e sub-aba', () => {
    expect(caminhoInstrutor({
      courseId: 'course-1', secao: 'avaliacoes', subAba: 'exercicios', janela: 'documentos',
    })).toBe('/inst/curso/course-1/avaliacoes/exercicios?janela=documentos');
  });

  it('a janela sobrevive à ausência de curso', () => {
    // "Cadastrar Novo Curso" abre na tela de escolha, quando ainda não há curso.
    expect(caminhoInstrutor({ courseId: null, janela: 'novo-curso' }))
      .toBe('/inst?janela=novo-curso');
    expect(parseInstrutor('/inst', '?janela=novo-curso').janela).toBe('novo-curso');
  });

  it('janela vazia não suja o endereço', () => {
    expect(caminhoInstrutor({ courseId: 'c1', janela: '' })).toBe('/inst/curso/c1');
    expect(caminhoInstrutor({ courseId: 'c1', janela: null })).toBe('/inst/curso/c1');
  });
});

describe('seção e aba interna', () => {
  it('traduz nos dois sentidos', () => {
    expect(abaDaSecao('grade-curricular')).toBe('curriculum');
    expect(abaDaSecao('alunos')).toBe('students');
    expect(abaDaSecao('gestao')).toBe('general');
    expect(secaoDaAba('curriculum')).toBe('grade-curricular');
    expect(secaoDaAba('students')).toBe('alunos');
    expect(secaoDaAba('avaliacoes')).toBe('avaliacoes');
  });

  it('aba que este painel não expõe cai na entrada, sem inventar endereço', () => {
    // 'certificates', 'library' e 'faq' existem no tipo mas não neste painel.
    expect(secaoDaAba('certificates')).toBe('gestao');
    expect(secaoDaAba('library')).toBe('gestao');
  });
});

describe('caminhos que não são do painel', () => {
  it('não confunde prefixo parecido', () => {
    // `/institucional` começa com as letras de `/inst`.
    expect(parseInstrutor('/institucional/curso/course-1').courseId).toBeNull();
    expect(parseInstrutor('/app').courseId).toBeNull();
    expect(parseInstrutor('/').courseId).toBeNull();
  });
});
