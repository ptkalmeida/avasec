import { describe, it, expect } from 'vitest';
import {
  RAIZ_ALUNO,
  SEGMENTOS_RESERVADOS,
  TelaAluno,
  abaDaSecaoAluno,
  secaoDaAbaAluno,
  caminhoAluno,
  parseAluno,
} from '../../src/router/studentRoutes';

describe('as telas que o endereço perdia', () => {
  it('prova em andamento, aula e lista deixam de ter o mesmo endereço', () => {
    /*
     * O defeito que originou isto: `/app` era a URL das três telas. Um F5 no
     * meio da prova caía na lista de cursos, e nada era linkável.
     */
    const enderecos = [
      caminhoAluno({ tela: 'painel' }),
      caminhoAluno({ tela: 'curso', cursoRef: 'course-1' }),
      caminhoAluno({ tela: 'aula', cursoRef: 'course-1', lessonId: 'aula-1' }),
      caminhoAluno({ tela: 'avaliacoes', cursoRef: 'course-1' }),
      caminhoAluno({ tela: 'avaliacoes', cursoRef: 'course-1', quizId: 'quiz-1' }),
      caminhoAluno({ tela: 'exercicios', cursoRef: 'course-1' }),
      caminhoAluno({ tela: 'ao-vivo', cursoRef: 'course-1', sessionId: 's-1' }),
      caminhoAluno({ tela: 'catalogo', catalogoId: 'course-9' }),
    ];

    expect(new Set(enderecos).size).toBe(enderecos.length);
  });

  it('ida e volta em toda tela de dentro do curso', () => {
    const casos: { tela: TelaAluno; extra: Record<string, string> }[] = [
      { tela: 'curso', extra: {} },
      { tela: 'aula', extra: { lessonId: 'aula-7' } },
      { tela: 'avaliacoes', extra: {} },
      { tela: 'avaliacoes', extra: { quizId: 'quiz-1' } },
      { tela: 'exercicios', extra: {} },
      { tela: 'ao-vivo', extra: { sessionId: 'sessao-3' } },
    ];

    for (const { tela, extra } of casos) {
      const caminho = caminhoAluno({ tela, cursoRef: 'course-1', ...extra });
      const [pathname, search] = caminho.split('?');
      expect(parseAluno(pathname, search ?? '')).toMatchObject({
        tela, cursoRef: 'course-1', ...extra,
      });
    }
  });

  it('a prova em andamento tem endereço próprio, separado da lista', () => {
    // É o que faz o F5 voltar para a prova, e o link "sua prova está aqui" existir.
    expect(caminhoAluno({ tela: 'avaliacoes', cursoRef: 'course-1', quizId: 'quiz-1' }))
      .toBe('/aluno/curso/course-1/avaliacoes/quiz-1');
    expect(parseAluno('/aluno/curso/course-1/avaliacoes/quiz-1').quizId).toBe('quiz-1');
    expect(parseAluno('/aluno/curso/course-1/avaliacoes').quizId).toBeNull();
  });
});

describe('curso antes da tela', () => {
  it('tela de dentro do curso sem curso é a raiz do painel', () => {
    // Mesma regra do painel do instrutor: não há como pedir "a aula" sem dizer
    // de qual curso.
    expect(caminhoAluno({ tela: 'aula', lessonId: 'aula-1' })).toBe(RAIZ_ALUNO);
    expect(caminhoAluno({ tela: 'avaliacoes', cursoRef: null })).toBe(RAIZ_ALUNO);
    expect(caminhoAluno({ tela: 'exercicios', cursoRef: '' })).toBe(RAIZ_ALUNO);

    expect(parseAluno('/aluno/curso').tela).toBe('painel');
    expect(parseAluno('/aluno/curso/').cursoRef).toBeNull();
  });

  it('id incompleto cai no curso aberto, não em tela vazia', () => {
    expect(parseAluno('/aluno/curso/course-1/aula')).toMatchObject({ tela: 'curso', lessonId: null });
    expect(parseAluno('/aluno/curso/course-1/ao-vivo')).toMatchObject({ tela: 'curso', sessionId: null });
    expect(parseAluno('/aluno/curso/course-1/tela-que-nao-existe')).toMatchObject({
      tela: 'curso', cursoRef: 'course-1',
    });
  });
});

