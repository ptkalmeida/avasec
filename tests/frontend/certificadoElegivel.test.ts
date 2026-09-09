import { describe, it, expect } from 'vitest';
import {
  avaliacoesPendentes,
  oQueFaltaParaOCertificado,
  podeReceberCertificado,
} from '../../src/utils/certificadoElegivel';

const quiz = (id: string, courseId: string, title: string) => ({ id, courseId, title });
const sub = (quizId: string, courseId: string, userId: string, passed: boolean) =>
  ({ quizId, courseId, userId, passed });

const ALUNO = 'user-1';

describe('avaliações que faltam', () => {
  it('curso sem avaliação não tem pendência', () => {
    // Curso sem prova segue só pela frequência — é o que ele mede.
    expect(avaliacoesPendentes([], [], 'course-1', ALUNO)).toEqual([]);
    expect(avaliacoesPendentes(null, null, 'course-1', ALUNO)).toEqual([]);
    expect(avaliacoesPendentes([quiz('q1', 'course-2', 'De outro curso')], [], 'course-1', ALUNO)).toEqual([]);
  });

  it('prova não feita é pendência', () => {
    expect(avaliacoesPendentes([quiz('q1', 'course-1', 'Prova Final')], [], 'course-1', ALUNO))
      .toEqual(['Prova Final']);
  });

  it('prova REPROVADA continua pendente', () => {
    // A decisão foi "aprovada", não "respondida": zerar a prova não conclui.
    const r = avaliacoesPendentes(
      [quiz('q1', 'course-1', 'Prova Final')],
      [sub('q1', 'course-1', ALUNO, false)],
      'course-1', ALUNO
    );

    expect(r).toEqual(['Prova Final']);
  });

  it('prova aprovada sai da lista', () => {
    expect(avaliacoesPendentes(
      [quiz('q1', 'course-1', 'Prova Final')],
      [sub('q1', 'course-1', ALUNO, true)],
      'course-1', ALUNO
    )).toEqual([]);
  });

  it('aprovação em tentativa ANTERIOR continua valendo', () => {
    /*
     * A ADR 12 manda acrescentar tentativa, não sobrescrever. Ler "a última
     * tentativa" tiraria um certificado já conquistado de quem refez a prova
     * por curiosidade e foi mal.
     */
    const r = avaliacoesPendentes(
      [quiz('q1', 'course-1', 'Prova Final')],
      [sub('q1', 'course-1', ALUNO, true), sub('q1', 'course-1', ALUNO, false)],
      'course-1', ALUNO
    );

    expect(r).toEqual([]);
  });

  it('aprovação de OUTRO aluno não conta', () => {
    const r = avaliacoesPendentes(
      [quiz('q1', 'course-1', 'Prova Final')],
      [sub('q1', 'course-1', 'user-2', true)],
      'course-1', ALUNO
    );

    expect(r).toEqual(['Prova Final']);
  });

  it('aprovação em OUTRO curso não conta', () => {
    const r = avaliacoesPendentes(
      [quiz('q1', 'course-1', 'Prova Final')],
      [sub('q1', 'course-9', ALUNO, true)],
      'course-1', ALUNO
    );

    expect(r).toEqual(['Prova Final']);
  });

  it('todas as avaliações são exigidas', () => {
    const r = avaliacoesPendentes(
      [quiz('q1', 'course-1', 'Prova 1'), quiz('q2', 'course-1', 'Prova 2'), quiz('q3', 'course-1', 'Prova 3')],
      [sub('q1', 'course-1', ALUNO, true)],
      'course-1', ALUNO
    );

    expect(r).toEqual(['Prova 2', 'Prova 3']);
  });
});

describe('o certificado pode sair?', () => {
  it('frequência cheia SEM a prova não basta', () => {
    /*
     * O defeito exato que a regra veio fechar: com conclusão automática ao
     * avançar, clicar "Próxima aula" até o fim dava 100% de frequência.
     */
    expect(podeReceberCertificado({ frequencia: 100, frequenciaMinima: 70, pendentes: ['Prova Final'] }))
      .toBe(false);
  });

  it('prova aprovada SEM frequência também não basta', () => {
    expect(podeReceberCertificado({ frequencia: 40, frequenciaMinima: 70, pendentes: [] })).toBe(false);
  });

  it('os dois critérios cumpridos liberam', () => {
    expect(podeReceberCertificado({ frequencia: 70, frequenciaMinima: 70, pendentes: [] })).toBe(true);
  });

  it('curso sem prova segue liberando pela frequência', () => {
    // A regra nova não pode travar o que já funcionava.
    expect(podeReceberCertificado({ frequencia: 85, frequenciaMinima: 70, pendentes: [] })).toBe(true);
  });
});

describe('o que dizer na tela', () => {
  it('nada a dizer quando está tudo cumprido', () => {
    expect(oQueFaltaParaOCertificado({ frequencia: 90, frequenciaMinima: 70, pendentes: [] })).toBeNull();
  });

  it('só frequência', () => {
    expect(oQueFaltaParaOCertificado({ frequencia: 50, frequenciaMinima: 70, pendentes: [] }))
      .toBe('Frequência de 70% exigida — você tem 50%.');
  });

  it('só uma avaliação, nomeada', () => {
    // Nomear a prova evita a pergunta seguinte: "qual avaliação?".
    expect(oQueFaltaParaOCertificado({ frequencia: 90, frequenciaMinima: 70, pendentes: ['Prova Final'] }))
      .toBe('Falta ser aprovado na avaliação "Prova Final".');
  });

  it('várias avaliações', () => {
    expect(oQueFaltaParaOCertificado({ frequencia: 90, frequenciaMinima: 70, pendentes: ['Prova 1', 'Prova 2'] }))
      .toBe('Falta ser aprovado em 2 avaliações: Prova 1, Prova 2.');
  });

  it('as duas coisas ao mesmo tempo', () => {
    expect(oQueFaltaParaOCertificado({ frequencia: 30, frequenciaMinima: 70, pendentes: ['Prova Final'] }))
      .toBe('Falta ser aprovado na avaliação "Prova Final", e a frequência de 70% (você tem 30%).');
  });
});
