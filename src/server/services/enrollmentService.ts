// Progresso, matrícula (StudentEnrollment) e solicitações de admissão (AdmissionRequest).
// Regra geral de acesso: aluno só enxerga/edita os próprios dados; instrutor fica restrito
// aos cursos que leciona; admin tem visão e ação irrestritas.
import { prisma } from '../prisma';
import { Errors } from '../utils/ApiError';

type Requester = { role: string; name: string };

async function instructorCourseIds(instructorName: string): Promise<string[]> {
  const courses = await prisma.course.findMany({ where: { instructorName }, select: { id: true } });
  return courses.map((c) => c.id);
}

// ---------- PROGRESSO ----------

export async function getProgress(requestedStudentName: string | undefined, requester: Requester) {
  if (requester.role === 'student') {
    if (requestedStudentName && requestedStudentName !== requester.name) {
      throw Errors.forbidden('Você só pode consultar o próprio progresso.');
    }
    return prisma.studentProgress.findMany({ where: { studentName: requester.name } });
  }

  if (requester.role === 'instructor') {
    const courseIds = await instructorCourseIds(requester.name);
    return prisma.studentProgress.findMany({
      where: {
        courseId: { in: courseIds },
        ...(requestedStudentName ? { studentName: requestedStudentName } : {}),
      },
    });
  }

  // admin
  return prisma.studentProgress.findMany({
    where: requestedStudentName ? { studentName: requestedStudentName } : undefined,
  });
}

export async function upsertProgress(
  input: { studentName: string; courseId: string; completedLessons: string[]; attendedLiveSessions: string[] },
  requester: Requester
) {
  if (requester.role === 'student' && input.studentName !== requester.name) {
    throw Errors.forbidden('Você só pode atualizar o próprio progresso.');
  }
  if (requester.role === 'instructor') {
    throw Errors.forbidden('Instrutores não registram progresso em nome do aluno.');
  }

  return prisma.studentProgress.upsert({
    where: { studentName_courseId: { studentName: input.studentName, courseId: input.courseId } },
    update: { completedLessons: input.completedLessons, attendedLiveSessions: input.attendedLiveSessions },
    create: input,
  });
}

// ---------- MATRÍCULA (StudentEnrollment) ----------

const EMPTY_ENROLLMENT = { enrolledCourseId: null, enrolledAt: null, completedCourseIds: [] as string[], dropOutPenaltyUntil: null };

export async function getEnrollments(requester: Requester) {
  if (requester.role === 'student') {
    const row = await prisma.studentEnrollment.findUnique({ where: { studentName: requester.name } });
    return { [requester.name]: row ? stripName(row) : EMPTY_ENROLLMENT };
  }

  const rows = await prisma.studentEnrollment.findMany();
  const map: Record<string, unknown> = {};
  for (const row of rows) map[row.studentName] = stripName(row);
  return map;
}

function stripName<T extends { studentName: string }>(row: T) {
  const { studentName, ...rest } = row;
  return rest;
}

async function assertInstructorCanManage(courseId: string | null, requester: Requester) {
  if (requester.role === 'admin') return;
  if (requester.role === 'instructor' && courseId) {
    const course = await prisma.course.findUnique({ where: { id: courseId }, select: { instructorName: true } });
    if (course?.instructorName === requester.name) return;
  }
  throw Errors.forbidden('Você só pode gerenciar matrículas de cursos vinculados ao seu perfil.');
}

export async function upsertEnrollment(
  studentName: string,
  updates: {
    enrolledCourseId?: string | null;
    enrolledAt?: string | null;
    completedCourseIds?: string[];
    dropOutPenaltyUntil?: string | null;
  },
  requester: Requester
) {
  await assertInstructorCanManage(updates.enrolledCourseId ?? null, requester);

  const current = await prisma.studentEnrollment.findUnique({ where: { studentName } });
  const merged = { ...EMPTY_ENROLLMENT, ...(current ? stripName(current) : {}), ...updates };

  const saved = await prisma.studentEnrollment.upsert({
    where: { studentName },
    update: merged,
    create: { studentName, ...merged },
  });
  return stripName(saved);
}

// ---------- SOLICITAÇÕES DE ADMISSÃO ----------

export async function listAdmissions(requester: Requester) {
  if (requester.role === 'student') {
    return prisma.admissionRequest.findMany({ where: { studentName: requester.name } });
  }
  if (requester.role === 'instructor') {
    const courseIds = await instructorCourseIds(requester.name);
    return prisma.admissionRequest.findMany({ where: { courseId: { in: courseIds } } });
  }
  return prisma.admissionRequest.findMany();
}

export async function createAdmission(
  input: { id?: string; studentName: string; courseId: string },
  requester: Requester
) {
  if (requester.role === 'student' && input.studentName !== requester.name) {
    throw Errors.forbidden('Você só pode solicitar matrícula em seu próprio nome.');
  }

  const isDuplicate = await prisma.admissionRequest.findFirst({
    where: { studentName: input.studentName, courseId: input.courseId, status: 'pending' },
  });
  if (isDuplicate) {
    throw Errors.conflict('Matrícula pendente para este curso já registrada.');
  }

  return prisma.admissionRequest.create({
    data: {
      id: input.id || `adm-${Date.now()}`,
      studentName: input.studentName,
      courseId: input.courseId,
      status: 'pending',
      submittedAt: new Date().toLocaleDateString('pt-BR'),
    },
  });
}

export async function updateAdmissionStatus(id: string, status: 'approved' | 'rejected', requester: Requester) {
  const admission = await prisma.admissionRequest.findUnique({ where: { id } });
  if (!admission) throw Errors.notFound('Matrícula não encontrada.');

  await assertInstructorCanManage(admission.courseId, requester);

  // Aprovação efetiva a matrícula do aluno no curso na mesma transação — evita ficar com a
  // solicitação "aprovada" sem o aluno de fato matriculado.
  const [updated] = await prisma.$transaction(async (tx) => {
    const updatedRequest = await tx.admissionRequest.update({ where: { id }, data: { status } });
    if (status === 'approved') {
      const current = await tx.studentEnrollment.findUnique({ where: { studentName: admission.studentName } });
      const merged = {
        ...EMPTY_ENROLLMENT,
        ...(current ? stripName(current) : {}),
        enrolledCourseId: admission.courseId,
        enrolledAt: new Date().toISOString(),
        dropOutPenaltyUntil: null,
      };
      await tx.studentEnrollment.upsert({
        where: { studentName: admission.studentName },
        update: merged,
        create: { studentName: admission.studentName, ...merged },
      });
    }
    return [updatedRequest];
  });

  return updated;
}
