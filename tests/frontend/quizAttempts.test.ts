import { describe, it, expect } from 'vitest';
import {
  momentoIso,
  momentoDaTentativa,
  ordenarTentativas,
  tentativasDe,
  tentativaVigente,
  tentativasAnteriores,
  apenasVigentes,
  mediaDasVigentes,
  mediaDoAlunoNoCurso,
  textoDaTentativa,
} from '../../src/utils/quizAttempts';
import { QuizSubmission } from '../../src/types';

const sub = (over: Partial<QuizSubmission> = {}): QuizSubmission => ({
  id: 's1',
  userId: 'aluno-1',
  studentName: 'Clara Ribeiro',
  courseId: 'c1',
  quizId: 'q1',
  scorePercent: 50,
  passed: false,
  submittedAt: '03/09/2026 às 10:00',
  enviadoEm: '2026-09-03T10:00:00',
  ...over,
});

describe('momentoIso', () => {
  it('carrega o fuso, para comparar com o que o servidor devolve', () => {
    /*
     * A aplicação roda em UTC e o navegador do aluno não. Sem o fuso no valor,
     * a tentativa acabada de enviar pareceria três horas mais ANTIGA que as
     * anteriores (em Brasília), e a página mostraria a errada como vigente.
     */
    const d = new Date(2026, 8, 3, 16, 23, 7);
    expect(momentoIso(d)).toMatch(/Z$/);
    expect(new Date(momentoIso(d)).getTime()).toBe(d.getTime());
  });

  it('o instante sobrevive à ida e volta, seja qual for o fuso da máquina', () => {
    const d = new Date(2026, 0, 9, 4, 5, 6);
    expect(momentoDaTentativa({ enviadoEm: momentoIso(d), submittedAt: '' })).toBe(d.getTime());
  });
});

describe('momentoDaTentativa', () => {
  it('usa enviadoEm quando existe', () => {
    expect(momentoDaTentativa(sub({ enviadoEm: '2026-09-03T16:23:00' })))
      .toBe(new Date(2026, 8, 3, 16, 23).getTime());
  });

  it('cai para submittedAt quando não há enviadoEm, lendo dd/mm/aaaa', () => {
    // `new Date('03/09/2026')` leria o padrão americano: 3 de setembro viraria
    // 9 de março. É por isso que a data é montada campo a campo.
    const t = momentoDaTentativa(sub({ enviadoEm: undefined, submittedAt: '03/09/2026 às 16:23' }));
    expect(t).toBe(new Date(2026, 8, 3, 16, 23).getTime());
  });

  it('aceita as formas que o banco já gravou', () => {
    expect(momentoDaTentativa(sub({ enviadoEm: undefined, submittedAt: '03/09/2026 16:23' })))
      .toBe(new Date(2026, 8, 3, 16, 23).getTime());
    expect(momentoDaTentativa(sub({ enviadoEm: undefined, submittedAt: '03/09/2026' })))
      .toBe(new Date(2026, 8, 3, 0, 0).getTime());
  });

  it('devolve null em vez de inventar data', () => {
    expect(momentoDaTentativa(sub({ enviadoEm: undefined, submittedAt: 'ontem de manhã' }))).toBeNull();
    expect(momentoDaTentativa(sub({ enviadoEm: '   ', submittedAt: '' }))).toBeNull();
  });
});

describe('ordenação', () => {
  it('mais recente primeiro, e não alfabeticamente', () => {
    // O defeito que motivou a coluna: por texto, '01/12/2026' vem antes de
    // '03/09/2026' — dezembro apareceria como mais antigo que setembro.
    const dezembro = sub({ id: 'dez', submittedAt: '01/12/2026 às 09:00', enviadoEm: '2026-12-01T09:00:00' });
    const setembro = sub({ id: 'set', submittedAt: '03/09/2026 às 09:00', enviadoEm: '2026-09-03T09:00:00' });

    expect(ordenarTentativas([setembro, dezembro]).map((s) => s.id)).toEqual(['dez', 'set']);
    expect(ordenarTentativas([dezembro, setembro]).map((s) => s.id)).toEqual(['dez', 'set']);
  });

  it('tentativa sem data reconhecível vai para o fim', () => {
    const semData = sub({ id: 'sem', enviadoEm: undefined, submittedAt: 'sem data' });
    const comData = sub({ id: 'com' });

    expect(ordenarTentativas([semData, comData]).map((s) => s.id)).toEqual(['com', 'sem']);
  });

  it('não altera o array recebido', () => {
    const lista = [sub({ id: 'a', enviadoEm: '2026-09-01T10:00:00' }), sub({ id: 'b', enviadoEm: '2026-09-05T10:00:00' })];
    ordenarTentativas(lista);
    expect(lista.map((s) => s.id)).toEqual(['a', 'b']);
  });
});

