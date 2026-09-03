import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';
import { AvaliacoesPage, contarAcertos, percentual } from '../../src/components/student/AvaliacoesPage';
import { Quiz, QuizSubmission } from '../../src/types';

const quiz = (id: string, over: Partial<Quiz> = {}): Quiz => ({
  id,
  courseId: 'course-1',
  title: `Avaliação ${id}`,
  questions: [
    {
      id: `${id}-q1`,
      questionText: 'Qual heurística trata de visibilidade do status?',
      options: ['Visibilidade', 'Consistência', 'Liberdade'],
      correctOptionIndex: 0,
      explanation: 'O sistema deve informar o que está acontecendo.',
    },
    {
      id: `${id}-q2`,
      questionText: 'Quantas heurísticas Nielsen propôs?',
      options: ['5', '10', '20'],
      correctOptionIndex: 1,
      recommendedModule: 'Módulo 2 — Heurísticas',
    },
  ],
  ...over,
});

const envio = (quizId: string, over: Partial<QuizSubmission> = {}): QuizSubmission => ({
  id: `sub-${quizId}`,
  userId: 'aluno-1',
  studentName: 'João Silva',
  courseId: 'course-1',
  quizId,
  scorePercent: 100,
  passed: true,
  submittedAt: '01/09/2026 às 10:00',
  ...over,
});

const renderPage = (over: Partial<React.ComponentProps<typeof AvaliacoesPage>> = {}) => {
  const props = {
    courseTitle: 'UX/UI Design',
    courseId: 'course-1',
    quizzes: [quiz('q1')],
    submissions: [] as QuizSubmission[],
    userId: 'aluno-1',
    onBack: vi.fn(),
    onSubmit: vi.fn(async () => ({ ok: true, scorePercent: 50, passed: false })),
    notify: vi.fn(),
    ...over,
  } as React.ComponentProps<typeof AvaliacoesPage>;
  render(<AvaliacoesPage {...props} />);

  return props;
};

/** Responde a questão visível escolhendo a alternativa pela letra. */
const responder = async (letra: string) => {
  const opcao = screen.getAllByRole('button').find((b) => b.textContent?.startsWith(letra));
  await userEvent.click(opcao!);
  await userEvent.click(screen.getByRole('button', { name: /^responder$/i }));
};

describe('contas da avaliação', () => {
  it('conta acertos comparando com o gabarito', () => {
    const q = quiz('q1');
    expect(contarAcertos(q.questions, { 'q1-q1': 0, 'q1-q2': 1 })).toBe(2);
    expect(contarAcertos(q.questions, { 'q1-q1': 0, 'q1-q2': 0 })).toBe(1);
    expect(contarAcertos(q.questions, {})).toBe(0);
  });

  it('percentual não divide por zero', () => {
    expect(percentual(0, 0)).toBe(0);
    expect(percentual(1, 3)).toBe(33);
  });
});

