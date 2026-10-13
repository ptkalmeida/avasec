import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { SuportePedagogico } from '../../src/components/student/SuportePedagogico';

const base = {
  instrutor: 'Gestor de Conteúdos',
  mensagem: '',
  onMensagemChange: () => {},
  onEnviar: () => {},
  enviada: false,
} as const;

describe('SuportePedagogico', () => {
  it('mostra o canal, o instrutor e o formulário nas duas variantes', () => {
    const { unmount } = render(<SuportePedagogico {...base} variante="lateral" />);
    expect(screen.getByText('Suporte Pedagógico')).toBeInTheDocument();
    expect(screen.getByText('Prof. Gestor de Conteúdos')).toBeInTheDocument();
    expect(screen.getByLabelText('Sua dúvida ou relato sobre esta aula')).toBeInTheDocument();
    unmount();

    render(<SuportePedagogico {...base} variante="corpo" />);
    expect(screen.getByText('Suporte Pedagógico')).toBeInTheDocument();
    expect(screen.getByText('Prof. Gestor de Conteúdos')).toBeInTheDocument();
  });

  /*
   * Esta é a razão de o componente existir separado, e não uma preferência de
   * layout. A versão anterior, em aba, afirmava duas coisas que o sistema não
   * sabe: um selo "ONLINE" vindo do `localStorage` do navegador do próprio aluno
   * (verde por padrão, para todo mundo, sempre) e um "Tempo de resposta
   * esperado: < 2 horas" escrito no código. Levar o cartão para a barra lateral
   * deixaria as duas à vista o tempo inteiro. Se alguém as trouxer de volta, é
   * aqui que o teste quebra.
   */
  it.each(['lateral', 'corpo'] as const)(
    'não afirma presença nem prazo de resposta (%s)',
    (variante) => {
      render(<SuportePedagogico {...base} variante={variante} />);

      expect(screen.queryByText(/ONLINE/i)).not.toBeInTheDocument();
      expect(screen.queryByText(/OFFLINE/i)).not.toBeInTheDocument();
      expect(screen.queryByText(/tempo de resposta/i)).not.toBeInTheDocument();
      expect(screen.queryByText(/2 horas/i)).not.toBeInTheDocument();
    },
  );

  it('só libera o envio quando há texto, e não envia espaço em branco', async () => {
    const onEnviar = vi.fn();
    const { rerender } = render(
      <SuportePedagogico {...base} variante="lateral" onEnviar={onEnviar} />,
    );

    const botao = screen.getByRole('button', { name: /enviar mensagem/i });
    expect(botao).toBeDisabled();

    rerender(
      <SuportePedagogico {...base} variante="lateral" mensagem="   " onEnviar={onEnviar} />,
    );
    expect(screen.getByRole('button', { name: /enviar mensagem/i })).toBeDisabled();

    rerender(
      <SuportePedagogico {...base} variante="lateral" mensagem="Dúvida real" onEnviar={onEnviar} />,
    );
    await userEvent.click(screen.getByRole('button', { name: /enviar mensagem/i }));
    expect(onEnviar).toHaveBeenCalledTimes(1);
  });

  it('confirma o envio apenas quando a gravação foi aceita', () => {
    const { rerender } = render(<SuportePedagogico {...base} variante="lateral" />);
    expect(screen.queryByText('Mensagem enviada!')).not.toBeInTheDocument();

    rerender(<SuportePedagogico {...base} variante="lateral" enviada />);
    expect(screen.getByText('Mensagem enviada!')).toBeInTheDocument();
  });

  it('repassa o que o aluno digita sem guardar estado próprio', async () => {
    const onMensagemChange = vi.fn();
    render(
      <SuportePedagogico {...base} variante="corpo" onMensagemChange={onMensagemChange} />,
    );

    await userEvent.type(screen.getByLabelText('Sua dúvida ou relato sobre esta aula'), 'oi');

    // Duas teclas, duas chamadas: o valor vive no pai, que é o mesmo para a aba
    // e para o cartão. É o que impede as duas versões de divergirem.
    expect(onMensagemChange).toHaveBeenCalledTimes(2);
  });
});
