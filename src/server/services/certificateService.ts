// Emissão e consulta de certificados. A emissão NUNCA confia no percentual informado pelo
// cliente — recalcula a frequência a partir de curso + progresso reais no banco.
import crypto from 'crypto';
import { prisma } from '../prisma';
import { Errors } from '../utils/ApiError';

function computeAttendancePercent(course: { lessons: unknown[]; liveSessions: unknown[] }, progress: {
  completedLessons: unknown;
  attendedLiveSessions: unknown;
} | null) {
  const totalActivities = course.lessons.length + course.liveSessions.length;
  if (totalActivities === 0) return 0;
  const completedLessons = Array.isArray(progress?.completedLessons) ? (progress!.completedLessons as unknown[]).length : 0;
  const attendedLive = Array.isArray(progress?.attendedLiveSessions) ? (progress!.attendedLiveSessions as unknown[]).length : 0;
  return Math.min(100, Math.round(((completedLessons + attendedLive) / totalActivities) * 100));
}

export async function listCertificates(requester: { role: string; name: string }, skip: number, take: number) {
  const where = requester.role === 'student' ? { studentName: requester.name } : undefined;
  const [items, total] = await Promise.all([
    prisma.certificate.findMany({ where, skip, take, orderBy: { issueDate: 'desc' } }),
    prisma.certificate.count({ where }),
  ]);
  return { items, total };
}

/** Consulta pública de verificação — usada na página institucional sem exigir login.
 * Retorna só o certificado que bate com o termo buscado (id, hash ou nome do aluno). */
export async function verifyCertificatePublic(query: string) {
  const trimmed = query.trim();
  const cert = await prisma.certificate.findFirst({
    where: {
      OR: [
        { id: { equals: trimmed } },
        { verificationHash: { equals: trimmed } },
        { studentName: { contains: trimmed } },
      ],
    },
  });
  return cert;
}

export async function issueCertificate(
  input: { studentName: string; courseId: string },
  requester: { role: string; name: string }
) {
  if (requester.role === 'student' && input.studentName !== requester.name) {
    throw Errors.forbidden('Você só pode emitir certificado para si mesmo.');
  }

  const course = await prisma.course.findUnique({
    where: { id: input.courseId },
    include: { lessons: true, liveSessions: true },
  });
  if (!course) throw Errors.notFound('Curso não encontrado.');

  // Idempotência: se já existe certificado para este aluno+curso, apenas o retorna.
  const existing = await prisma.certificate.findUnique({
    where: { studentName_courseId: { studentName: input.studentName, courseId: input.courseId } },
  });
  if (existing) return existing;

  const progress = await prisma.studentProgress.findUnique({
    where: { studentName_courseId: { studentName: input.studentName, courseId: input.courseId } },
  });

  const attendancePercent = computeAttendancePercent(course, progress as any);
  const minAttendance = course.minAttendance ?? 70;

  if (attendancePercent < minAttendance) {
    throw Errors.forbidden(
      `Critério de frequência ainda não atingido para emissão do certificado (${attendancePercent}% de ${minAttendance}% exigidos).`
    );
  }

  const hashHex = crypto.randomBytes(8).toString('hex').toUpperCase();
  const certificate = await prisma.certificate.create({
    data: {
      id: `cert-${course.id}-${hashHex}`,
      studentName: input.studentName,
      courseId: course.id,
      courseTitle: course.title,
      issueDate: new Date().toLocaleDateString('pt-BR'),
      attendancePercent,
      verificationHash: `AVA-${hashHex}`,
    },
  });

  return certificate;
}

export async function deleteCertificate(id: string) {
  await prisma.certificate.delete({ where: { id } }).catch(() => null);
}
