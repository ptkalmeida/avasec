import { describe, it, expect } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { useState } from 'react';
import { LessonContentEditor } from '../../src/components/instructor/LessonContentEditor';

const CONTEUDO = [
  '## Do Rabisco ao Esqueleto Digital',
  '',
  'Os wireframes servem para validar a estrutura do layout.',
  '',
  '### Tipos de Wireframe',
  '',
  '1. Baixa Fidelidade',
  '2. Média Fidelidade',
  '',
  'Código 1: exemplo:',
  '```java',
  'int a = 1;',
  '```',
].join('\n');

/** Envolve o editor num estado real, como os formulários do instrutor fazem. */
const Harness: React.FC<{ inicial?: string }> = ({ inicial = CONTEUDO }) => {
  const [valor, setValor] = useState(inicial);

  return (
    <>
      <LessonContentEditor value={valor} onChange={setValor} />
      <output data-testid="valor">{valor}</output>
    </>
  );
};

const valor = () => screen.getByTestId('valor').textContent ?? '';

describe('LessonContentEditor', () => {
  it('mostra a aula como o aluno vê, num card só, sem área de marcação', () => {
    render(<Harness />);

    expect(screen.getByText('Do Rabisco ao Esqueleto Digital')).toBeInTheDocument();
    expect(screen.getByText('Tipos de Wireframe')).toBeInTheDocument();
    // Nenhum campo aberto: a edição começa pelo lápis do trecho.
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
  });

  it('dá um lápis e uma lixeira para cada trecho', () => {
    render(<Harness />);

    expect(screen.getByRole('button', { name: 'Editar seção' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Editar subtítulo' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Editar parágrafo' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Editar lista numerada' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Editar bloco de código' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Remover seção' })).toBeInTheDocument();
  });

  it('edita a seção por campo de texto simples, sem expor marcação', () => {
    render(<Harness />);
    fireEvent.click(screen.getByRole('button', { name: 'Editar seção' }));

    const campo = screen.getByDisplayValue('Do Rabisco ao Esqueleto Digital');
    // O campo traz o texto puro: nada de "##" para o gestor decifrar.
    expect(campo).toBeInTheDocument();

    fireEvent.change(campo, { target: { value: 'Título revisado' } });
    fireEvent.click(screen.getByRole('button', { name: /aplicar/i }));

    expect(valor()).toContain('## Título revisado');
    expect(valor()).not.toContain('Do Rabisco');
  });

  it('não toca no resto do conteúdo ao salvar um trecho', () => {
    render(<Harness />);
    fireEvent.click(screen.getByRole('button', { name: 'Editar parágrafo' }));
    fireEvent.change(screen.getByDisplayValue(/wireframes servem/), { target: { value: 'Texto novo.' } });
    fireEvent.click(screen.getByRole('button', { name: /aplicar/i }));

    // A indentação do código e as demais linhas permanecem intactas.
    expect(valor()).toContain('```java\nint a = 1;\n```');
    expect(valor()).toContain('## Do Rabisco ao Esqueleto Digital');
    expect(valor()).toContain('### Tipos de Wireframe');
    expect(valor()).toContain('1. Baixa Fidelidade');
  });

  it('edita lista com um item por linha, sem pedir numeração', () => {
    render(<Harness />);
    fireEvent.click(screen.getByRole('button', { name: 'Editar lista numerada' }));

    // getByDisplayValue normaliza espaços; aqui as quebras de linha são o ponto,
    // então pegamos o textarea e checamos o valor cru.
    const campo = screen.getByRole('textbox') as HTMLTextAreaElement;
    expect(campo.value).toBe('Baixa Fidelidade\nMédia Fidelidade');

    fireEvent.change(campo, { target: { value: 'Um\nDois\nTrês' } });
    fireEvent.click(screen.getByRole('button', { name: /aplicar/i }));

    // A numeração é reconstruída pelo editor.
    expect(valor()).toContain('1. Um\n2. Dois\n3. Três');
  });

  it('edita código com linguagem e legenda em campos próprios', () => {
    render(<Harness />);
    fireEvent.click(screen.getByRole('button', { name: 'Editar bloco de código' }));

    expect(screen.getByDisplayValue('Código 1: exemplo:')).toBeInTheDocument();
    fireEvent.change(screen.getByDisplayValue('int a = 1;'), { target: { value: 'int b = 2;' } });
    fireEvent.change(screen.getByRole('combobox'), { target: { value: 'php' } });
    fireEvent.click(screen.getByRole('button', { name: /aplicar/i }));

    expect(valor()).toContain('```php\nint b = 2;\n```');
    expect(valor()).toContain('Código 1: exemplo:');
  });

  it('cancelar edição não altera o conteúdo', () => {
    render(<Harness />);
    fireEvent.click(screen.getByRole('button', { name: 'Editar seção' }));
    fireEvent.change(screen.getByDisplayValue('Do Rabisco ao Esqueleto Digital'), { target: { value: 'descartar' } });
    fireEvent.click(screen.getByRole('button', { name: /cancelar/i }));

    expect(valor()).toBe(CONTEUDO);
  });

  it('remove o trecho pela lixeira', () => {
    render(<Harness />);
    fireEvent.click(screen.getByRole('button', { name: 'Remover subtítulo' }));

    expect(valor()).not.toContain('### Tipos de Wireframe');
    expect(valor()).toContain('## Do Rabisco ao Esqueleto Digital');
  });

  it('insere trecho novo no ponto escolhido', () => {
    render(<Harness />);

    // O primeiro "Adicionar" insere antes de tudo.
    fireEvent.click(screen.getAllByRole('button', { name: /adicionar bloco aqui/i })[0]);
    fireEvent.click(screen.getByRole('button', { name: /^parágrafo$/i }));

    expect(valor()).toContain('Escreva o parágrafo aqui.');
    expect(valor()).toContain('## Do Rabisco ao Esqueleto Digital');
  });

  it('não oferece criar bloco de código: quem edita não é programador', () => {
    render(<Harness />);
    fireEvent.click(screen.getAllByRole('button', { name: /adicionar bloco aqui/i })[0]);

    expect(screen.getByRole('button', { name: /^parágrafo$/i })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^código$/i })).not.toBeInTheDocument();
  });

  it('mas continua editando bloco de código que já existe na aula', () => {
    // Tirar a edição junto tornaria o código das 13 aulas atuais impossível de corrigir.
    render(<Harness />);

    expect(screen.getByRole('button', { name: 'Editar bloco de código' })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Editar bloco de código' }));
    expect(screen.getByDisplayValue('int a = 1;')).toBeInTheDocument();
  });

  it('oferece os tipos de trecho quando a aula está vazia', () => {
    render(<Harness inicial="" />);

    expect(screen.getByText(/ainda não tem material escrito/i)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /^seção$/i }));
    expect(valor()).toBe('## Título da seção');
  });

  it('conta seções e trechos para orientar quem edita', () => {
    render(<Harness />);

    expect(screen.getByText('1 seções')).toBeInTheDocument();
    expect(screen.getByText('5 trechos')).toBeInTheDocument();
    expect(screen.getByText('contém bloco de código')).toBeInTheDocument();
  });

  it('explica o funcionamento em linguagem simples quando pedido', () => {
    render(<Harness />);
    fireEvent.click(screen.getByRole('button', { name: /como funciona/i }));

    expect(screen.getByText(/entre dois trechos, para inserir/i)).toBeInTheDocument();
  });
});