describe('tentativa vigente', () => {
  const primeira = sub({ id: 'p', scorePercent: 20, enviadoEm: '2026-09-01T10:00:00' });
  const segunda = sub({ id: 's', scorePercent: 90, passed: true, enviadoEm: '2026-09-03T10:00:00' });
  const terceira = sub({ id: 't', scorePercent: 60, enviadoEm: '2026-09-05T10:00:00' });

  it('é a mais recente, não a de maior nota', () => {
    // Refazer substitui o resultado anterior — é o que o botão "Refazer" sempre
    // significou. Escolher a melhor nota mudaria a regra acadêmica.
    const lista = [primeira, terceira, segunda];
    expect(tentativaVigente(lista, 'q1', 'aluno-1')?.id).toBe('t');
    expect(tentativaVigente(lista, 'q1', 'aluno-1')?.scorePercent).toBe(60);
  });

  it('as anteriores vêm da mais recente para a mais antiga, sem a vigente', () => {
    expect(tentativasAnteriores([primeira, segunda, terceira], 'q1', 'aluno-1').map((s) => s.id))
      .toEqual(['s', 'p']);
  });

  it('não mistura aluno nem avaliação', () => {
    const deOutro = sub({ id: 'outro', userId: 'aluno-2', enviadoEm: '2026-12-01T10:00:00' });
    const doOutroQuiz = sub({ id: 'q2', quizId: 'q2', enviadoEm: '2026-12-02T10:00:00' });

    expect(tentativasDe([primeira, deOutro, doOutroQuiz], 'q1', 'aluno-1').map((s) => s.id)).toEqual(['p']);
    expect(tentativaVigente([deOutro, doOutroQuiz], 'q1', 'aluno-1')).toBeUndefined();
  });

  it('sem tentativa nenhuma, não há vigente nem anteriores', () => {
    expect(tentativaVigente([], 'q1', 'aluno-1')).toBeUndefined();
    expect(tentativasAnteriores([], 'q1', 'aluno-1')).toEqual([]);
  });
});

describe('médias', () => {
  it('conta uma tentativa por aluno e avaliação', () => {
    /*
     * Somar a lista crua virou "média entre tentativas" no dia em que o
     * histórico ficou no ar: quem refaz três vezes pesaria o triplo de quem fez
     * uma, e a nota baixa corrigida na segunda tentativa arrastaria o indicador
     * para sempre.
     */
    const lista = [
      sub({ id: 'a1', scorePercent: 0, enviadoEm: '2026-09-01T10:00:00' }),
      sub({ id: 'a2', scorePercent: 100, enviadoEm: '2026-09-02T10:00:00' }),
      sub({ id: 'b1', userId: 'aluno-2', scorePercent: 50, enviadoEm: '2026-09-01T10:00:00' }),
    ];

    expect(apenasVigentes(lista).map((s) => s.id).sort()).toEqual(['a2', 'b1']);
    expect(mediaDasVigentes(lista)).toBe(75);
  });

  it('separa avaliações do mesmo aluno', () => {
    const lista = [
      sub({ id: 'q1', quizId: 'q1', scorePercent: 40 }),
      sub({ id: 'q2', quizId: 'q2', scorePercent: 80 }),
    ];
    expect(mediaDasVigentes(lista)).toBe(60);
  });

  it('sem tentativa devolve null, para a tela poder dizer "Pendente"', () => {
    // Zero seria uma nota; ausência de nota não é nota zero.
    expect(mediaDasVigentes([])).toBeNull();
    expect(mediaDoAlunoNoCurso([sub()], 'aluno-1', 'outro-curso')).toBeNull();
  });

  it('média do aluno no curso ignora outro aluno e outro curso', () => {
    const lista = [
      sub({ id: 'meu', scorePercent: 100 }),
      sub({ id: 'outro-aluno', userId: 'aluno-2', scorePercent: 0 }),
      sub({ id: 'outro-curso', courseId: 'c2', quizId: 'q9', scorePercent: 0 }),
    ];
    expect(mediaDoAlunoNoCurso(lista, 'aluno-1', 'c1')).toBe(100);
  });
});

describe('textoDaTentativa', () => {
  it('mostra a hora no relógio de quem lê, e não a do servidor em UTC', () => {
    // O defeito real: `submittedAt` é formatado no servidor, que roda em UTC.
    // Uma tentativa das 17:08 de Brasília era exibida como "20:08".
    const brasilia17h = new Date(2026, 8, 3, 17, 8, 0);
    expect(textoDaTentativa({ enviadoEm: brasilia17h.toISOString(), submittedAt: '03/09/2026 às 20:08' }))
      .toBe('03/09/2026 às 17:08');
  });

  it('sem enviadoEm, mostra a string como ela está no banco', () => {
    // Não se adivinha correção sobre dado que não diz o próprio fuso.
    expect(textoDaTentativa({ enviadoEm: undefined, submittedAt: '01/09/2026 às 10:00' }))
      .toBe('01/09/2026 às 10:00');
    expect(textoDaTentativa({ enviadoEm: 'nao e data', submittedAt: 'ontem' })).toBe('ontem');
  });
});
