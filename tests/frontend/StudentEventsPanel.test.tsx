import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useLMS } from '../../src/context/LMSContext';
import { StudentEventsPanel } from '../../src/components/student/StudentEventsPanel';

vi.mock('../../src/context/LMSContext', () => ({ useLMS: vi.fn() }));

const mockedUseLMS = vi.mocked(useLMS);

describe('StudentEventsPanel', () => {
  it('lista os eventos/webinars vindos do contexto', () => {
    mockedUseLMS.mockReturnValue({
      webinarEvents: [
        { id: 'w1', title: 'Aula Magna de Abertura', description: 'Evento de boas-vindas', date: '10/03', time: '19h', image: '/img.jpg' },
      ],
    } as any);

    render(<StudentEventsPanel onBack={() => {}} />);

    expect(screen.getByText('Aula Magna de Abertura')).toBeInTheDocument();
    expect(screen.getByText('Realizar Inscrição')).toBeInTheDocument();
  });

  it('chama onBack ao clicar em voltar', async () => {
    mockedUseLMS.mockReturnValue({ webinarEvents: [] } as any);
    const onBack = vi.fn();

    render(<StudentEventsPanel onBack={onBack} />);
    await userEvent.click(screen.getByRole('button', { name: /voltar ao meu painel de estudos/i }));

    expect(onBack).toHaveBeenCalledTimes(1);
  });
});
