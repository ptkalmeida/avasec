import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { LessonContent } from '../../src/components/student/LessonContent';
import { parseLessonContent } from '../../src/utils/lessonContent';

/** Conteúdo no formato que o instrutor escreve, com código e legenda. */
const CONTEUDO = `## A hierarquia de herança em Java

Herança é a transmissão de características aos descendentes.

### Hierarquia de classe

Código 1: Declaração de classe:
\`\`\`java
public class Pessoa {
    protected String nome;
}
\`\`\`

1. Primeiro passo
2. Segundo passo

- Item com marcador
`;

const renderContent = (markup: string) =>
  render(<LessonContent blocks={parseLessonContent(markup).blocks} />);

describe('LessonContent', () => {
  it('renderiza seção numerada, subtítulo e parágrafo', () => {
    renderContent(CONTEUDO);

    expect(screen.getByText('A hierarquia de herança em Java')).toBeInTheDocument();
    expect(screen.getByText('1.')).toBeInTheDocument();
    expect(screen.getByText('Hierarquia de classe')).toBeInTheDocument();
    expect(screen.getByText(/transmissão de características/)).toBeInTheDocument();
  });

  // O bloco de código é carregado sob demanda (React.lazy), para o realce de
  // sintaxe não pesar nas aulas sem código — daí o findBy em vez de getBy.
  it('renderiza o bloco de código com rótulo da linguagem, legenda e botão copiar', async () => {
    renderContent(CONTEUDO);

    // Timeout folgado: a PRIMEIRA resolução do React.lazy carrega o Prism
    // inteiro e passa do 1s padrão do findBy.
    expect(await screen.findByText('Java', {}, { timeout: 15000 })).toBeInTheDocument();
    expect(screen.getByText('Código 1: Declaração de classe:')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /copiar/i })).toBeInTheDocument();
  });

  it('oferece alternar o fundo do bloco de código', async () => {
    renderContent(CONTEUDO);
    expect(
      await screen.findByRole('button', { name: /usar fundo claro/i }, { timeout: 15000 })
    ).toBeInTheDocument();
  });

  it('ancora os títulos com id, para o índice saltar até eles', () => {
    const { container } = renderContent(CONTEUDO);

    expect(container.querySelector('#a-hierarquia-de-heranca-em-java')).not.toBeNull();
    expect(container.querySelector('#hierarquia-de-classe')).not.toBeNull();
  });

  it('renderiza as duas formas de lista', () => {
    const { container } = renderContent(CONTEUDO);

    expect(container.querySelector('ol')).not.toBeNull();
    expect(container.querySelector('ul')).not.toBeNull();
    expect(screen.getByText('Segundo passo')).toBeInTheDocument();
    expect(screen.getByText('Item com marcador')).toBeInTheDocument();
  });

  it('aplica negrito sem injetar HTML', () => {
    const { container } = renderContent('Isto é **muito** importante. <script>alert(1)</script>');

    expect(container.querySelector('strong')?.textContent).toBe('muito');
    // A tag literal aparece como TEXTO, nunca como elemento.
    expect(container.querySelector('script')).toBeNull();
    expect(container.textContent).toContain('<script>alert(1)</script>');
  });
});