describe('AvaliacoesPage', () => {
  it('sem avaliações, explica em vez de mostrar lista vazia', () => {
    renderPage({ quizzes: [] });
    expect(screen.getByText(/ainda não tem avaliações/i)).toBeInTheDocument();
  });

  it('mostra só as avaliações do curso da página', () => {
    renderPage({ quizzes: [quiz('q1'), quiz('q2', { courseId: 'outro' })] });

    expect(screen.getByText('Avaliação q1')).toBeInTheDocument();
    expect(screen.queryByText('Avaliação q2')).toBeNull();
  });

  it('lista número de questões, nota anterior e o mínimo para aprovação', () => {
    renderPage({ submissions: [envio('q1', { scorePercent: 85 })] });

    expect(screen.getByText(/2 questões/)).toBeInTheDocument();
    expect(screen.getByText('Aprovado · 85%')).toBeInTheDocument();
    expect(screen.getByText('70%')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /refazer/i })).toBeInTheDocument();
  });

  it('a prova acontece na página, sem modal nem backdrop', async () => {
    const { container } = render(
      <AvaliacoesPage
        courseTitle="UX/UI Design"
        courseId="course-1"
        quizzes={[quiz('q1')]}
        submissions={[]}
        userId="aluno-1"
        onBack={vi.fn()}
        onSubmit={vi.fn(async () => ({ ok: true }))}
        notify={vi.fn()}
      />
    );

    await userEvent.click(screen.getByRole('button', { name: /começar/i }));

    expect(screen.getByText('Questão 1 de 2')).toBeInTheDocument();
    // O defeito que motivou a mudança: a prova abria num `fixed inset-0` que
    // fechava por clique no backdrop, descartando as respostas.
    expect(container.querySelector('.fixed')).toBeNull();
  });

  it('abre direto na avaliação pedida pelo card do curso', () => {
    renderPage({ quizzes: [quiz('q1'), quiz('q2')], quizInicial: 'q2' });

    expect(screen.getByText('Avaliação q2')).toBeInTheDocument();
    expect(screen.getByText('Questão 1 de 2')).toBeInTheDocument();
  });

  it('ignora quizInicial que não é deste curso', () => {
    renderPage({ quizzes: [quiz('q1')], quizInicial: 'inexistente' });

    // Cai na lista, não numa prova em branco.
    expect(screen.getByRole('button', { name: /começar/i })).toBeInTheDocument();
    expect(screen.queryByText(/Questão 1 de/)).toBeNull();
  });

  it('não deixa responder antes de escolher alternativa', async () => {
    renderPage();
    await userEvent.click(screen.getByRole('button', { name: /começar/i }));

    expect(screen.getByRole('button', { name: /^responder$/i })).toBeDisabled();
  });

  it('mostra o gabarito comentado e a indicação de estudo ao errar', async () => {
    renderPage();
    await userEvent.click(screen.getByRole('button', { name: /começar/i }));
    await responder('B');

    expect(screen.getByText(/ainda não foi desta vez/i)).toBeInTheDocument();
    expect(screen.getByText(/O sistema deve informar o que está acontecendo/)).toBeInTheDocument();
  });

  it('envia ao fim e mostra a nota que o SERVIDOR devolveu', async () => {
    const onSubmit = vi.fn(async () => ({ ok: true, scorePercent: 50, passed: false }));
    const props = renderPage({ onSubmit });

    await userEvent.click(screen.getByRole('button', { name: /começar/i }));
    await responder('A');
    await userEvent.click(screen.getByRole('button', { name: /próxima pergunta/i }));
    await responder('A');
    await userEvent.click(screen.getByRole('button', { name: /ver resultado final/i }));

    expect(onSubmit).toHaveBeenCalledWith('q1', 50, false, { 'q1-q1': 0, 'q1-q2': 0 });
    // 50% e reprovado vêm do servidor; o cliente só calculou para o feedback.
    expect(screen.getByText(/revisão recomendada/i)).toBeInTheDocument();
    expect(screen.getByText(/Rendimento:/)).toBeInTheDocument();
    expect(props.notify).toHaveBeenCalledWith(expect.stringContaining('50%'));
  });

  it('nota do servidor prevalece sobre a calculada no cliente', async () => {
    // Cliente acertaria 100%; se o servidor disser 40, é 40 que aparece.
    const onSubmit = vi.fn(async () => ({ ok: true, scorePercent: 40, passed: false }));
    renderPage({ onSubmit });

    await userEvent.click(screen.getByRole('button', { name: /começar/i }));
    await responder('A');
    await userEvent.click(screen.getByRole('button', { name: /próxima pergunta/i }));
    await responder('B');
    await userEvent.click(screen.getByRole('button', { name: /ver resultado final/i }));

    // O 40% aparece no selo e no texto; a asserção mira o texto, que é onde o
    // aluno lê a nota por extenso.
    expect(screen.getByText(/Você atingiu 40% de aproveitamento/)).toBeInTheDocument();
  });

  it('falha no envio avisa e não anuncia resultado', async () => {
    const onSubmit = vi.fn(async () => ({ ok: false, error: 'Servidor indisponível.' }));
    const props = renderPage({ onSubmit });

    await userEvent.click(screen.getByRole('button', { name: /começar/i }));
    await responder('A');
    await userEvent.click(screen.getByRole('button', { name: /próxima pergunta/i }));
    await responder('B');
    await userEvent.click(screen.getByRole('button', { name: /ver resultado final/i }));

    expect(screen.getByText('Servidor indisponível.')).toBeInTheDocument();
    expect(screen.queryByText(/revisão recomendada/i)).toBeNull();
    expect(props.notify).not.toHaveBeenCalled();
  });

  it('sair do teste pede confirmação antes de descartar as respostas', async () => {
    renderPage();
    await userEvent.click(screen.getByRole('button', { name: /começar/i }));
    await userEvent.click(screen.getByRole('button', { name: /sair do teste/i }));

    expect(screen.getByText(/descarta as respostas desta tentativa/i)).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: /continuar prova/i }));
    expect(screen.getByText('Questão 1 de 2')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: /sair do teste/i }));
    await userEvent.click(screen.getByRole('button', { name: /sair e descartar/i }));
    expect(screen.getByRole('button', { name: /começar/i })).toBeInTheDocument();
  });

  it('conta quantas avaliações do curso já foram aprovadas', () => {
    renderPage({
      quizzes: [quiz('q1'), quiz('q2')],
      submissions: [envio('q1'), envio('q2', { passed: false, scorePercent: 30 })],
    });

    expect(screen.getByText('1 de 2')).toBeInTheDocument();
  });
});

