import { describe, it, expect } from 'vitest';
import {
  buildAlunos,
  buildCursos,
  buildMatriculas,
  buildProgressoModulo,
  buildCertificados,
} from '../../src/utils/managementExport';

describe('managementExport — conversão de payloads do backend para a base de BI', () => {
  it('buildAlunos mapeia os campos e usa string vazia quando o dado é ausente', () => {
    const rows = buildAlunos([
      { id: 'u1', name: 'João Silva', email: 'joao@example.com', status: 'active' },
    ]);
    expect(rows).toEqual([
      {
        id_aluno: 'u1',
        nome: 'João Silva',
        email: 'joao@example.com',
        status_conta: 'active',
        municipio: '',
        uf: '',
        area_de_interesse: '',
        data_de_cadastro: '',
      },
    ]);
  });

  it('buildCursos usa o percentual mínimo de frequência do curso (courseMinAttendance)', () => {
    const rows = buildCursos([
      { id: 'c1', title: 'Curso A', category: 'Arte', instructorName: 'Prof. X', minAttendance: 80 },
      { id: 'c2', title: 'Curso B', category: 'Música', instructorName: 'Prof. Y' },
    ]);
    expect(rows[0].percentual_minimo).toBe(80);
    // sem minAttendance definido no curso, cai no padrão institucional (70)
    expect(rows[1].percentual_minimo).toBe(70);
  });

  it('buildCursos usa "Sim"/"Não" para emissão de certificado conforme a flag do curso', () => {
    const rows = buildCursos([
      { id: 'c1', title: 'A', category: 'X', instructorName: 'P', emiteCertificado: false },
      { id: 'c2', title: 'B', category: 'X', instructorName: 'P' },
    ]);
    expect(rows[0].emite_certificado).toBe('Não');
    expect(rows[1].emite_certificado).toBe('Sim');
  });

  it('buildMatriculas gera uma linha "Ativa" para o curso em andamento e uma linha por curso concluído', () => {
    const rows = buildMatriculas(
      [
        {
          id: 'e1',
          userId: 'u1',
          studentName: 'João Silva',
          enrolledCourseId: 'c1',
          enrolledAt: '2026-01-10T00:00:00.000Z',
          completedCourseIds: ['c2', 'c3'],
        },
      ],
      [{ courseId: 'c1', userId: 'u1', studentName: 'João Silva', completedLessons: ['l1', 'l2'] }],
      [{ courseId: 'c2', userId: 'u1', studentName: 'João Silva' }],
    );

    expect(rows).toHaveLength(3);
    expect(rows[0]).toMatchObject({
      id_matricula: 'e1',
      id_curso: 'c1',
      data_matricula: '2026-01-10',
      status_matricula: 'Ativa',
      modulos_concluidos: 2,
      certificado_liberado: 'Não',
    });
    expect(rows[1]).toMatchObject({ id_curso: 'c2', status_matricula: 'Concluída', certificado_liberado: 'Sim' });
    expect(rows[2]).toMatchObject({ id_curso: 'c3', status_matricula: 'Concluída', certificado_liberado: 'Não' });
  });

  it('buildProgressoModulo ignora progresso de cursos que não estão mais no catálogo', () => {
    const rows = buildProgressoModulo(
      [{ id: 'p1', userId: 'u1', courseId: 'curso-removido', completedLessons: [] }],
      [{ id: 'c1', lessons: [{ id: 'l1', title: 'Aula 1' }] }],
    );
    expect(rows).toEqual([]);
  });

  it('buildProgressoModulo marca cada aula como Concluído/Não Iniciado', () => {
    const rows = buildProgressoModulo(
      [{ id: 'p1', userId: 'u1', courseId: 'c1', completedLessons: ['l1'] }],
      [
        {
          id: 'c1',
          lessons: [
            { id: 'l1', title: 'Aula 1' },
            { id: 'l2', title: 'Aula 2' },
          ],
        },
      ],
    );
    expect(rows.map((r) => r.status_modulo)).toEqual(['Concluído', 'Não Iniciado']);
  });

  it('buildCertificados usa a carga horária do curso correspondente no catálogo', () => {
    const rows = buildCertificados(
      [{ id: 'cert1', courseId: 'c1', userId: 'u1', verificationHash: 'abc', issueDate: '2026-02-01', attendancePercent: 90 }],
      [{ id: 'c1', cargaHoraria: 40 }],
    );
    expect(rows[0].carga_horaria_certificada).toBe(40);
  });
});
