import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';
import { EncerrarMatriculaPanel } from '../../src/components/student/EncerrarMatriculaPanel';
import { Course } from '../../src/types';

const curso = (id: string, title: string): Course => ({
  id,
  title,
  category: 'Design Digital',
  description: 'd',
  instructorId: 'prof-1',
  instructorName: 'Prof. Ana',
  lessons: [],
} as unknown as Course);

const renderPanel = (
  over: Partial<React.ComponentProps<typeof EncerrarMatriculaPanel>> = {}
) => {
  const props = {
    matriculas: [curso('course-1', 'UX/UI Design')],
    diasSemPenalidade: null,
    onDrop: vi.fn(async () => ({ ok: true, penaltyApplied: false })),
    notify: vi.fn(),
    ...over,
  } as React.ComponentProps<typeof EncerrarMatriculaPanel>;
  render(<EncerrarMatriculaPanel {...props} />);

  return props;
};

describe('EncerrarMatriculaPanel', () => {
  it('sem matrícula ativa, explica que curso concluído não se encerra', () => {
    renderPanel({ matriculas: [] });

    expect(screen.getByText(/não tem matrícula ativa/i)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /solicitar saída/i })).toBeNull();
  });

  it('lista cada matrícula ativa com uma saída própria', () => {
    renderPanel({
      matriculas: [curso('course-1', 'UX/UI Design'), curso('course-2', 'Gestão Cultural')],
    });

    expect(screen.getByText('UX/UI Design')).toBeInTheDocument();
    expect(screen.getByText('Gestão Cultural')).toBeInTheDocument();
    expect(screen.getAllByRole('button', { name: /solicitar saída/i })).toHaveLength(2);
  });

  it('não encerra no primeiro clique — pede confirmação', async () => {
    const props = renderPanel();

    await userEvent.click(screen.getByRole('button', { name: /solicitar saída/i }));

    // Ação irreversível: o primeiro clique só revela a confirmação.
    expect(props.onDrop).not.toHaveBeenCalled();
    expect(screen.getByText(/Encerrar sua matrícula em UX\/UI Design\?/)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /confirmar saída/i })).toBeInTheDocument();
  });

  it('"manter matrícula" desiste sem chamar o servidor', async () => {
    const props = renderPanel();

    await userEvent.click(screen.getByRole('button', { name: /solicitar saída/i }));
    await userEvent.click(screen.getByRole('button', { name: /manter matrícula/i }));

    expect(props.onDrop).not.toHaveBeenCalled();
    expect(screen.queryByRole('button', { name: /confirmar saída/i })).toBeNull();
  });

  it('confirmar encerra o curso certo e avisa na tela', async () => {
    const props = renderPanel({
      matriculas: [curso('course-1', 'UX/UI Design'), curso('course-2', 'Gestão Cultural')],
    });

    await userEvent.click(screen.getAllByRole('button', { name: /solicitar saída/i })[1]);
    await userEvent.click(screen.getByRole('button', { name: /confirmar saída/i }));

    expect(props.onDrop).toHaveBeenCalledWith('course-2');
    // O Perfil não tem toast: a confirmação precisa aparecer no próprio painel.
    expect(screen.getByText('Matrícula em Gestão Cultural encerrada.')).toBeInTheDocument();
    expect(props.notify).toHaveBeenCalledWith('Matrícula em Gestão Cultural encerrada.');
  });

  it('penalidade aplicada pelo servidor é dita ao aluno', async () => {
    renderPanel({ onDrop: vi.fn(async () => ({ ok: true, penaltyApplied: true })) });

    await userEvent.click(screen.getByRole('button', { name: /solicitar saída/i }));
    await userEvent.click(screen.getByRole('button', { name: /confirmar saída/i }));

    expect(screen.getByText(/restrição temporária para nova matrícula/i)).toBeInTheDocument();
  });

  it('falha do servidor mantém a confirmação aberta e mostra o motivo', async () => {
    const props = renderPanel({
      onDrop: vi.fn(async () => ({ ok: false, penaltyApplied: false, error: 'Matrícula não encontrada.' })),
    });

    await userEvent.click(screen.getByRole('button', { name: /solicitar saída/i }));
    await userEvent.click(screen.getByRole('button', { name: /confirmar saída/i }));

    expect(screen.getByText('Matrícula não encontrada.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /confirmar saída/i })).toBeInTheDocument();
    expect(props.notify).not.toHaveBeenCalled();
  });

  it('só menciona penalidade quando a flag informa o prazo', () => {
    renderPanel({ diasSemPenalidade: null });
    expect(screen.queryByText(/restrição temporária para nova inscrição/i)).toBeNull();
  });

  it('com a flag ativa, informa o prazo antes de a pessoa decidir', () => {
    renderPanel({ diasSemPenalidade: 5 });
    expect(screen.getByText(/após 5 dias de matrícula/i)).toBeInTheDocument();
  });
});
