import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { LessonManagePage } from '../../src/components/instructor/LessonManagePage';
import { Lesson } from '../../src/types';

const aula: Lesson = {
  id: 'lesson-2-2',
  title: 'NodeJS Express: Levantamento de APIs',
  duration: '25 min',
  order: 2,
  content: '## Servidores RESTful\n\nAprenda a estruturar um servidor limpo.',
  videoUrl: '',
  documents: [
    { id: 'doc-1', title: 'Slides da Aula.pdf', type: 'pdf', url: 'https://exemplo.com/a.pdf', size: '2.4 MB' },
  ],
} as Lesson;

const montar = (over: Partial<React.ComponentProps<typeof LessonManagePage>> = {}) => {
  const props = {
    lesson: aula,
    courseTitle: 'Desenvolvimento Full-Stack',
    courseCategory: 'Desenvolvimento Web',
    totalLessons: 4,
    onBack: vi.fn(),
    onSaveHeader: vi.fn(),
    onSaveVideo: vi.fn(),
    onSaveContent: vi.fn(),
    onToggleOptional: vi.fn(),
    documentsSlot: <div data-testid="slot-docs">formulário de anexos</div>,
    ...over,
  };
  render(<LessonManagePage {...props} />);

  return props;
};

describe('LessonManagePage', () => {
  it('abre mostrando a aula como o aluno vê, sem nenhum campo em edição', () => {
    montar();

    expect(screen.getByText('NodeJS Express: Levantamento de APIs')).toBeInTheDocument();
    expect(screen.getByText('Servidores RESTful')).toBeInTheDocument();
    // O slot de anexos só aparece ao entrar em edição.
    expect(screen.queryByTestId('slot-docs')).not.toBeInTheDocument();
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
  });

  it('oferece um lápis para cada bloco editável da aula', () => {
    montar();

    expect(screen.getByRole('button', { name: /editar identificação da aula/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /editar vídeo da aula/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /editar material didático/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /editar material de apoio/i })).toBeInTheDocument();
  });

  it('salva a identificação com o que foi digitado', () => {
    const props = montar();
    fireEvent.click(screen.getByRole('button', { name: /editar identificação da aula/i }));

    fireEvent.change(screen.getByDisplayValue('NodeJS Express: Levantamento de APIs'), {
      target: { value: 'Título revisado' },
    });
    fireEvent.click(screen.getByRole('button', { name: /salvar identificação/i }));

    expect(props.onSaveHeader).toHaveBeenCalledWith('Título revisado', '25 min');
  });

  it('mostra o formulário de anexos só depois do lápis', () => {
    montar();
    expect(screen.queryByTestId('slot-docs')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /editar material de apoio/i }));

    expect(screen.getByTestId('slot-docs')).toBeInTheDocument();
  });

  it('mantém apenas um bloco aberto por vez', () => {
    montar();

    fireEvent.click(screen.getByRole('button', { name: /editar material de apoio/i }));
    expect(screen.getByTestId('slot-docs')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /editar identificação da aula/i }));

    // Abrir a identificação fecha o de anexos: o foco não se espalha pela página.
    expect(screen.queryByTestId('slot-docs')).not.toBeInTheDocument();
    expect(screen.getByDisplayValue('NodeJS Express: Levantamento de APIs')).toBeInTheDocument();
  });

  it('avisa que a aula é de leitura quando não há vídeo', () => {
    montar();

    expect(screen.getByText('Aula sem vídeo')).toBeInTheDocument();
  });

  it('lista os documentos anexados fora do modo de edição', () => {
    montar();

    expect(screen.getByText('Slides da Aula.pdf')).toBeInTheDocument();
    expect(screen.getByText('2.4 MB')).toBeInTheDocument();
  });

  it('volta ao currículo pelo botão de retorno', () => {
    const props = montar();
    fireEvent.click(screen.getByRole('button', { name: /voltar ao currículo/i }));

    expect(props.onBack).toHaveBeenCalled();
  });

  it('mostra aviso de aula sem material quando o conteúdo está vazio', () => {
    montar({ lesson: { ...aula, content: '' } as Lesson });

    expect(screen.getByText(/ainda não tem material escrito/i)).toBeInTheDocument();
  });
});
