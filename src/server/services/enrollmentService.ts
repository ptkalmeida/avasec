// Progresso, matrícula (StudentEnrollment) e solicitações de admissão (AdmissionRequest).
// Regra geral de acesso: aluno só enxerga/edita os próprios dados; instrutor fica restrito
// aos cursos que leciona; admin tem visão e ação irrestritas.
// Fase de transição de identidade: autorização usa a FK (userId) quando a linha tem uma;
// linhas legadas sem FK ainda são comparadas por nome (ver utils/identity.ts).
import crypto from 'crypto';
import { prisma } from '../prisma';
import { Errors } from '../utils/ApiError';
import { Requester, resolveStudentUserId, ownRowsWhere } from '../utils/identity';
import { features } from '../../config/features';
import { DROPOUT_PENALTY_FREE_DAYS, DROPOUT_PENALTY_DAYS, courseMinAttendance } from '../../config/constants';

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

// ---------- AÇÕES DO PRÓPRIO ALUNO (matrícula/cancelamento/conclusão) ----------
// A regra de penalidade de cancelamento tardio vive AQUI, no servidor, e só é aplicada quando
// a feature flag penalidadesCancelamento está ligada. O cliente não decide dias nem penalidade.

async function getOwnEnrollment(requester: Requester) {
  return prisma.studentEnrollment.findFirst({ where: ownRowsWhere(requester) });
}

function hasActivePenalty(row: { dropOutPenaltyUntil: string | null } | null): boolean {
  if (!features.penalidadesCancelamento) return false;
  if (!row?.dropOutPenaltyUntil) return false;
  const until = new Date(row.dropOutPenaltyUntil).getTime();
  return !isNaN(until) && until > Date.now();
}

export async function selfEnroll(courseId: string, requester: Requester) {
  const course = await prisma.course.findUnique({ where: { id: courseId }, select: { id: true } });
  if (!course) throw Errors.notFound('Curso não encontrado.');

  const current = await getOwnEnrollment(requester);

  if (hasActivePenalty(current)) {
    throw Errors.forbidden(
      'Você está em período de restrição temporária de nova matrícula por cancelamento tardio. Aguarde o fim da restrição ou solicite liberação à coordenação.'
    );
  }
  if (current?.enrolledCourseId) {
    throw Errors.conflict('Você já possui uma matrícula ativa. Conclua ou cancele o curso atual antes de iniciar outro.');
  }

  const merged = {
    ...EMPTY_ENROLLMENT,
    completedCourseIds: (current?.completedCourseIds as string[] | undefined) ?? [],
    dropOutPenaltyUntil: current?.dropOutPenaltyUntil ?? null,
    enrolledCourseId: courseId,
    enrolledAt: new Date().toISOString(),
  };
  const saved = await persistEnrollment(prisma, requester.name, merged, requester.sub);
  return { enrollment: toPublicEnrollment(saved) };
}

export async function selfDrop(courseId: string, requester: Requester) {
  const current = await getOwnEnrollment(requester);
  if (!current?.enrolledCourseId || current.enrolledCourseId !== courseId) {
    throw Errors.badRequest('Você não possui matrícula ativa neste curso.');
  }

  // Dias contados a partir do enrolledAt REAL persistido — nunca de um valor vindo do cliente.
  let penaltyApplied = false;
  let penaltyUntil: string | null = current.dropOutPenaltyUntil ?? null;
  if (features.penalidadesCancelamento && current.enrolledAt) {
    const enrolledAtMs = new Date(current.enrolledAt).getTime();
    const daysEnrolled = isNaN(enrolledAtMs) ? 0 : Math.ceil((Date.now() - enrolledAtMs) / (1000 * 60 * 60 * 24));
    if (daysEnrolled > DROPOUT_PENALTY_FREE_DAYS) {
      penaltyApplied = true;
      const d = new Date();
      d.setDate(d.getDate() + DROPOUT_PENALTY_DAYS);
      penaltyUntil = d.toISOString();
    }
  }

  const { studentName: _s, id: _i, userId: _u, ...rest } = current as any;
  const merged = { ...rest, enrolledCourseId: null, enrolledAt: null, dropOutPenaltyUntil: penaltyUntil };
  const saved = await persistEnrollment(prisma, requester.name, merged, current.userId ?? requester.sub);

  return { enrollment: toPublicEnrollment(saved), penaltyApplied };
}

export async function selfComplete(courseId: string, requester: Requester) {
  const current = await getOwnEnrollment(requester);
  if (!current?.enrolledCourseId || current.enrolledCourseId !== courseId) {
    throw Errors.badRequest('Você não possui matrícula ativa neste curso.');
  }

  // Conclusão exige o critério de frequência do curso — verificado no servidor.
  const course = await prisma.course.findUnique({
    where: { id: courseId },
    include: { lessons: true, liveSessions: true },
  });
  if (!course) throw Errors.notFound('Curso não encontrado.');

  const progress = await prisma.studentProgress.findUnique({
    where: { studentName_courseId: { studentName: requester.name, courseId } },
  });
  const totalActivities = course.lessons.length + course.liveSessions.length;
  const done =
    (Array.isArray(progress?.completedLessons) ? (progress!.completedLessons as unknown[]).length : 0) +
    (Array.isArray(progress?.attendedLiveSessions) ? (progress!.attendedLiveSessions as unknown[]).length : 0);
  const attendance = totalActivities === 0 ? 0 : Math.min(100, Math.round((done / totalActivities) * 100));
  const minAttendance = courseMinAttendance(course);
  if (attendance < minAttendance) {
    throw Errors.forbidden(
      `Critério de frequência ainda não atingido para concluir o curso (${attendance}% de ${minAttendance}% exigidos).`
    );
  }

  const { studentName: _s, id: _i, userId: _u, ...rest } = current as any;
  const completed = Array.from(new Set([...(((current.completedCourseIds as string[]) ?? [])), courseId]));
  const merged = { ...rest, enrolledCourseId: null, enrolledAt: null, completedCourseIds: completed };
  const saved = await persistEnrollment(prisma, requester.name, merged, current.userId ?? requester.sub);

  return { enrollment: toPublicEnrollment(saved) };
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
