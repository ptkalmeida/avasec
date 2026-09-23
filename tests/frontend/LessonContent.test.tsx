import { describe, it, expect } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
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

describe('LessonContent — mídia no corpo da aula', () => {
  it('renderiza a imagem em figure, com a legenda abaixo', () => {
    const { container } = renderContent('Figura 1 - o diagrama\n![Diagrama das classes](/uploads/x.png)');
    const figure = container.querySelector('figure');

    expect(figure).not.toBeNull();
    expect(figure?.querySelector('img')).not.toBeNull();
    expect(screen.getByText('Figura 1 - o diagrama').tagName).toBe('FIGCAPTION');

    // A legenda vem DEPOIS da imagem no documento — convenção de material didático.
    const filhos = Array.from(figure!.children);
    expect(filhos.findIndex((e) => e.querySelector('img') !== null))
      .toBeLessThan(filhos.findIndex((e) => e.tagName === 'FIGCAPTION'));
  });

  it('a descrição vai para o alt da imagem', () => {
    const { container } = renderContent('![Diagrama das classes](/uploads/x.png)');

    expect(container.querySelector('img')?.getAttribute('alt')).toBe('Diagrama das classes');
  });

  it('pede carregamento adiado e não vaza o referenciador', () => {
    const { container } = renderContent('![Foto](/uploads/x.png)');
    const img = container.querySelector('img')!;

    expect(img.getAttribute('loading')).toBe('lazy');
    expect(img.getAttribute('referrerpolicy')).toBe('no-referrer');
  });

  // Sem parênteses de propósito: com eles a linha nem chega a virar bloco de
  // imagem (a regex do parser a recusa antes), e o teste não exercitaria o safeUrl.
  it('endereço com esquema perigoso não vira imagem', () => {
    const { container } = renderContent('![Inofensiva](javascript:location=name)');

    expect(container.querySelector('img')).toBeNull();
    expect(screen.getByText('Imagem indisponível')).toBeInTheDocument();
  });

  it('quando a imagem falha ao carregar, o aluno lê a descrição no lugar', async () => {
    const { container } = renderContent('![Diagrama das classes](/uploads/sumiu.png)');

    fireEvent.error(container.querySelector('img')!);

    expect(container.querySelector('img')).toBeNull();
    expect(screen.getByText('Imagem indisponível')).toBeInTheDocument();
    expect(screen.getByText('Diagrama das classes')).toBeInTheDocument();
  });

  it('imagem sem descrição não inventa texto para o leitor de tela', () => {
    const { container } = renderContent('![](/uploads/x.png)');

    expect(container.querySelector('img')?.getAttribute('alt')).toBe('');
  });

  it('vídeo do YouTube no meio da aula abre no player único da plataforma', () => {
    const { container } = renderContent('@[Demonstração](https://youtu.be/dQw4w9WgXcQ)');

    expect(container.querySelector('iframe')).not.toBeNull();
    expect(container.querySelector('iframe')?.getAttribute('src')).toContain('dQw4w9WgXcQ');
  });

  it('link que não é vídeo mostra o aviso, não uma caixa vazia', () => {
    renderContent('@[Quebrado](https://exemplo.com/pagina)');

    expect(screen.getByText(/indisponível/i)).toBeInTheDocument();
  });

  it('a prévia escura usa a paleta escura na legenda', () => {
    render(
      <LessonContent
        blocks={parseLessonContent('Figura 1 - o diagrama\n![Diagrama](/uploads/x.png)').blocks}
        tone="dark"
      />
    );

    expect(screen.getByText('Figura 1 - o diagrama').className).toContain('text-teal-400');
  });
});
