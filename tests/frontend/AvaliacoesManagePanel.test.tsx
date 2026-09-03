import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';
import {
  AvaliacoesManagePanel,
  problemaNaQuestao,
  problemaNaAvaliacao,
  paraQuestao,
  paraRascunho,
  questaoVazia,
} from '../../src/components/instructor/AvaliacoesManagePanel';
import { Course, Quiz, QuizQuestion, QuizSubmission } from '../../src/types';

const curso = (id: string, title: string): Course =>
  ({ id, title, description: 'd', category: 'Design', instructorId: 'prof-1' } as unknown as Course);

const questao = (id: string, over: Partial<QuizQuestion> = {}): QuizQuestion => ({
  id,
  questionText: 'Qual heurística trata de visibilidade?',
  options: ['Visibilidade', 'Consistência', 'Liberdade', 'Erro'],
  correctOptionIndex: 0,
  ...over,
});

const quiz = (id: string, over: Partial<Quiz> = {}): Quiz => ({
  id,
  courseId: 'c1',
  title: `Avaliação ${id}`,
  questions: [questao(`${id}-q1`)],
  ...over,
});

const envio = (quizId: string, over: Partial<QuizSubmission> = {}): QuizSubmission => ({
  id: `sub-${quizId}`,
  userId: 'aluno-1',
  studentName: 'João Silva',
  courseId: 'c1',
  quizId,
  scorePercent: 90,
  passed: true,
  submittedAt: '01/09/2026 às 10:00',
  ...over,
});

const renderPanel = (over: Partial<React.ComponentProps<typeof AvaliacoesManagePanel>> = {}) => {
  const props = {
    courses: [curso('c1', 'UX/UI Design')],
    quizzes: [] as Quiz[],
    submissions: [] as QuizSubmission[],
    onCreate: vi.fn(async () => ({ ok: true })),
    onUpdate: vi.fn(async () => ({ ok: true })),
    onDelete: vi.fn(async () => ({ ok: true })),
    confirmar: vi.fn(() => true),
    notify: vi.fn(),
    ...over,
  } as React.ComponentProps<typeof AvaliacoesManagePanel>;
  const utils = render(<AvaliacoesManagePanel {...props} />);

  return { ...utils, props };
};

/** Preenche a primeira questão do editor com dados válidos. */
const preencherQuestao = async () => {
  await userEvent.type(screen.getByLabelText(/enunciado/i), 'Pergunta de teste?');
  await userEvent.type(screen.getByLabelText(/texto da alternativa a/i), 'Certa');
  await userEvent.type(screen.getByLabelText(/texto da alternativa b/i), 'Errada');
};

describe('validação da questão', () => {
  const base = { ...questaoVazia(), questionText: 'Pergunta?' };

  it('exige enunciado', () => {
    expect(problemaNaQuestao(questaoVazia())).toMatch(/enunciado/i);
  });

  it('exige pelo menos duas alternativas', () => {
    expect(problemaNaQuestao({ ...base, options: ['só uma', '', '', ''] })).toMatch(/ao menos 2/i);
  });

  it('recusa gabarito apontando para alternativa em branco', () => {
    // O balão antigo deixava marcar "Opção 4 (D)" e publicar com a D vazia:
    // nascia questão cujo gabarito aponta para algo que o aluno não vê.
    const q = { ...base, options: ['A', 'B', '', ''], correctOptionIndex: 3 };
    expect(problemaNaQuestao(q)).toMatch(/correta está em branco/i);
  });

  it('recusa alternativa vazia no meio das preenchidas', () => {
    const q = { ...base, options: ['A', '', 'C', ''] };
    expect(problemaNaQuestao(q)).toMatch(/alternativa B está em branco/i);
  });

  it('recusa alternativas repetidas', () => {
    const q = { ...base, options: ['Sim', 'sim ', '', ''] };
    expect(problemaNaQuestao(q)).toMatch(/repetidas/i);
  });

  it('aceita questão completa', () => {
    expect(problemaNaQuestao({ ...base, options: ['A', 'B', 'C', 'D'] })).toBeNull();
  });
});

describe('validação da avaliação', () => {
  const boa = { ...questaoVazia(), questionText: 'P?', options: ['A', 'B', '', ''] };

  it('exige título, disciplina e ao menos uma questão', () => {
    expect(problemaNaAvaliacao('', 'c1', [boa])).toMatch(/título/i);
    expect(problemaNaAvaliacao('T', '', [boa])).toMatch(/disciplina/i);
    expect(problemaNaAvaliacao('T', 'c1', [])).toMatch(/pelo menos uma questão/i);
  });

  it('aponta em qual questão está o problema', () => {
    const ruim = { ...questaoVazia(), questionText: '' };
    expect(problemaNaAvaliacao('T', 'c1', [boa, ruim])).toMatch(/^Questão 2:/);
  });
});

