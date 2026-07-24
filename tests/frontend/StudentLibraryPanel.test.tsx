import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useLMS } from '../../src/context/LMSContext';
import { StudentLibraryPanel } from '../../src/components/student/StudentLibraryPanel';

vi.mock('../../src/context/LMSContext', () => ({ useLMS: vi.fn() }));

const mockedUseLMS = vi.mocked(useLMS);

describe('StudentLibraryPanel', () => {
  it('lista os itens da biblioteca vindos do contexto', () => {
    mockedUseLMS.mockReturnValue({
      libraryItems: [
        { id: 'l1', title: 'Guia de Redação', description: 'Material de apoio', category: 'Português', type: 'pdf', url: '/f1.pdf' },
        { id: 'l2', title: 'Portal de Artes', description: 'Site externo', category: 'Artes', type: 'link', url: 'https://example.com' },
      ],
    } as any);

    render(<StudentLibraryPanel onBack={() => {}} />);

    expect(screen.getByText('Guia de Redação')).toBeInTheDocument();
    expect(screen.getByText('Baixar Arquivo')).toBeInTheDocument();
    expect(screen.getByText('Acessar Link')).toBeInTheDocument();
  });

  it('chama onBack ao clicar em voltar', async () => {
    mockedUseLMS.mockReturnValue({ libraryItems: [] } as any);
    const onBack = vi.fn();

    render(<StudentLibraryPanel onBack={onBack} />);
    await userEvent.click(screen.getByRole('button', { name: /voltar ao meu painel de estudos/i }));

    expect(onBack).toHaveBeenCalledTimes(1);
  });
});
