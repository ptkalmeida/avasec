// Exportação de Dados Gerenciais — restrita a admin. Exporta somente as bases autorizadas:
// Alunos, Cursos, Matrículas, Progresso por Módulo e Certificados. Nunca inclui hash de senha
// ou qualquer outro dado de autenticação.
import { prisma } from '../prisma';
import { Errors } from '../utils/ApiError';

export const EXPORTABLE_DATASETS = ['students', 'courses', 'enrollments', 'progress', 'certificates'] as const;
export type ExportableDataset = (typeof EXPORTABLE_DATASETS)[number];

export async function exportDataset(dataset: ExportableDataset) {
  switch (dataset) {
    case 'students':
      return prisma.user.findMany({
        where: { role: 'student' },
        select: {
          id: true, name: true, email: true, status: true,
          municipio: true, uf: true, areaInteresse: true, dataCadastro: true, createdAt: true,
        },
      });
    case 'courses':
      return prisma.course.findMany({
        select: {
          id: true, title: true, category: true, instructorName: true, cargaHoraria: true,
          modalidade: true, nivel: true, statusCurso: true, emiteCertificado: true,
        },
      });
    case 'enrollments':
      return prisma.studentEnrollment.findMany();
    case 'progress':
      return prisma.studentProgress.findMany();
    case 'certificates':
      return prisma.certificate.findMany();
    default:
      throw Errors.badRequest('Base de dados não reconhecida.');
  }
}
