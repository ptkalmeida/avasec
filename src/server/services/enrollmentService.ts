// Progresso, matrícula (StudentEnrollment) e solicitações de admissão (AdmissionRequest).
// Regra geral de acesso: aluno só enxerga/edita os próprios dados; instrutor fica restrito
// aos cursos que leciona; admin tem visão e ação irrestritas.
// Fase de transição de identidade: autorização usa a FK (userId) quando a linha tem uma;
// linhas legadas sem FK ainda são comparadas por nome (ver utils/identity.ts).
import crypto from 'crypto';
import { prisma } from '../prisma';
import { Errors } from '../utils/ApiError';
import { Requester, resolveStudentUserId, ownRowsWhere } from '../utils/identity';

async function instructorCourseIds(requester: Requester): Promise<string[]> {
  const courses = await prisma.course.findMany({
    where: {
      OR: [
        { instructorId: requester.sub },
        { AND: [{ instructorId: null }, { instructorName: requester.name }] },
      ],
    },
    select: { id: true },
  });
  return courses.map((c) => c.id);
}

// ---------- PROGRESSO ----------

export async function getProgress(requestedStudentName: string | undefined, requester: Requester) {
  if (requester.role === 'student') {
    if (requestedStudentName && requestedStudentName !== requester.name) {
      throw Errors.forbidden('Você só pode consultar o próprio progresso.');
    }
    return prisma.studentProgress.findMany({ where: ownRowsWhere(requester) });
  }

  if (requester.role === 'instructor') {
    const courseIds = await instructorCourseIds(requester);
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

  const userId = await resolveStudentUserId(input.studentName, requester);
  const enrollment = await prisma.studentEnrollment.findUnique({
    where: { studentName: input.studentName },
    select: { id: true },
  });

  return prisma.studentProgress.upsert({
    where: { studentName_courseId: { studentName: input.studentName, courseId: input.courseId } },
    update: {
      completedLessons: input.completedLessons,
      attendedLiveSessions: input.attendedLiveSessions,
      userId,
      enrollmentId: enrollment?.id ?? null,
    },
    create: { ...input, userId, enrollmentId: enrollment?.id ?? null },
  });
}

// ---------- MATRÍCULA (StudentEnrollment) ----------

const EMPTY_ENROLLMENT = { enrolledCourseId: null, enrolledAt: null, completedCourseIds: [] as string[], dropOutPenaltyUntil: null };

export async function getEnrollments(requester: Requester) {
  if (requester.role === 'student') {
    const row = await prisma.studentEnrollment.findFirst({ where: ownRowsWhere(requester) });
    return { [requester.name]: row ? toPublicEnrollment(row) : EMPTY_ENROLLMENT };
  }

  const rows = await prisma.studentEnrollment.findMany();
  const map: Record<string, unknown> = {};
  for (const row of rows) map[row.studentName] = toPublicEnrollment(row);
  return map;
}

function toPublicEnrollment(row: { studentName: string; id?: string | null; userId?: string | null } & Record<string, unknown>) {
  const { studentName, id, userId, ...rest } = row;
  return rest;
}

async function assertInstructorCanManage(courseId: string | null, requester: Requester) {
  if (requester.role === 'admin') return;
  if (requester.role === 'instructor' && courseId) {
    const course = await prisma.course.findUnique({
      where: { id: courseId },
      select: { instructorName: true, instructorId: true },
    });
    if (course && (course.instructorId ? course.instructorId === requester.sub : course.instructorName === requester.name)) {
      return;
    }
  }
  throw Errors.forbidden('Você só pode gerenciar matrículas de cursos vinculados ao seu perfil.');
}

/** Aplica um upsert de matrícula garantindo id estável (enrollmentId) e FK de usuário. */
async function persistEnrollment(
  tx: any,
  studentName: string,
  merged: typeof EMPTY_ENROLLMENT & Record<string, unknown>,
  userId: string | null
) {
  return tx.studentEnrollment.upsert({
    where: { studentName },
    update: { ...merged, userId },
    create: {
      studentName,
      id: crypto.randomUUID(),
      userId,
      ...merged,
    },
  });
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
  const userId = current?.userId ?? (await resolveStudentUserId(studentName, requester));
  const { studentName: _s, id: _i, userId: _u, ...currentRest } = (current as any) ?? {};
  const merged = { ...EMPTY_ENROLLMENT, ...currentRest, ...updates };

  const saved = await persistEnrollment(prisma, studentName, merged, userId);
  return toPublicEnrollment(saved);
}

// ---------- SOLICITAÇÕES DE ADMISSÃO ----------

export async function listAdmissions(requester: Requester) {
  if (requester.role === 'student') {
    return prisma.admissionRequest.findMany({ where: ownRowsWhere(requester) });
  }
  if (requester.role === 'instructor') {
    const courseIds = await instructorCourseIds(requester);
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

  const userId = await resolveStudentUserId(input.studentName, requester);

  return prisma.admissionRequest.create({
    data: {
      id: input.id || `adm-${Date.now()}`,
      studentName: input.studentName,
      userId,
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

  const studentUserId = admission.userId ?? (await resolveStudentUserId(admission.studentName, requester));

  // Aprovação efetiva a matrícula do aluno no curso na mesma transação — evita ficar com a
  // solicitação "aprovada" sem o aluno de fato matriculado.
  const [updated] = await prisma.$transaction(async (tx) => {
    const updatedRequest = await tx.admissionRequest.update({ where: { id }, data: { status } });
    if (status === 'approved') {
      const current = await tx.studentEnrollment.findUnique({ where: { studentName: admission.studentName } });
      const { studentName: _s, id: _i, userId: _u, ...currentRest } = (current as any) ?? {};
      const merged = {
        ...EMPTY_ENROLLMENT,
        ...currentRest,
        enrolledCourseId: admission.courseId,
        enrolledAt: new Date().toISOString(),
        dropOutPenaltyUntil: null,
      };
      await persistEnrollment(tx, admission.studentName, merged, studentUserId);
    }
    return [updatedRequest];
  });

  return updated;
}