describe('conversão de payload', () => {
  it('descarta alternativa em branco e campo opcional vazio', () => {
    const q = paraQuestao({
      ...questaoVazia(),
      questionText: '  P?  ',
      options: ['A', 'B', '', ''],
      explanation: '   ',
    });

    expect(q.options).toEqual(['A', 'B']);
    expect(q.questionText).toBe('P?');
    expect(q.explanation).toBeUndefined();
  });

  it('preserva o id da questão já gravada — é o que liga às respostas entregues', () => {
    // Reenviar sem id faria o servidor apagar e recriar a questão.
    const rascunho = paraRascunho(questao('q-existente', { allowRetry: false }));
    expect(rascunho.id).toBe('q-existente');
    expect(rascunho.allowRetry).toBe(false);
    expect(paraQuestao(rascunho).id).toBe('q-existente');
  });

  it('allowRetry ausente conta como permitido', () => {
    expect(paraRascunho(questao('q1')).allowRetry).toBe(true);
  });
});

describe('AvaliacoesManagePanel', () => {
  it('a área não usa modal nem backdrop', async () => {
    const { container } = renderPanel({ quizzes: [quiz('q1')] });
    expect(container.querySelector('.fixed')).toBeNull();

    await userEvent.click(screen.getByRole('button', { name: /elaborar avaliação/i }));
    expect(container.querySelector('.fixed')).toBeNull();
  });

  it('sem avaliação nenhuma, explica em vez de mostrar lista vazia', () => {
    renderPanel();
    expect(screen.getByText(/nenhuma avaliação elaborada ainda/i)).toBeInTheDocument();
  });

  it('sem disciplina sob responsabilidade, não oferece elaborar', () => {
    renderPanel({ courses: [] });
    expect(screen.getByText(/ainda não tem disciplina/i)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /elaborar avaliação/i })).toBeNull();
  });

  it('lista só as avaliações das disciplinas desta pessoa', () => {
    renderPanel({ quizzes: [quiz('q1'), quiz('q2', { courseId: 'de-outro-professor' })] });

    expect(screen.getByText('Avaliação q1')).toBeInTheDocument();
    expect(screen.queryByText('Avaliação q2')).toBeNull();
  });

  it('mostra questões, respostas entregues e aprovados', () => {
    renderPanel({
      quizzes: [quiz('q1')],
      submissions: [envio('q1'), envio('q1', { id: 'sub-2', passed: false, scorePercent: 20 })],
    });

    expect(screen.getByText(/1 questão · 2 respostas · 1 aprovado \(mínimo 70%\)/)).toBeInTheDocument();
  });

  it('cria a avaliação com o que foi preenchido', async () => {
    const { props } = renderPanel();

    await userEvent.click(screen.getByRole('button', { name: /elaborar avaliação/i }));
    await userEvent.type(screen.getByLabelText(/título da avaliação/i), 'Teste do Módulo 1');
    await preencherQuestao();
    await userEvent.click(screen.getByRole('button', { name: /publicar avaliação/i }));

    expect(props.onCreate).toHaveBeenCalledWith(
      'c1',
      'Teste do Módulo 1',
      [expect.objectContaining({ questionText: 'Pergunta de teste?', options: ['Certa', 'Errada'], correctOptionIndex: 0 })]
    );
    expect(props.notify).toHaveBeenCalledWith(expect.stringMatching(/publicada/i));
  });

  it('não publica avaliação inválida e diz o motivo', async () => {
    const { props } = renderPanel();

    await userEvent.click(screen.getByRole('button', { name: /elaborar avaliação/i }));
    await userEvent.click(screen.getByRole('button', { name: /publicar avaliação/i }));

    expect(screen.getByRole('alert')).toHaveTextContent(/título/i);
    expect(props.onCreate).not.toHaveBeenCalled();
  });

  it('falha do servidor aparece e a pessoa não perde o rascunho', async () => {
    const onCreate = vi.fn(async () => ({ ok: false, error: 'Você não leciona esta disciplina.' }));
    const { props } = renderPanel({ onCreate });

    await userEvent.click(screen.getByRole('button', { name: /elaborar avaliação/i }));
    await userEvent.type(screen.getByLabelText(/título da avaliação/i), 'Teste');
    await preencherQuestao();
    await userEvent.click(screen.getByRole('button', { name: /publicar avaliação/i }));

    expect(screen.getByRole('alert')).toHaveTextContent('Você não leciona esta disciplina.');
    // O padrão antigo trocava de tela e engolia o erro; aqui o editor continua
    // aberto com o texto digitado.
    expect(screen.getByLabelText(/título da avaliação/i)).toHaveValue('Teste');
    expect(props.notify).not.toHaveBeenCalled();
  });

  it('editar abre a avaliação já preenchida e salva pelo id existente', async () => {
    const { props } = renderPanel({ quizzes: [quiz('q1')] });

    await userEvent.click(screen.getByRole('button', { name: /editar/i }));
    expect(screen.getByLabelText(/título da avaliação/i)).toHaveValue('Avaliação q1');
    expect(screen.getByLabelText(/enunciado/i)).toHaveValue('Qual heurística trata de visibilidade?');

    await userEvent.clear(screen.getByLabelText(/título da avaliação/i));
    await userEvent.type(screen.getByLabelText(/título da avaliação/i), 'Título corrigido');
    await userEvent.click(screen.getByRole('button', { name: /salvar alterações/i }));

    expect(props.onUpdate).toHaveBeenCalledWith('q1', 'c1', 'Título corrigido', [
      expect.objectContaining({ id: 'q1-q1' }),
    ]);
    expect(props.onCreate).not.toHaveBeenCalled();
  });

  it('avisa que editar não recalcula as notas já lançadas', async () => {
    renderPanel({ quizzes: [quiz('q1')], submissions: [envio('q1')] });

    await userEvent.click(screen.getByRole('button', { name: /editar/i }));
    expect(screen.getByText(/notas já lançadas não mudam/i)).toBeInTheDocument();
  });

  it('remover alternativa antes da correta não desloca o gabarito', async () => {
    // A correta é a C; excluindo a A, ela tem de continuar sendo a mesma frase.
    const q = quiz('q1', {
      questions: [questao('q1-q1', { options: ['A', 'B', 'Certa', 'D'], correctOptionIndex: 2 })],
    });
    const { props } = renderPanel({ quizzes: [q] });

    await userEvent.click(screen.getByRole('button', { name: /editar/i }));
    await userEvent.click(screen.getByRole('button', { name: /excluir alternativa a/i }));
    await userEvent.click(screen.getByRole('button', { name: /salvar alterações/i }));

    const questoes = (props.onUpdate as ReturnType<typeof vi.fn>).mock.calls[0][3];
    expect(questoes[0].options[questoes[0].correctOptionIndex]).toBe('Certa');
  });

  it('a confirmação diz que as respostas dos alunos ficam registradas', async () => {
    const { props } = renderPanel({ quizzes: [quiz('q1')], submissions: [envio('q1')] });

    await userEvent.click(screen.getByRole('button', { name: /excluir/i }));

    // O aviso antigo dizia que as respostas eram apagadas junto — era verdade
    // até a ADR 12 (nada é apagado). Manter aquele texto assustaria com uma
    // perda que não acontece, e assustar com dado errado é pior que não avisar.
    const pergunta = (props.confirmar as ReturnType<typeof vi.fn>).mock.calls[0][0] as string;
    expect(pergunta).toMatch(/1 respostas já entregues continuam registradas/);
    expect(pergunta).not.toMatch(/apaga/i);
    expect(props.onDelete).toHaveBeenCalledWith('q1');
  });

  it('recusar a confirmação não exclui nada', async () => {
    const { props } = renderPanel({ quizzes: [quiz('q1')], confirmar: vi.fn(() => false) });

    await userEvent.click(screen.getByRole('button', { name: /excluir/i }));
    expect(props.onDelete).not.toHaveBeenCalled();
  });

  it('falha ao excluir aparece na lista', async () => {
    const onDelete = vi.fn(async () => ({ ok: false, error: 'Avaliação de outro curso.' }));
    renderPanel({ quizzes: [quiz('q1')], onDelete });

    await userEvent.click(screen.getByRole('button', { name: /excluir/i }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Avaliação de outro curso.');
  });

  it('acrescentar e remover questão no editor', async () => {
    renderPanel();
    await userEvent.click(screen.getByRole('button', { name: /elaborar avaliação/i }));

    expect(screen.getByText('Questão 1')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: /acrescentar questão/i }));
    expect(screen.getByText('Questão 2')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: /remover questão 2/i }));
    expect(screen.queryByText('Questão 2')).toBeNull();
  });

  it('cancelar volta para a lista', async () => {
    renderPanel({ quizzes: [quiz('q1')] });

    await userEvent.click(screen.getByRole('button', { name: /elaborar avaliação/i }));
    await userEvent.click(screen.getByRole('button', { name: /^cancelar$/i }));

    expect(screen.getByText('Avaliação q1')).toBeInTheDocument();
  });
});