describe('seções do painel', () => {
  it('a entrada não ganha segmento próprio', () => {
    // `/aluno` já é o Ambiente de Estudos; `/aluno/geral` seria o segundo endereço.
    expect(caminhoAluno({})).toBe('/aluno');
    expect(caminhoAluno({ tela: 'painel', aba: 'general' })).toBe('/aluno');
    expect(parseAluno('/aluno').aba).toBe('general');
  });

  it('cada seção monta e lê', () => {
    expect(caminhoAluno({ aba: 'certificates' })).toBe('/aluno/certificados');
    expect(caminhoAluno({ aba: 'library' })).toBe('/aluno/biblioteca');
    expect(caminhoAluno({ aba: 'settings' })).toBe('/aluno/perfil');
    expect(parseAluno('/aluno/duvidas').aba).toBe('faq');
    expect(parseAluno('/aluno/mensagens').aba).toBe('messages');
    expect(parseAluno('/aluno/solicitacoes').aba).toBe('documents');
    expect(parseAluno('/aluno/eventos').aba).toBe('events');
  });

  it('aba que este painel não expõe não inventa endereço', () => {
    // 'curriculum' e 'students' são do painel do instrutor.
    expect(secaoDaAbaAluno('curriculum')).toBe('');
    expect(secaoDaAbaAluno('students')).toBe('');
    expect(caminhoAluno({ aba: 'curriculum' })).toBe('/aluno');
    expect(abaDaSecaoAluno('nao-existe')).toBe('general');
  });

  it('nenhuma seção pode se chamar como um segmento reservado', () => {
    /*
     * Colisão aqui daria endereço ambíguo: `/aluno/curso` significaria a seção
     * e o começo de um curso ao mesmo tempo.
     */
    for (const reservado of SEGMENTOS_RESERVADOS) {
      expect(abaDaSecaoAluno(reservado)).toBe('general');
    }
    expect(parseAluno('/aluno/catalogo').tela).toBe('painel');
  });
});

describe('janela', () => {
  it('o endereço de um curso NÃO carrega mais módulo', () => {
    /*
     * `?modulo=` saiu (09/09/2026). Ele guardava o NOME do módulo, e módulo não
     * existe no banco: era um trecho de endereço ancorado em texto escrito no
     * componente. Este teste prende a remoção — se `modulo` voltar a entrar na
     * query, o endereço volta a apontar para um conceito inexistente.
     */
    expect(caminhoAluno({ tela: 'curso', cursoRef: 'course-1' })).toBe('/aluno/curso/course-1');
    expect(caminhoAluno({ tela: 'curso', cursoRef: 'c1', janela: 'ementa' }))
      .toBe('/aluno/curso/c1?janela=ementa');
  });

  it('a janela entra e sai', () => {
    // É o que faz o Voltar do navegador FECHAR o modal em vez de trocar de tela.
    expect(caminhoAluno({ aba: 'certificates', janela: 'emitir' }))
      .toBe('/aluno/certificados?janela=emitir');
    expect(parseAluno('/aluno/curso/c1', '?janela=ementa')).toMatchObject({ janela: 'ementa' });
    expect(parseAluno('/aluno').janela).toBeNull();
    expect(caminhoAluno({ janela: '' })).toBe('/aluno');
  });
});

describe('endereços que não são do painel do aluno', () => {
  it('não confunde prefixo parecido', () => {
    expect(parseAluno('/alunos/curso/c1').cursoRef).toBeNull();
    expect(parseAluno('/inst/curso/course-1').cursoRef).toBeNull();
    expect(parseAluno('/').tela).toBe('painel');
  });

  it('id com caractere especial sobrevive à ida e volta, barra inclusive', () => {
    // A barra sai como %2F, então não parte o endereço em dois segmentos nem é
    // lida como nome de tela.
    const id = 'curso com espaço/e-barra';
    const caminho = caminhoAluno({ tela: 'aula', cursoRef: id, lessonId: 'a/1' });
    expect(caminho).toContain('%2F');
    expect(parseAluno(caminho)).toMatchObject({ cursoRef: id, lessonId: 'a/1', tela: 'aula' });
  });
});
