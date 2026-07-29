// Exportação de Dados Gerenciais do painel administrativo.
// Fonte de dados: EXCLUSIVAMENTE os endpoints seguros e auditados do backend
// (GET /api/export/:dataset — restritos a admin, com rate limit e trilha de auditoria).
// Este módulo apenas converte os payloads do servidor em CSV e dispara o download —
// nenhuma base é montada a partir do estado local do navegador.
import { authFetch } from '../context/LMSContext';
import { courseMinAttendance } from '../config/constants';

export type ManagementBase = 'alunos' | 'cursos' | 'matriculas' | 'progresso' | 'certificados';

const BASE_FILENAMES: Record<ManagementBase, string> = {
  alunos: 'base_alunos.csv',
  cursos: 'base_cursos.csv',
  matriculas: 'base_matriculas.csv',
  progresso: 'base_progresso_modulo.csv',
  certificados: 'base_certificados.csv',
};

async function fetchDataset(dataset: string): Promise<any[]> {
  const res = await authFetch(`/api/export/${dataset}`);
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new Error(body.message || `Falha ao exportar a base "${dataset}" (${res.status}).`);
  }
  const payload = await res.json();
  return payload.data ?? [];
}

/** Cursos com aulas (catálogo público) — usados para nomear módulos e carga horária. */
async function fetchCourseCatalog(): Promise<any[]> {
  const res = await authFetch('/api/courses');
  if (!res.ok) return [];
  return res.json();
}

// ---------- Conversão dos payloads do servidor para o layout de BI em português ----------

export function buildAlunos(students: any[]) {
  return students.map((u) => ({
    id_aluno: u.id,
    nome: u.name,
    email: u.email,
    status_conta: u.status,
    municipio: u.municipio ?? '',
    uf: u.uf ?? '',
    area_de_interesse: u.areaInteresse ?? '',
    data_de_cadastro: u.dataCadastro ?? '',
  }));
}

export function buildCursos(courses: any[]) {
  return courses.map((c) => ({
    id_curso: c.id,
    titulo: c.title,
    categoria: c.category,
    area_tematica: c.areaTematica ?? '',
    carga_horaria: c.cargaHoraria ?? '',
    modalidade: c.modalidade ?? '',
    nivel: c.nivel ?? '',
    professor_responsavel: c.instructorName,
    emite_certificado: c.emiteCertificado !== false ? 'Sim' : 'Não',
    percentual_minimo: courseMinAttendance(c),
    status_do_curso: c.statusCurso ?? '',
  }));
}

export function buildMatriculas(enrollments: any[], progress: any[], certificates: any[]) {
  const rows: any[] = [];
  for (const e of enrollments) {
    // Joins por userId (ADR 10) — o backend garante o campo em matrículas e progresso.
    const enrollmentId = e.id ?? e.userId;
    if (e.enrolledCourseId) {
      const prog = progress.find((p) => p.courseId === e.enrolledCourseId && p.userId === e.userId);
      const hasCert = certificates.some((c) => c.courseId === e.enrolledCourseId && c.userId === e.userId);
      rows.push({
        id_matricula: enrollmentId,
        id_aluno: e.userId,
        id_curso: e.enrolledCourseId,
        data_matricula: e.enrolledAt ? String(e.enrolledAt).split('T')[0] : '',
        status_matricula: 'Ativa',
        modulos_concluidos: Array.isArray(prog?.completedLessons) ? prog.completedLessons.length : 0,
        certificado_liberado: hasCert ? 'Sim' : 'Não',
      });
    }
    const completed: string[] = Array.isArray(e.completedCourseIds) ? e.completedCourseIds : [];
    completed.forEach((courseId, i) => {
      const cert = certificates.find((c) => c.courseId === courseId && c.userId === e.userId);
      rows.push({
        id_matricula: `${enrollmentId}-C${i + 1}`,
        id_aluno: e.userId,
        id_curso: courseId,
        data_matricula: '',
        status_matricula: 'Concluída',
        modulos_concluidos: '',
        certificado_liberado: cert ? 'Sim' : 'Não',
      });
    });
  }
  return rows;
}

