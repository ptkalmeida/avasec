import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { useState } from 'react';
import { LessonContentEditor } from '../../src/components/instructor/LessonContentEditor';

/** Envolve o editor num estado real, como o formulário do instrutor faz. */
const Harness: React.FC<{ inicial?: string }> = ({ inicial = '' }) => {
  const [valor, setValor] = useState(inicial);

  return (
    <>
      <LessonContentEditor value={valor} onChange={setValor} />
      <output data-testid="valor">{valor}</output>
    </>
  );
};

const area = () => screen.getByRole('textbox') as HTMLTextAreaElement;
const valor = () => screen.getByTestId('valor').textContent;

describe('LessonContentEditor', () => {
  it('insere seção numerada na área vazia', () => {
    render(<Harness />);
    fireEvent.click(screen.getByRole('button', { name: /^seção$/i }));

    expect(valor()).toBe('## Título da seção');
  });

  it('abre parágrafo novo em vez de colar na linha escrita', () => {
    render(<Harness inicial="Texto que já estava aqui." />);
    const t = area();
    t.setSelectionRange(t.value.length, t.value.length);

    fireEvent.click(screen.getByRole('button', { name: /^seção$/i }));

    expect(valor()).toBe('Texto que já estava aqui.\n\n## Título da seção');
  });

  it('aplica negrito na seleção sem perder o texto', () => {
    render(<Harness inicial="uma palavra importante" />);
    const t = area();
    t.setSelectionRange(4, 11); // "palavra"

    fireEvent.click(screen.getByRole('button', { name: /negrito/i }));

    expect(valor()).toBe('uma **palavra** importante');
  });

  it('marca a linha selecionada como subtítulo', () => {
    render(<Harness inicial="Assunto da vez" />);
    const t = area();
    t.setSelectionRange(0, 7);

    fireEvent.click(screen.getByRole('button', { name: /subtítulo/i }));

    expect(valor()).toBe('### Assunto da vez');
  });

  it('insere bloco de código com a cerca da linguagem escolhida', () => {
    render(<Harness />);
    fireEvent.change(screen.getByRole('combobox'), { target: { value: 'php' } });
    fireEvent.click(screen.getByRole('button', { name: /bloco de código/i }));

    expect(valor()).toContain('```php');
    expect(valor()).toContain('// escreva o código aqui');
    // A cerca de fechamento também entra, senão o bloco engoliria o resto da aula.
    expect((valor() ?? '').match(/```/g)).toHaveLength(2);
  });

  it('conta as seções escritas para orientar quem edita', () => {
    render(<Harness inicial={'## Uma\n\ntexto\n\n## Outra\n\n### Sub'} />);

    // Só ## conta como seção; ### é subtítulo dentro dela.
    expect(screen.getByText('2 seções')).toBeInTheDocument();
  });

  it('mostra a prévia do que o aluno verá', async () => {
    render(<Harness inicial={'## Herança em Java\n\nTexto da aula.'} />);

    expect(await screen.findByText('Herança em Java', {}, { timeout: 15000 })).toBeInTheDocument();
    expect(screen.getByText('Texto da aula.')).toBeInTheDocument();
  });

  it('explica o formato em linguagem simples quando pedido', () => {
    render(<Harness />);
    fireEvent.click(screen.getByRole('button', { name: /como formatar/i }));

    expect(screen.getByText(/divide a aula em partes numeradas/i)).toBeInTheDocument();
  });

  it('não perde o conteúdo existente ao inserir marcação', () => {
    const original = '## Seção existente\n\nParágrafo preservado.';
    render(<Harness inicial={original} />);
    const t = area();
    t.setSelectionRange(t.value.length, t.value.length);

    fireEvent.click(screen.getByRole('button', { name: /^lista$/i }));

    expect(valor()).toContain(original);
    expect(valor()).toContain('- Item da lista');
  });
});

// requestAnimationFrame é usado para devolver o cursor ao textarea; no jsdom ele
// existe, mas garantimos que a ausência não quebra a inserção.
describe('LessonContentEditor sem requestAnimationFrame', () => {
  it('ainda grava o texto inserido', () => {
    const original = window.requestAnimationFrame;
    window.requestAnimationFrame = undefined as unknown as typeof window.requestAnimationFrame;
    const spy = vi.fn();

    try {
      render(<LessonContentEditor value="" onChange={spy} />);
      expect(() => fireEvent.click(screen.getByRole('button', { name: /^seção$/i }))).not.toThrow();
      expect(spy).toHaveBeenCalledWith('## Título da seção');
    } finally {
      window.requestAnimationFrame = original;
    }
  });
});
