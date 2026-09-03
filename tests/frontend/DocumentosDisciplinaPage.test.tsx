import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';
import {
  DocumentosDisciplinaPage,
  problemaNoDocumento,
  paraDocumento,
  tipoPorExtensao,
  tamanhoLegivel,
  RASCUNHO_VAZIO,
} from '../../src/components/instructor/DocumentosDisciplinaPage';
import { Lesson, LessonDocument } from '../../src/types';

const doc = (id: string, over: Partial<LessonDocument> = {}): LessonDocument => ({
  id,
  title: `Documento ${id}`,
  type: 'pdf',
  url: 'https://exemplo.org/a.pdf',
  ...over,
});

const aula = (id: string, over: Partial<Lesson> = {}): Lesson =>
  ({
    id,
    courseId: 'c1',
    title: `Aula ${id}`,
    order: 1,
    documents: [],
    ...over,
  } as unknown as Lesson);

const renderPage = (over: Partial<React.ComponentProps<typeof DocumentosDisciplinaPage>> = {}) => {
  const props = {
    courseTitle: 'UX/UI Design',
    lessons: [aula('a1')],
    onSave: vi.fn(async () => ({ ok: true })),
    onUpload: vi.fn(async () => ({ ok: true, url: '/uploads/x.pdf' })),
    confirmar: vi.fn(() => true),
    notify: vi.fn(),
    onBack: vi.fn(),
    ...over,
  } as React.ComponentProps<typeof DocumentosDisciplinaPage>;
  const utils = render(<DocumentosDisciplinaPage {...props} />);

  return { ...utils, props };
};

describe('validação do documento', () => {
  const base = { ...RASCUNHO_VAZIO, title: 'Slides', url: 'https://exemplo.org/a.pdf' };

  it('exige título e endereço', () => {
    expect(problemaNoDocumento(RASCUNHO_VAZIO)).toMatch(/título/i);
    expect(problemaNoDocumento({ ...RASCUNHO_VAZIO, title: 'Slides' })).toMatch(/link ou envie/i);
  });

  it('recusa endereço que não é http(s) nem arquivo enviado', () => {
    // O formulário antigo aceitava texto livre; um `javascript:` viraria link
    // clicável na tela do aluno.
    expect(problemaNoDocumento({ ...base, url: 'javascript:alert(1)' })).toMatch(/http/i);
    expect(problemaNoDocumento({ ...base, url: 'apostila da aula' })).toMatch(/http/i);
  });

  it('recusa esquema com espaço no meio, que o navegador executa', () => {
    expect(problemaNoDocumento({ ...base, url: 'java\tscript:alert(1)' })).toMatch(/http/i);
  });

  it('aceita link https e caminho de upload interno', () => {
    expect(problemaNoDocumento(base)).toBeNull();
    expect(problemaNoDocumento({ ...base, url: '/uploads/arquivo.pdf' })).toBeNull();
  });
});

describe('conversão e metadados', () => {
  it('não grava size quando não há tamanho', () => {
    const d = paraDocumento({ ...RASCUNHO_VAZIO, title: 'X', url: 'https://a.org/b', size: '  ' });
    expect(d.size).toBeUndefined();
    expect(d.title).toBe('X');
  });

  it('deduz o tipo pela extensão do arquivo', () => {
    expect(tipoPorExtensao('slides.pdf')).toBe('pdf');
    expect(tipoPorExtensao('texto.DOCX')).toBe('doc');
    expect(tipoPorExtensao('planilha.xlsx')).toBe('outro');
    expect(tipoPorExtensao('semextensao')).toBe('outro');
  });

  it('tamanho vem dos bytes reais, não de um valor fixo', () => {
    // O formulário antigo gravava "1.2 MB" para qualquer anexo, então o aluno
    // lia um tamanho que não era o do arquivo.
    expect(tamanhoLegivel(512)).toBe('512 B');
    expect(tamanhoLegivel(2048)).toBe('2 KB');
    expect(tamanhoLegivel(3 * 1024 * 1024)).toBe('3.0 MB');
    expect(tamanhoLegivel(0)).toBe('');
    expect(tamanhoLegivel(Number.NaN)).toBe('');
  });
});

