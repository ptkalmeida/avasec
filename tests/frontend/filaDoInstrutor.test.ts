import { describe, it, expect } from 'vitest';
import { filaDoInstrutor, textoDoItem } from '../../src/utils/filaDoInstrutor';

const EU = 'prof-1';
const OUTRO = 'prof-2';

const CURSOS = [
  { id: 'c1', instructorId: EU },
  { id: 'c2', instructorId: EU },
  { id: 'c3', instructorId: OUTRO },
];

const EXERCICIOS = [
  { id: 'e1', courseId: 'c1' },
  { id: 'e2', courseId: 'c3' },
];

describe('a fila conta trabalho que existe', () => {
  it('matrícula pendente do meu curso entra', () => {
    const fila = filaDoInstrutor({
      admissionRequests: [
        { status: 'pending', courseId: 'c1' },
        { status: 'pending', courseId: 'c2' },
      ],
      courses: CURSOS,
      instructorId: EU,
    });

    expect(fila).toHaveLength(1);
    expect(fila[0].id).toBe('matriculas');
    expect(fila[0].quantidade).toBe(2);
    expect(fila[0].aba).toBe('students');
  });

  it('matrícula de curso de OUTRO instrutor não entra', () => {
    /*
     * Somar a escola inteira mostraria trabalho que não é dele — e é assim que
     * a pessoa aprende a ignorar a fila.
     */
    const fila = filaDoInstrutor({
      admissionRequests: [{ status: 'pending', courseId: 'c3' }],
      courses: CURSOS,
      instructorId: EU,
    });

    expect(fila).toEqual([]);
  });

  it('matrícula já aprovada não entra', () => {
    const fila = filaDoInstrutor({
      admissionRequests: [
        { status: 'approved', courseId: 'c1' },
        { status: 'rejected', courseId: 'c1' },
      ],
      courses: CURSOS,
      instructorId: EU,
    });

    expect(fila).toEqual([]);
  });

  it('entrega pendente de exercício do meu curso entra', () => {
    const fila = filaDoInstrutor({
      exerciseSubmissions: [
        { status: 'pending', exerciseId: 'e1' },
        { status: 'pending', exerciseId: 'e1' },
      ],
      exercises: EXERCICIOS,
      courses: CURSOS,
      instructorId: EU,
    });

    expect(fila).toHaveLength(1);
    expect(fila[0].id).toBe('exercicios');
    expect(fila[0].quantidade).toBe(2);
    expect(fila[0].aba).toBe('exercicios');
  });

  it('entrega de exercício de curso alheio não entra', () => {
    const fila = filaDoInstrutor({
      exerciseSubmissions: [{ status: 'pending', exerciseId: 'e2' }],
      exercises: EXERCICIOS,
      courses: CURSOS,
      instructorId: EU,
    });

    expect(fila).toEqual([]);
  });

  it('entrega já corrigida não entra', () => {
    const fila = filaDoInstrutor({
      exerciseSubmissions: [
        { status: 'approved', exerciseId: 'e1' },
        { status: 'revision', exerciseId: 'e1' },
      ],
      exercises: EXERCICIOS,
      courses: CURSOS,
      instructorId: EU,
    });

    expect(fila).toEqual([]);
  });

  it('as duas pendências convivem, na ordem', () => {
    const fila = filaDoInstrutor({
      admissionRequests: [{ status: 'pending', courseId: 'c1' }],
      exerciseSubmissions: [{ status: 'pending', exerciseId: 'e1' }],
      exercises: EXERCICIOS,
      courses: CURSOS,
      instructorId: EU,
    });

    expect(fila.map((i) => i.id)).toEqual(['matriculas', 'exercicios']);
  });
});

describe('fila vazia é ausência de fila', () => {
  it('item com zero NÃO aparece', () => {
    /*
     * "0 exercícios a corrigir" fixo na tela vira ruído permanente, e a pessoa
     * para de ler a fila inteira — inclusive quando há algo lá.
     */
    expect(filaDoInstrutor({ courses: CURSOS, instructorId: EU })).toEqual([]);
    expect(
      filaDoInstrutor({
        admissionRequests: [],
        exerciseSubmissions: [],
        exercises: EXERCICIOS,
        courses: CURSOS,
        instructorId: EU,
      })
    ).toEqual([]);
  });

  it('dado ausente não vira contagem nem erro', () => {
    expect(filaDoInstrutor({ admissionRequests: null, courses: null, instructorId: EU })).toEqual([]);
    expect(filaDoInstrutor({ instructorId: '' })).toEqual([]);
  });
});

describe('não há terceiro item', () => {
  it('a fila nunca inclui "notas a lançar"', () => {
    /*
     * O handoff pede três contadores. O terceiro não existe: avaliação neste
     * sistema é corrigida automaticamente — `QuizSubmission` já chega com
     * `scorePercent` e `passed` —, então não há nota esperando lançamento.
     * Um contador de trabalho inexistente manda a pessoa procurar o que não
     * existe.
     */
    const fila = filaDoInstrutor({
      admissionRequests: [{ status: 'pending', courseId: 'c1' }],
      exerciseSubmissions: [{ status: 'pending', exerciseId: 'e1' }],
      exercises: EXERCICIOS,
      courses: CURSOS,
      instructorId: EU,
    });

    expect(fila.map((i) => i.id)).not.toContain('notas');
    expect(fila).toHaveLength(2);
  });
});

describe('concordância do texto', () => {
  it('singular com um, plural com mais de um', () => {
    const [item] = filaDoInstrutor({
      admissionRequests: [{ status: 'pending', courseId: 'c1' }],
      courses: CURSOS,
      instructorId: EU,
    });

    expect(textoDoItem(item)).toBe('1 matrícula aguardando aprovação');
    expect(textoDoItem({ ...item, quantidade: 3 })).toBe('3 matrículas aguardando aprovação');
  });
});
