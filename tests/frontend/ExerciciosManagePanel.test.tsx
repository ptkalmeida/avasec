import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';
import { ExerciciosManagePanel } from '../../src/components/instructor/ExerciciosManagePanel';
import { Course, PracticalExercise, ExerciseSubmission } from '../../src/types';

const curso = (id: string, title: string): Course => ({
  id,
  title,
  description: 'd',
  category: 'Design',
  instructorId: 'prof-1',
  instructorName: 'Prof. Ana',
  lessons: [],
} as unknown as Course);

const ex = (id: string, over: Partial<PracticalExercise> = {}): PracticalExercise => ({
  id,
  courseId: 'course-1',
  title: `Atividade ${id}`,
  description: 'obj',
  instructions: 'inst',
  maxPoints: 100,
  ...over,
});

const entrega = (
  id: string,
  exerciseId: string,
  status: ExerciseSubmission['status'],
  over: Partial<ExerciseSubmission> = {}
): ExerciseSubmission => ({
  id,
  exerciseId,
  userId: 'aluno-1',
  studentName: 'João Silva',
  submissionText: 'texto da entrega',
  submittedAt: '01/09/2026 10:00',
  status,
  ...over,
});

const renderPanel = (
  over: Partial<React.ComponentProps<typeof ExerciciosManagePanel>> = {}
) => {
  const props = {
    courses: [curso('course-1', 'Design de Interfaces')],
    exercises: [] as PracticalExercise[],
    submissions: [] as ExerciseSubmission[],
    onCreate: vi.fn(async () => ({ ok: true })),
    onUpdate: vi.fn(async () => ({ ok: true })),
    onDelete: vi.fn(async () => ({ ok: true })),
    onGrade: vi.fn(async () => ({ ok: true })),
    confirmar: vi.fn(() => true),
    notify: vi.fn(),
    ...over,
  } as React.ComponentProps<typeof ExerciciosManagePanel>;
  render(<ExerciciosManagePanel {...props} />);

  return props;
};

const preencherFormulario = async (over: Record<string, string> = {}) => {
  const valores = {
    'Título': 'Nova atividade',
    'Descrição (objetivo)': 'objetivo',
    'Instruções de entrega': 'instruções',
    ...over,
  };
  for (const [rotulo, valor] of Object.entries(valores)) {
    const campo = screen.getByLabelText(rotulo);
    await userEvent.clear(campo);
    if (valor !== '') await userEvent.type(campo, valor);
  }
};