describe('histórico de tentativas', () => {
  const tresTentativas = [
    envio('q1', { id: 'primeira', scorePercent: 20, passed: false, submittedAt: '01/09/2026 às 09:00', enviadoEm: '2026-09-01T09:00:00' }),
    envio('q1', { id: 'segunda', scorePercent: 90, passed: true, submittedAt: '02/09/2026 às 14:30', enviadoEm: '2026-09-02T14:30:00' }),
    envio('q1', { id: 'terceira', scorePercent: 60, passed: false, submittedAt: '03/09/2026 às 16:23', enviadoEm: '2026-09-03T16:23:00' }),
  ];

  it('a nota exibida é da tentativa vigente, não da primeira da lista', () => {
    // A lista chega em qualquer ordem; antes um `find()` pegava a primeira.
    renderPage({ submissions: [tresTentativas[1], tresTentativas[0], tresTentativas[2]] });

    expect(screen.getByText('60%')).toBeInTheDocument();
    expect(screen.getByText(/tentativa vigente em 03\/09\/2026 às 16:23/i)).toBeInTheDocument();
  });

  it('anuncia quantas tentativas anteriores existem e lista cada uma', async () => {
    // A tentativa anterior era inativada a cada nova resposta: ficava no banco
    // (ADR 12) mas o aluno não tinha como ver o que já havia feito.
    renderPage({ submissions: tresTentativas });

    await userEvent.click(screen.getByText(/2 tentativas anteriores/i));

    expect(screen.getByText('90%')).toBeInTheDocument();
    expect(screen.getByText('20%')).toBeInTheDocument();
    expect(screen.getByText('02/09/2026 às 14:30')).toBeInTheDocument();
    // A vigente não se repete na lista de anteriores.
    expect(screen.getAllByText(/03\/09\/2026 às 16:23/)).toHaveLength(1);
  });

  it('tentativa única não anuncia histórico', () => {
    renderPage({ submissions: [tresTentativas[2]] });

    expect(screen.queryByText(/tentativa anterior/i)).not.toBeInTheDocument();
    expect(screen.getByText(/respondida em 03\/09\/2026 às 16:23/i)).toBeInTheDocument();
  });

  it('"Aprovadas" olha a vigente: reprovar depois de aprovar deixa de contar', () => {
    // A regra do projeto é que refazer substitui o resultado — o botão sempre se
    // chamou "Refazer Avaliação". Contar a melhor nota mudaria isso em silêncio.
    renderPage({ submissions: [tresTentativas[1], tresTentativas[2]] });

    expect(screen.getByText('0 de 1')).toBeInTheDocument();
  });

  it('tentativa de outro aluno não entra no histórico', () => {
    renderPage({
      submissions: [
        tresTentativas[2],
        envio('q1', { id: 'de-outro', userId: 'aluno-2', scorePercent: 10, submittedAt: '04/09/2026 às 08:00', enviadoEm: '2026-09-04T08:00:00' }),
      ],
    });

    expect(screen.queryByText(/tentativa anterior/i)).not.toBeInTheDocument();
    expect(screen.getByText('60%')).toBeInTheDocument();
    expect(screen.queryByText('10%')).not.toBeInTheDocument();
  });
});
