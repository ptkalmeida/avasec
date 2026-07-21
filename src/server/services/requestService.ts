// Solicitações acadêmicas (certificado, histórico, matrícula, outro) — "justificativas" do aluno.
import { prisma } from '../prisma';
import { Errors } from '../utils/ApiError';
import { Requester, resolveStudentUserId, ownRowsWhere } from '../utils/identity';

export async function listAcademicRequests(requester: Requester) {
  if (requester.role === 'student') {
    return prisma.academicRequest.findMany({ where: ownRowsWhere(requester), orderBy: { submittedAt: 'desc' } });
  }
  // Instrutor e admin acompanham todas as solicitações (secretaria acadêmica é centralizada).
  return prisma.academicRequest.findMany({ orderBy: { submittedAt: 'desc' } });
}

export async function createAcademicRequest(
  input: { studentName: string; type: string; description: string; courseTitle?: string },
  requester: Requester
) {
  if (requester.role === 'student' && input.studentName !== requester.name) {
    throw Errors.forbidden('Você só pode enviar solicitações em seu próprio nome.');
  }

  const userId = await resolveStudentUserId(input.studentName, requester);

  return prisma.academicRequest.create({
    data: {
      id: `req-${Date.now()}`,
      studentName: input.studentName,
      userId,
      type: input.type as any,
      description: input.description,
      courseTitle: input.courseTitle,
      status: 'pending',
      submittedAt: new Date().toLocaleDateString('pt-BR'),
    },
  });
}

export async function updateAcademicRequestStatus(id: string, status: 'approved' | 'rejected') {
  const updated = await prisma.academicRequest.update({ where: { id }, data: { status } }).catch(() => null);
  if (!updated) throw Errors.notFound('Solicitação acadêmica não encontrada.');
  return updated;
}