export function buildProgressoModulo(progress: any[], catalog: any[]) {
  const rows: any[] = [];
  for (const p of progress) {
    const course = catalog.find((c) => c.id === p.courseId);
    if (!course) continue;
    const completed: string[] = Array.isArray(p.completedLessons) ? p.completedLessons : [];
    for (const lesson of course.lessons ?? []) {
      rows.push({
        id_progresso: `${p.id}-${lesson.id}`,
        id_matricula: p.enrollmentId ?? '',
        id_aluno: p.userId,
        id_curso: p.courseId,
        id_modulo: lesson.id,
        titulo_modulo: lesson.title,
        status_modulo: completed.includes(lesson.id) ? 'Concluído' : 'Não Iniciado',
      });
    }
  }
  return rows;
}

export function buildCertificados(certificates: any[], catalog: any[]) {
  return certificates.map((c) => {
    const course = catalog.find((co) => co.id === c.courseId);
    return {
      id_certificado: c.id,
      id_matricula: c.enrollmentId ?? '',
      id_aluno: c.userId ?? c.studentName,
      id_curso: c.courseId,
      codigo_validacao: c.verificationHash,
      data_emissao: c.issueDate,
      percentual_frequencia: c.attendancePercent,
      carga_horaria_certificada: course?.cargaHoraria ?? '',
    };
  });
}

// ---------- Download CSV ----------

function downloadCSV(filename: string, data: any[]) {
  if (data.length === 0) return;
  const headers = Object.keys(data[0]);
  const delimiter = ';';
  // '﻿' = BOM para o Excel abrir o CSV com acentuação correta.
  const csvContent =
    '﻿' +
    [
      headers.join(delimiter),
      ...data.map((row) =>
        headers
          .map((header) => {
            const val = row[header];
            const strVal = val === undefined || val === null ? '' : String(val);
            const escaped = strVal.replace(/"/g, '""');
            if (escaped.includes(delimiter) || escaped.includes('\n') || escaped.includes('"')) {
              return `"${escaped}"`;
            }
            return escaped;
          })
          .join(delimiter)
      ),
    ].join('\n');

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  link.setAttribute('download', filename);
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

async function buildBase(base: ManagementBase): Promise<any[]> {
  switch (base) {
    case 'alunos':
      return buildAlunos(await fetchDataset('students'));
    case 'cursos':
      return buildCursos(await fetchDataset('courses'));
    case 'matriculas': {
      const [enrollments, progress, certificates] = await Promise.all([
        fetchDataset('enrollments'),
        fetchDataset('progress'),
        fetchDataset('certificates'),
      ]);
      return buildMatriculas(enrollments, progress, certificates);
    }
    case 'progresso': {
      const [progress, catalog] = await Promise.all([fetchDataset('progress'), fetchCourseCatalog()]);
      return buildProgressoModulo(progress, catalog);
    }
    case 'certificados': {
      const [certificates, catalog] = await Promise.all([fetchDataset('certificates'), fetchCourseCatalog()]);
      return buildCertificados(certificates, catalog);
    }
  }
}

/** Exporta uma única base (download .csv), consumindo os endpoints auditados do backend. */
export async function exportManagementBase(base: ManagementBase): Promise<number> {
  const rows = await buildBase(base);
  downloadCSV(BASE_FILENAMES[base], rows);
  return rows.length;
}

/** Exporta as 5 bases em sequência, consumindo os endpoints auditados do backend. */
export async function exportAllManagementBases(): Promise<void> {
  const bases: ManagementBase[] = ['alunos', 'cursos', 'matriculas', 'progresso', 'certificados'];
  for (const [i, base] of bases.entries()) {
    const rows = await buildBase(base);
    // Pequeno intervalo entre downloads para o navegador não bloquear os arquivos seguintes.
    setTimeout(() => downloadCSV(BASE_FILENAMES[base], rows), i * 180);
  }
}