describe('DocumentosDisciplinaPage', () => {
  it('é página, não modal', () => {
    const { container } = renderPage();
    expect(container.querySelector('.fixed')).toBeNull();
  });

  it('mostra a disciplina e conta os documentos de todas as aulas', () => {
    renderPage({
      lessons: [
        aula('a1', { documents: [doc('d1'), doc('d2')] }),
        aula('a2', { order: 2, documents: [doc('d3')] }),
      ],
    });

    expect(screen.getByText('UX/UI Design')).toBeInTheDocument();
    expect(screen.getByText('3')).toBeInTheDocument(); // total de documentos
  });

  it('destaca quantas aulas estão sem material', () => {
    // Era a informação que a gestão por aula não dava: só abrindo uma por uma.
    renderPage({
      lessons: [aula('a1', { documents: [doc('d1')] }), aula('a2', { order: 2 }), aula('a3', { order: 3 })],
    });

    // O resumo diz QUANTAS aulas estão sem material — o número acionável — e
    // cada aula vazia carrega o próprio selo.
    const resumo = screen.getByText('Aulas sem material').closest('div');
    expect(resumo?.textContent).toContain('2');
    expect(screen.getAllByText('Sem material')).toHaveLength(2);
  });

  it('lista as aulas na ordem em que o aluno as recebe', () => {
    renderPage({
      lessons: [aula('terceira', { order: 3 }), aula('primeira', { order: 1 }), aula('segunda', { order: 2 })],
    });

    const titulos = screen.getAllByText(/^Aula (primeira|segunda|terceira)$/).map((e) => e.textContent);
    expect(titulos).toEqual(['Aula primeira', 'Aula segunda', 'Aula terceira']);
  });

  it('disciplina sem aula explica em vez de mostrar lista vazia', () => {
    renderPage({ lessons: [] });
    expect(screen.getByText(/ainda não tem aulas/i)).toBeInTheDocument();
  });

  it('anexa um documento por link à aula escolhida', async () => {
    const { props } = renderPage({ lessons: [aula('a1', { documents: [doc('ja-existe')] })] });

    await userEvent.click(screen.getByRole('button', { name: /anexar/i }));
    await userEvent.type(screen.getByLabelText(/título do documento/i), 'Slides da aula');
    await userEvent.type(screen.getByLabelText(/endereço/i), 'https://exemplo.org/slides.pdf');
    await userEvent.click(screen.getByRole('button', { name: /anexar documento/i }));

    // Grava a lista COMPLETA: o documento que já existia tem de continuar.
    expect(props.onSave).toHaveBeenCalledWith('a1', [
      expect.objectContaining({ id: 'ja-existe' }),
      expect.objectContaining({ title: 'Slides da aula', url: 'https://exemplo.org/slides.pdf' }),
    ]);
    expect(props.notify).toHaveBeenCalledWith(expect.stringContaining('Aula a1'));
  });

  it('não anexa documento inválido e diz o motivo', async () => {
    const { props } = renderPage();

    await userEvent.click(screen.getByRole('button', { name: /anexar/i }));
    await userEvent.type(screen.getByLabelText(/título do documento/i), 'Apostila');
    await userEvent.type(screen.getByLabelText(/endereço/i), 'javascript:alert(1)');
    await userEvent.click(screen.getByRole('button', { name: /anexar documento/i }));

    expect(screen.getByRole('alert')).toHaveTextContent(/http/i);
    expect(props.onSave).not.toHaveBeenCalled();
  });

  it('falha do servidor aparece e o rascunho não é perdido', async () => {
    const onSave = vi.fn(async () => ({ ok: false, error: 'Você não leciona esta disciplina.' }));
    const { props } = renderPage({ onSave });

    await userEvent.click(screen.getByRole('button', { name: /anexar/i }));
    await userEvent.type(screen.getByLabelText(/título do documento/i), 'Apostila');
    await userEvent.type(screen.getByLabelText(/endereço/i), 'https://exemplo.org/a.pdf');
    await userEvent.click(screen.getByRole('button', { name: /anexar documento/i }));

    expect(screen.getByRole('alert')).toHaveTextContent('Você não leciona esta disciplina.');
    expect(screen.getByLabelText(/título do documento/i)).toHaveValue('Apostila');
    expect(props.notify).not.toHaveBeenCalled();
  });

  it('desvincular pede confirmação e grava a lista sem aquele documento', async () => {
    const { props } = renderPage({ lessons: [aula('a1', { documents: [doc('d1'), doc('d2')] })] });

    await userEvent.click(screen.getByRole('button', { name: /desvincular documento d1/i }));

    expect(props.confirmar).toHaveBeenCalledWith(expect.stringContaining('Documento d1'));
    expect(props.onSave).toHaveBeenCalledWith('a1', [expect.objectContaining({ id: 'd2' })]);
  });

  it('recusar a confirmação não desvincula nada', async () => {
    const { props } = renderPage({
      lessons: [aula('a1', { documents: [doc('d1')] })],
      confirmar: vi.fn(() => false),
    });

    await userEvent.click(screen.getByRole('button', { name: /desvincular documento d1/i }));
    expect(props.onSave).not.toHaveBeenCalled();
  });

  it('o link do documento sai do sanitizador, não cru do banco', () => {
    // Documento gravado antes desta validação pode ter URL insegura; o href não
    // pode ser emitido nesse caso.
    const { container } = renderPage({
      lessons: [aula('a1', { documents: [doc('d1', { url: 'javascript:alert(1)' })] })],
    });

    const link = container.querySelector('a[title="Abrir o documento"]');
    expect(link).not.toBeNull();
    expect(link?.getAttribute('href')).toBeNull();
  });

  it('voltar sai da página', async () => {
    const { props } = renderPage();
    await userEvent.click(screen.getByRole('button', { name: /voltar à gestão/i }));
    expect(props.onBack).toHaveBeenCalled();
  });
});