describe('ExerciciosManagePanel', () => {
  it('mostra quantas entregas esperam correção — o número de quem corrige', () => {
    renderPanel({
      exercises: [ex('e1')],
      submissions: [
        entrega('s1', 'e1', 'pending'),
        entrega('s2', 'e1', 'pending'),
        entrega('s3', 'e1', 'approved', { score: 90 }),
      ],
    });

    expect(screen.getByText('2 para corrigir')).toBeInTheDocument();
    expect(screen.getByText('1 corrigidas')).toBeInTheDocument();
  });

  it('sem exercícios, diz o que acontece quando lançar um', () => {
    renderPanel();
    expect(screen.getByText(/Nenhum exercício neste curso/i)).toBeInTheDocument();
  });

  it('recusa prazo que não seja dd/mm/aaaa, em vez de gravar texto livre', async () => {
    const props = renderPanel();
    await userEvent.click(screen.getByRole('button', { name: /novo exercício/i }));
    await preencherFormulario();
    await userEvent.type(screen.getByLabelText('Prazo (opcional)'), 'julho');
    await userEvent.click(screen.getByRole('button', { name: /^publicar$/i }));

    // Como texto livre, um "julho" entraria no banco e a página do aluno nunca
    // mostraria vencimento.
    expect(screen.getByText(/formato dd\/mm\/aaaa/i)).toBeInTheDocument();
    expect(props.onCreate).not.toHaveBeenCalled();
  });

  it('publica com prazo válido e sem prazo', async () => {
    const props = renderPanel();

    await userEvent.click(screen.getByRole('button', { name: /novo exercício/i }));
    await preencherFormulario();
    await userEvent.type(screen.getByLabelText('Prazo (opcional)'), '30/09/2026');
    await userEvent.click(screen.getByRole('button', { name: /^publicar$/i }));

    expect(props.onCreate).toHaveBeenCalledWith(
      'course-1', 'Nova atividade', 'objetivo', 'instruções', 100, '30/09/2026'
    );

    await userEvent.click(screen.getByRole('button', { name: /novo exercício/i }));
    await preencherFormulario({ 'Título': 'Sem prazo' });
    await userEvent.click(screen.getByRole('button', { name: /^publicar$/i }));

    expect(props.onCreate).toHaveBeenLastCalledWith(
      'course-1', 'Sem prazo', 'objetivo', 'instruções', 100, undefined
    );
  });

  it('recusa nota máxima que não seja inteiro de 1 a 1000', async () => {
    const props = renderPanel();
    await userEvent.click(screen.getByRole('button', { name: /novo exercício/i }));
    await preencherFormulario({ 'Nota máxima': '0' });
    await userEvent.click(screen.getByRole('button', { name: /^publicar$/i }));

    expect(screen.getByText(/inteiro entre 1 e 1000/i)).toBeInTheDocument();
    expect(props.onCreate).not.toHaveBeenCalled();
  });

  it('falha do servidor aparece no formulário e não é anunciada como sucesso', async () => {
    const onCreate = vi.fn(async () => ({ ok: false, error: 'Curso não é seu.' }));
    const props = renderPanel({ onCreate });

    await userEvent.click(screen.getByRole('button', { name: /novo exercício/i }));
    await preencherFormulario();
    await userEvent.click(screen.getByRole('button', { name: /^publicar$/i }));

    expect(screen.getByText('Curso não é seu.')).toBeInTheDocument();
    expect(props.notify).not.toHaveBeenCalled();
  });

  it('correção exige nota dentro do máximo do exercício', async () => {
    const props = renderPanel({
      exercises: [ex('e1', { maxPoints: 50 })],
      submissions: [entrega('s1', 'e1', 'pending')],
    });

    await userEvent.type(screen.getByLabelText(/Nota \(0 a 50\)/), '80');
    await userEvent.type(screen.getByLabelText(/feedback para o aluno/i), 'ok');
    await userEvent.click(screen.getByRole('button', { name: /aprovar/i }));

    expect(screen.getByText(/nota entre 0 e 50/i)).toBeInTheDocument();
    expect(props.onGrade).not.toHaveBeenCalled();
  });

  it('devolver trabalho exige feedback; aprovar não', async () => {
    const props = renderPanel({
      exercises: [ex('e1')],
      submissions: [entrega('s1', 'e1', 'pending')],
    });

    await userEvent.type(screen.getByLabelText(/Nota \(0 a 100\)/), '40');
    await userEvent.click(screen.getByRole('button', { name: /pedir ajustes/i }));

    // Devolver sem dizer o que corrigir não ajuda ninguém.
    expect(screen.getByText(/escreva o feedback para o aluno/i)).toBeInTheDocument();
    expect(props.onGrade).not.toHaveBeenCalled();

    await userEvent.click(screen.getByRole('button', { name: /aprovar/i }));
    expect(props.onGrade).toHaveBeenCalledWith('s1', 40, '', 'approved');
  });

  it('lança a correção com nota, feedback e desfecho', async () => {
    const props = renderPanel({
      exercises: [ex('e1')],
      submissions: [entrega('s1', 'e1', 'pending')],
    });

    await userEvent.type(screen.getByLabelText(/Nota \(0 a 100\)/), '85');
    await userEvent.type(screen.getByLabelText(/feedback para o aluno/i), 'Bom trabalho.');
    await userEvent.click(screen.getByRole('button', { name: /aprovar/i }));

    expect(props.onGrade).toHaveBeenCalledWith('s1', 85, 'Bom trabalho.', 'approved');
    expect(props.notify).toHaveBeenCalledWith(expect.stringMatching(/correção registrada/i));
  });

  it('entrega já corrigida não abre o formulário sozinha', () => {
    renderPanel({
      exercises: [ex('e1')],
      submissions: [entrega('s1', 'e1', 'approved', { score: 90, feedback: 'ok' })],
    });

    expect(screen.getByText('Aprovado · 90/100')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /rever correção/i })).toBeInTheDocument();
    expect(screen.queryByLabelText(/Nota \(0 a 100\)/)).toBeNull();
  });

  it('avisa quantas entregas serão apagadas antes de remover', async () => {
    const props = renderPanel({
      exercises: [ex('e1')],
      submissions: [entrega('s1', 'e1', 'approved', { score: 90 }), entrega('s2', 'e1', 'pending')],
    });

    await userEvent.click(screen.getByTitle('Remover exercício'));

    expect(props.confirmar).toHaveBeenCalledWith(expect.stringContaining('2 entrega(s)'));
    expect(props.onDelete).toHaveBeenCalledWith('e1');
  });

  it('não remove quando a confirmação é negada', async () => {
    const props = renderPanel({ exercises: [ex('e1')], confirmar: vi.fn(() => false) });

    await userEvent.click(screen.getByTitle('Remover exercício'));

    expect(props.onDelete).not.toHaveBeenCalled();
  });

  it('editar carrega os valores atuais e salva por update, não por criação', async () => {
    const props = renderPanel({ exercises: [ex('e1', { maxPoints: 40, dueDate: '30/09/2026' })] });

    await userEvent.click(screen.getByTitle('Editar exercício'));
    expect(screen.getByLabelText('Título')).toHaveValue('Atividade e1');
    expect(screen.getByLabelText('Prazo (opcional)')).toHaveValue('30/09/2026');

    await userEvent.clear(screen.getByLabelText('Título'));
    await userEvent.type(screen.getByLabelText('Título'), 'Atividade revisada');
    await userEvent.click(screen.getByRole('button', { name: /^salvar$/i }));

    expect(props.onUpdate).toHaveBeenCalledWith('e1', expect.objectContaining({
      title: 'Atividade revisada', maxPoints: 40, dueDate: '30/09/2026',
    }));
    expect(props.onCreate).not.toHaveBeenCalled();
  });

  it('sem curso sob gestão, não oferece um curso de outra pessoa', () => {
    renderPanel({ courses: [] });
    expect(screen.getByText(/nenhum curso sob sua gestão/i)).toBeInTheDocument();
  });
});
