import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { CalendarioPage } from '../../src/components/pages/CalendarioPage';
import { Course, WebinarEvent } from '../../src/types';
import { toDatetimeLocalValue } from '../../src/utils/liveSchedule';

/**
 * A agenda é relativa a "hoje", então o relógio é congelado: sem isso o teste passa
 * hoje e falha quando as datas fixas vencerem.
 */
const HOJE = new Date(2026, 8, 10, 12, 0);

const emDias = (dias: number, hora: number, minuto = 0): string => {
  const d = new Date(HOJE);
  d.setDate(d.getDate() + dias);
  d.setHours(hora, minuto, 0, 0);

  return toDatetimeLocalValue(d);
};

const curso = (id: string, title: string, sessoes: Array<[string, string, string]>): Course => ({
  id,
  title,
  description: 'd',
  category: 'c',
  thumbnail: 't',
  instructorName: 'Prof',
  lessons: [],
  liveSessions: sessoes.map(([sid, stitle, scheduledAt]) => ({
    id: sid, courseId: id, title: stitle, scheduledAt,
    durationMinutes: 60, meetingLink: '', isLive: false,
  })),
} as unknown as Course);

const props = (courses: Course[], webinars: WebinarEvent[] = []) => ({
  onBack: vi.fn(),
  isUserLoggedIn: false,
  onRequireLogin: vi.fn(),
  speakText: vi.fn(),
  courses,
  webinars,
});

beforeEach(() => {
  vi.useFakeTimers();
  vi.setSystemTime(HOJE);
});

afterEach(() => {
  vi.useRealTimers();
});

describe('CalendarioPage', () => {
  it('mostra as aulas ao vivo agendadas, da mais próxima para a mais distante', () => {
    render(<CalendarioPage {...props([
      curso('c1', 'Design de Interfaces', [
        ['s2', 'Feedback de Portfólio', emDias(12, 18, 0)],
        ['s1', 'Mentoria de Wireframes', emDias(5, 19, 30)],
      ]),
    ])} />);

    const titulos = screen.getAllByText(/Mentoria de Wireframes|Feedback de Portfólio/);
    expect(titulos.map((t) => t.textContent)).toEqual([
      'Mentoria de Wireframes',
      'Feedback de Portfólio',
    ]);
  });

  it('identifica o curso de cada encontro', () => {
    render(<CalendarioPage {...props([
      curso('c1', 'Design de Interfaces', [['s1', 'Mentoria', emDias(3, 19, 30)]]),
    ])} />);

    expect(screen.getByText('Design de Interfaces')).toBeInTheDocument();
    expect(screen.getByText(/aula ao vivo/i)).toBeInTheDocument();
  });

  it('esconde o que já passou e o que está além dos 30 dias', () => {
    render(<CalendarioPage {...props([
      curso('c1', 'Curso', [
        ['s1', 'Já aconteceu', emDias(-2, 19, 0)],
        ['s2', 'Longe demais', emDias(45, 19, 0)],
        ['s3', 'Dentro da janela', emDias(10, 19, 0)],
      ]),
    ])} />);

    expect(screen.getByText('Dentro da janela')).toBeInTheDocument();
    expect(screen.queryByText('Já aconteceu')).not.toBeInTheDocument();
    expect(screen.queryByText('Longe demais')).not.toBeInTheDocument();
  });

  it('ignora sessão no formato antigo de texto livre, sem quebrar a página', () => {
    render(<CalendarioPage {...props([
      curso('c1', 'Curso', [
        ['s1', 'Formato antigo', 'Próxima Segunda, às 20:00'],
        ['s2', 'Formato novo', emDias(4, 19, 0)],
      ]),
    ])} />);

    expect(screen.getByText('Formato novo')).toBeInTheDocument();
    expect(screen.queryByText('Formato antigo')).not.toBeInTheDocument();
  });

  it('inclui webinars globais junto das aulas', () => {
    const webinar = {
      id: 'w1', title: 'IA no Design', date: '15/09/2026', time: '19:00',
      description: 'd', link: '#', image: 'i',
    } as unknown as WebinarEvent;

    render(<CalendarioPage {...props(
      [curso('c1', 'Curso', [['s1', 'Mentoria', emDias(2, 19, 0)]])],
      [webinar]
    )} />);

    expect(screen.getByText('IA no Design')).toBeInTheDocument();
    // "Webinar aberto" aparece no selo do tipo e na linha de contexto do card.
    expect(screen.getAllByText(/webinar aberto/i).length).toBeGreaterThan(0);
    expect(screen.getByText('Mentoria')).toBeInTheDocument();
  });

  it('avisa quando não há nada agendado, em vez de mostrar evento inventado', () => {
    render(<CalendarioPage {...props([curso('c1', 'Curso', [])])} />);

    expect(screen.getByText(/nenhum encontro agendado para os próximos 30 dias/i)).toBeInTheDocument();
  });

  it('nunca expõe o link da sala nesta página pública', () => {
    // O catálogo anônimo já vem sem meetingLink (ISO-01); a página também não
    // oferece link algum, nem quando o dado chega preenchido.
    const c = curso('c1', 'Curso', [['s1', 'Mentoria', emDias(3, 19, 0)]]);
    c.liveSessions[0].meetingLink = 'https://meet.google.com/secreto';

    const { container } = render(<CalendarioPage {...props([c])} />);

    expect(container.querySelectorAll('a[href]')).toHaveLength(0);
    expect(container.innerHTML).not.toContain('meet.google.com');
  });

  it('visitante anônimo é levado ao login ao tentar participar', () => {
    const p = props([curso('c1', 'Curso', [['s1', 'Mentoria', emDias(3, 19, 0)]])]);
    render(<CalendarioPage {...p} />);

    fireEvent.click(screen.getByRole('button', { name: /participar/i }));
    expect(p.onRequireLogin).toHaveBeenCalled();
  });

  it('mostra a distância em dias de cada encontro', () => {
    render(<CalendarioPage {...props([
      curso('c1', 'Curso', [
        ['s1', 'Hoje ainda', emDias(0, 19, 0)],
        ['s2', 'Daqui a uma semana', emDias(7, 19, 0)],
      ]),
    ])} />);

    // O rótulo compõe distância e duração: "hoje · 60 min".
    expect(screen.getByText('hoje · 60 min')).toBeInTheDocument();
    expect(screen.getByText('em 7 dias · 60 min')).toBeInTheDocument();
  });
});
