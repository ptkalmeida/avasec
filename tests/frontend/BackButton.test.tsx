import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BackButton } from '../../src/components/BackButton';

describe('BackButton', () => {
  it('renderiza o texto informado e chama onClick ao ser clicado', async () => {
    const onClick = vi.fn();
    render(<BackButton text="Voltar aos cursos" onClick={onClick} />);

    const button = screen.getByRole('button', { name: /voltar aos cursos/i });
    expect(button).toBeInTheDocument();

    await userEvent.click(button);
    expect(onClick).toHaveBeenCalledTimes(1);
  });
});
