import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MeusCursos, type CursoDoAluno } from '../../src/components/student/MeusCursos';
import type { Certificate, Course } from '../../src/types';

const curso = (id: string, title: string, category: string, nAulas: number): Course =>
  ({
    id,
    title,
    description: `Descrição de ${title}`,
    category,
    thumbnail: `/img/${id}.png`,
    instructorName: 'Gestor de Conteúdos',
    lessons: Array.from({ length: nAulas }, (_, i) => ({ id: `${id}-l${i}` })),
    liveSessions: [],
  }) as unknown as Course;

const item = (c: Course, concluidas: number, extra: Partial<CursoDoAluno> = {}): CursoDoAluno => ({
  curso: c,
  concluidas,
  total: c.lessons.length,
  ...extra,
});

const fullstack = curso('c1', 'Desenvolvimento Full-Stack', 'Desenvolvimento Web', 4);
const portfolio = curso('c2', 'Portfólio de Comprovação', 'Portfólio Artístico', 15);
const fotografia = curso('c3', 'Curso de fotografia', 'Audio Visual', 3);

const padrao = {
  emAndamento: [item(fullstack, 3, { frequencia: 50, minimoFrequencia: 70 }), item(portfolio, 14)],
  concluidos: [item(fotografia, 3)],
  certificadoPorCurso: {} as Record<string, Certificate | undefined>,
  onAbrirCurso: () => {},
  onVerCertificados: () => {},
};

describe('MeusCursos', () => {
  it('separa cursos em andamento dos concluídos e conta cada grupo', () => {
    render(<MeusCursos {...padrao} />);

    expect(screen.getByRole('tab', { name: 'Todos (3)' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Em andamento (2)' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Concluídos (1)' })).toBeInTheDocument();

    expect(screen.getByRole('heading', { name: 'Em andamento' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Concluídos' })).toBeInTheDocument();
  });

  it('mostra progresso com número e texto, não só com a barra', () => {
    render(<MeusCursos {...padrao} />);

    // D §2.13: progresso que só existe como cor não é legível por quem não a distingue.
    expect(screen.getByText('75%')).toBeInTheDocument();
    expect(screen.getByText('3 de 4 aulas concluídas')).toBeInTheDocument();
    expect(screen.getByText('93%')).toBeInTheDocument();
    expect(screen.getByText('14 de 15 aulas concluídas')).toBeInTheDocument();
  });

  /*
   * Frequência NÃO é progresso, e os dois aparecem juntos de propósito.
   * Progresso conta aulas; frequência conta aulas e encontros ao vivo, e é o
   * número que decide a emissão do certificado. O cartão anterior mostrava só
   * frequência — a referência mostra só progresso. Trocar um pelo outro tiraria
   * da tela a informação acadêmica em favor da decorativa. Aqui estão as duas,
   * e com valores diferentes: 75% de progresso com 50% de frequência.
   */
  it('mostra a frequência ao lado do progresso, sem confundir os dois', () => {
    render(<MeusCursos {...padrao} />);

    expect(screen.getByText('50%')).toBeInTheDocument();
    expect(screen.getByText(/mínimo 70%/)).toBeInTheDocument();
    expect(screen.getByText('75%')).toBeInTheDocument();
  });

  it('filtra por situação', async () => {
    render(<MeusCursos {...padrao} />);

    await userEvent.click(screen.getByRole('tab', { name: 'Concluídos (1)' }));
    expect(screen.queryByRole('heading', { name: 'Em andamento' })).not.toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Concluídos' })).toBeInTheDocument();

    await userEvent.click(screen.getByRole('tab', { name: 'Em andamento (2)' }));
    expect(screen.getByRole('heading', { name: 'Em andamento' })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Concluídos' })).not.toBeInTheDocument();
  });

  it('busca por nome e por categoria, e avisa quando nada corresponde', async () => {
    render(<MeusCursos {...padrao} />);
    const busca = screen.getByLabelText('Buscar entre os meus cursos');

    await userEvent.type(busca, 'fotografia');
    expect(screen.getByText('Curso de fotografia')).toBeInTheDocument();
    expect(screen.queryByText('Desenvolvimento Full-Stack')).not.toBeInTheDocument();

    await userEvent.clear(busca);
    await userEvent.type(busca, 'Audio Visual');
    expect(screen.getByText('Curso de fotografia')).toBeInTheDocument();

    await userEvent.clear(busca);
    await userEvent.type(busca, 'xyzinexistente');
    expect(screen.getByText('Nenhum curso seu corresponde a esta busca.')).toBeInTheDocument();
  });

  /*
   * O certificado do curso concluído do aluno de teste está INATIVADO no banco,
   * e a API corretamente não o entrega. Oferecer "Acessar certificado" nesse
   * estado mandaria o aluno a uma tela que não tem o documento — e um
   * certificado revogado não vira link só porque o curso foi concluído.
   */
  it('não promete certificado quando não há certificado ativo', () => {
    render(<MeusCursos {...padrao} />);

    expect(screen.getByText('Sem certificado emitido para este curso.')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /acessar certificado/i })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: /rever o conteúdo/i })).toBeInTheDocument();
  });

  it('mostra a data de emissão como veio, sem reinterpretar o fuso', async () => {
    const onVerCertificados = vi.fn();
    render(
      <MeusCursos
        {...padrao}
        onVerCertificados={onVerCertificados}
        certificadoPorCurso={{
          c3: { id: 'cert-1', courseId: 'c3', issueDate: '28/08/2026' } as Certificate,
        }}
      />,
    );

    /*
     * `issueDate` chega do Laravel já em d/m/Y (cast `date:d/m/Y`). Data pura não
     * tem instante: passá-la por `new Date()` a converteria de meia-noite UTC
     * para 21h do dia ANTERIOR em Brasília, e o certificado recuaria um dia.
     * Ver backend-laravel/app/Support/Fuso.php.
     */
    expect(screen.getByText('28/08/2026')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: /acessar certificado/i }));
    expect(onVerCertificados).toHaveBeenCalledTimes(1);
  });

  it('abre o curso pelo id ao continuar', async () => {
    const onAbrirCurso = vi.fn();
    render(<MeusCursos {...padrao} onAbrirCurso={onAbrirCurso} />);

    await userEvent.click(screen.getAllByRole('button', { name: /continuar curso/i })[0]);
    expect(onAbrirCurso).toHaveBeenCalledWith('c1');
  });

  it('some inteira quando o aluno não tem curso nenhum', () => {
    const { container } = render(<MeusCursos {...padrao} emAndamento={[]} concluidos={[]} />);

    // Um bloco vazio com título só ocuparia a dobra: o catálogo abaixo já convida.
    expect(container).toBeEmptyDOMElement();
  });

  it('usa singular quando há um curso só', () => {
    render(<MeusCursos {...padrao} emAndamento={[item(portfolio, 14)]} concluidos={[]} />);

    expect(screen.getByRole('heading', { name: 'Meu curso' })).toBeInTheDocument();
  });

  it('não quebra com curso sem aula nenhuma', () => {
    const vazio = curso('c9', 'Curso sem aulas', 'Teste', 0);
    render(<MeusCursos {...padrao} emAndamento={[item(vazio, 0)]} concluidos={[]} />);

    expect(screen.getByText('0%')).toBeInTheDocument();
    expect(screen.getByText('0 de 0 aulas concluídas')).toBeInTheDocument();
  });
});
