import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';
import { ExerciciosPraticosPage } from '../../src/components/student/ExerciciosPraticosPage';
import { PracticalExercise, ExerciseSubmission } from '../../src/types';

const ex = (id: string, over: Partial<PracticalExercise> = {}): PracticalExercise => ({
  id,
  courseId: 'course-1',
  title: `Atividade ${id}`,
  description: 'Objetivo da atividade.',
  instructions: 'Entregue um relatório de 500 palavras.',
  maxPoints: 100,
  ...over,
});

const base = {
  courseTitle: 'Design de Interfaces',
  courseId: 'course-1',
  userId: 'aluno-1',
  onBack: () => {},
  onUpload: async () => ({ name: 'a.pdf', url: '/uploads/a.pdf' }),
  onDownload: async () => null,
  notify: () => {},
  permiteAnexo: true,
};

const renderPage = (over: Partial<React.ComponentProps<typeof ExerciciosPraticosPage>> = {}) => {
  const props = {
    ...base,
    exercises: [],
    submissions: [] as ExerciseSubmission[],
    onSubmit: async () => ({ ok: true }),
    ...over,
  } as React.ComponentProps<typeof ExerciciosPraticosPage>;
  render(<ExerciciosPraticosPage {...props} />);

  return props;
};

describe('ExerciciosPraticosPage', () => {
  it('sem exercícios, explica em vez de mostrar lista vazia', () => {
    renderPage();
    expect(screen.getByText(/ainda não tem exercícios práticos/i)).toBeInTheDocument();
  });

  it('mostra só os exercícios do curso da página', () => {
    renderPage({ exercises: [ex('e1'), ex('e2', { courseId: 'outro-curso' })] });

    expect(screen.getByText('Atividade e1')).toBeInTheDocument();
    expect(screen.queryByText('Atividade e2')).toBeNull();
  });

  it('exibe enunciado, instruções e quanto vale', () => {
    renderPage({ exercises: [ex('e1', { maxPoints: 80 })] });

    expect(screen.getByText('Objetivo da atividade.')).toBeInTheDocument();
    expect(screen.getByText(/relatório de 500 palavras/i)).toBeInTheDocument();
    expect(screen.getByText('vale 80 pts')).toBeInTheDocument();
  });

  it('entrega aprovada mostra a nota e não oferece novo envio', () => {
    renderPage({
      exercises: [ex('e1')],
      submissions: [{
        id: 'sub-1', exerciseId: 'e1', userId: 'aluno-1', studentName: 'João',
        submissionText: 'minha resposta', submittedAt: '01/09/2026 10:00',
        status: 'approved', score: 95, feedback: 'Muito bom.', gradedBy: 'Prof. Ana',
      }],
    });

    expect(screen.getByText('Nota 95 de 100')).toBeInTheDocument();
    expect(screen.getByText(/Feedback de Prof\. Ana/)).toBeInTheDocument();
    expect(screen.getByText(/Atividade concluída e aprovada/i)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /enviar|reenviar/i })).toBeNull();
  });

  it('entrega devolvida para ajustes permite reenviar e já vem com o texto anterior', () => {
    renderPage({
      exercises: [ex('e1')],
      submissions: [{
        id: 'sub-1', exerciseId: 'e1', userId: 'aluno-1', studentName: 'João',
        submissionText: 'primeira versão', submittedAt: '01/09/2026 10:00',
        status: 'revision', feedback: 'Detalhe melhor o método.',
      }],
    });

    expect(screen.getByText('Ajustes solicitados')).toBeInTheDocument();
    // Reenviar é editar, não redigitar do zero.
    expect(screen.getByRole('textbox')).toHaveValue('primeira versão');
    expect(screen.getByRole('button', { name: /reenviar resposta/i })).toBeInTheDocument();
  });

  it('ignora entrega de outro aluno', () => {
    renderPage({
      exercises: [ex('e1')],
      submissions: [{
        id: 'sub-1', exerciseId: 'e1', userId: 'OUTRO-ALUNO', studentName: 'Maria',
        submissionText: 'resposta da Maria', submittedAt: '01/09/2026 10:00',
        status: 'approved', score: 100,
      }],
    });

    expect(screen.getByText('Não entregue')).toBeInTheDocument();
    expect(screen.queryByText('resposta da Maria')).toBeNull();
  });

  it('não envia resposta em branco', async () => {
    const onSubmit = vi.fn(async () => ({ ok: true }));
    renderPage({ exercises: [ex('e1')], onSubmit });

    await userEvent.click(screen.getByRole('button', { name: /enviar para correção/i }));

    expect(onSubmit).not.toHaveBeenCalled();
    expect(screen.getByText(/escreva sua resposta antes de enviar/i)).toBeInTheDocument();
  });

  it('envia o texto e avisa a pessoa quando o servidor confirma', async () => {
    const onSubmit = vi.fn(async () => ({ ok: true }));
    const notify = vi.fn();
    renderPage({ exercises: [ex('e1')], onSubmit, notify });

    await userEvent.type(screen.getByRole('textbox'), 'minha análise');
    await userEvent.click(screen.getByRole('button', { name: /enviar para correção/i }));

    expect(onSubmit).toHaveBeenCalledWith('e1', 'minha análise', undefined, undefined);
    expect(notify).toHaveBeenCalledWith(expect.stringMatching(/entrega registrada/i));
  });

  it('falha do servidor aparece no card e NÃO é anunciada como sucesso', async () => {
    // O defeito que motivou isto: a tela dizia "enviado com sucesso" sem olhar a
    // resposta, e com a flag desligada toda chamada voltava 404.
    const onSubmit = vi.fn(async () => ({ ok: false, error: 'Recurso indisponível nesta instalação.' }));
    const notify = vi.fn();
    renderPage({ exercises: [ex('e1')], onSubmit, notify });

    await userEvent.type(screen.getByRole('textbox'), 'minha análise');
    await userEvent.click(screen.getByRole('button', { name: /enviar para correção/i }));

    expect(screen.getByText('Recurso indisponível nesta instalação.')).toBeInTheDocument();
    expect(notify).not.toHaveBeenCalled();
  });

  it('prazo vencido é destacado; prazo ilegível é mostrado sem afirmar atraso', () => {
    renderPage({
      exercises: [
        ex('e1', { dueDate: '10/07/2020' }),
        ex('e2', { dueDate: 'quando der' }),
      ],
    });

    expect(screen.getByText(/^Atrasado há /)).toBeInTheDocument();
    expect(screen.getByText('Prazo: quando der')).toBeInTheDocument();
  });

  it('pontuação soma só o que já foi corrigido', () => {
    renderPage({
      exercises: [ex('e1'), ex('e2'), ex('e3')],
      submissions: [
        { id: 's1', exerciseId: 'e1', userId: 'aluno-1', studentName: 'J', submissionText: 'x', submittedAt: 'x', status: 'approved', score: 70 },
        { id: 's2', exerciseId: 'e2', userId: 'aluno-1', studentName: 'J', submissionText: 'x', submittedAt: 'x', status: 'pending' },
      ],
    });

    expect(screen.getByText('2 de 3')).toBeInTheDocument();
    // 70 de 100 (o corrigido), não 70 de 300: a fila do professor não deve
    // fazer a nota do aluno parecer pior do que é.
    expect(screen.getByText('70 de 100')).toBeInTheDocument();
  });

  it('sem a flag de upload, a entrega é só texto', () => {
    renderPage({ exercises: [ex('e1')], permiteAnexo: false });
    expect(screen.queryByText(/anexar documento/i)).toBeNull();
  });
});
