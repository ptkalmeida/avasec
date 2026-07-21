// Quizzes, fórum de discussão e exercícios práticos. `senderName`/`studentName` sempre vêm
// da identidade autenticada (req.user), nunca do corpo enviado pelo cliente — impede que um
// usuário publique mensagens ou submissões em nome de outra pessoa.
import { prisma } from '../prisma';
import { Errors } from '../utils/ApiError';
import { Requester, ownRowsWhere } from '../utils/identity';

// ---------- QUIZZES ----------

export const listQuizzes = () => prisma.quiz.findMany({ include: { questions: true } });

export async function createQuiz(input: { id?: string; courseId: string; title: string; questions: any[] }) {
  const id = input.id || `quiz-${Date.now()}`;
  await prisma.quiz.upsert({
    where: { id },
    update: { courseId: input.courseId, title: input.title },
    create: { id, courseId: input.courseId, title: input.title },
  });
  const keptIds = input.questions.filter((q) => q.id).map((q) => q.id);
  await prisma.quizQuestion.deleteMany({ where: { quizId: id, id: { notIn: keptIds.length ? keptIds : [''] } } });
  for (const q of input.questions) {
    const qId = q.id || `${id}-q-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
    await prisma.quizQuestion.upsert({ where: { id: qId }, update: { ...q, id: undefined, quizId: id }, create: { ...q, id: qId, quizId: id } });
  }
  return prisma.quiz.findUnique({ where: { id }, include: { questions: true } });
}

export async function deleteQuiz(id: string) {
  await prisma.quizSubmission.deleteMany({ where: { quizId: id } });
  await prisma.quiz.delete({ where: { id } }).catch(() => null);
}

export async function listQuizSubmissions(requester: Requester) {
  if (requester.role === 'student') {
    return prisma.quizSubmission.findMany({ where: ownRowsWhere(requester) });
  }
  return prisma.quizSubmission.findMany();
}

export async function submitQuiz(
  input: { courseId: string; quizId: string; scorePercent: number; passed: boolean },
  requester: Requester
) {
  if (requester.role !== 'student') {
    throw Errors.forbidden('Somente alunos podem responder quizzes.');
  }
  await prisma.quizSubmission.deleteMany({ where: { studentName: requester.name, quizId: input.quizId } });
  return prisma.quizSubmission.create({
    data: {
      id: `sub-${Date.now()}-${Math.floor(Math.random() * 1000)}`,
      studentName: requester.name,
      userId: requester.sub,
      courseId: input.courseId,
      quizId: input.quizId,
      scorePercent: input.scorePercent,
      passed: input.passed,
      submittedAt: new Date().toLocaleDateString('pt-BR') + ' às ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
    },
  });
}

// ---------- FÓRUM ----------

export const listForumMessages = () => prisma.forumMessage.findMany();

export async function createForumMessage(input: { courseId: string; text: string }, requester: Requester) {
  return prisma.forumMessage.create({
    data: {
      id: `forum-msg-${Date.now()}`,
      courseId: input.courseId,
      senderName: requester.name,
      senderUserId: requester.sub,
      senderRole: requester.role as any,
      text: input.text,
      timestamp: new Date().toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }),
      likes: 0,
      likedBy: [],
    },
  });
}

export async function toggleForumLike(messageId: string, requester: Requester) {
  const msg = await prisma.forumMessage.findUnique({ where: { id: messageId } });
  if (!msg) throw Errors.notFound('Mensagem não encontrada.');
  const likedBy = Array.isArray(msg.likedBy) ? (msg.likedBy as string[]) : [];
  const hasLiked = likedBy.includes(requester.name);
  const nextLikedBy = hasLiked ? likedBy.filter((u) => u !== requester.name) : [...likedBy, requester.name];
  return prisma.forumMessage.update({ where: { id: messageId }, data: { likedBy: nextLikedBy, likes: nextLikedBy.length } });
}

export async function deleteForumMessage(id: string, requester: Requester) {
  const msg = await prisma.forumMessage.findUnique({ where: { id } });
  if (!msg) return;
  const owns = msg.senderUserId ? msg.senderUserId === requester.sub : msg.senderName === requester.name;
  if (requester.role !== 'admin' && !owns) {
    throw Errors.forbidden('Você só pode remover as próprias mensagens.');
  }
  await prisma.forumMessage.delete({ where: { id } });
}

// ---------- EXERCÍCIOS PRÁTICOS ----------

export const listExercises = () => prisma.practicalExercise.findMany();

export async function createExercise(input: any) {
  const id = input.id || `exercise-${Date.now()}`;
  return prisma.practicalExercise.create({ data: { ...input, id } });
}

export async function updateExercise(id: string, updates: any) {
  return prisma.practicalExercise.update({ where: { id }, data: updates }).catch(() => {
    throw Errors.notFound('Exercício não encontrado.');
  });
}

export async function deleteExercise(id: string) {
  await prisma.practicalExercise.delete({ where: { id } }).catch(() => null);
}

export async function listExerciseSubmissions(requester: Requester) {
  if (requester.role === 'student') {
    return prisma.exerciseSubmission.findMany({ where: ownRowsWhere(requester) });
  }
  return prisma.exerciseSubmission.findMany();
}

export async function submitExercise(
  input: { exerciseId: string; submissionText: string; fileUrl?: string; fileName?: string },
  requester: Requester
) {
  if (requester.role !== 'student') {
    throw Errors.forbidden('Somente alunos podem enviar entregas de exercícios.');
  }
  const existing = await prisma.exerciseSubmission.findFirst({
    where: { exerciseId: input.exerciseId, studentName: requester.name },
  });
  const data = {
    exerciseId: input.exerciseId,
    studentName: requester.name,
    userId: requester.sub,
    submissionText: input.submissionText,
    fileUrl: input.fileUrl,
    fileName: input.fileName,
    submittedAt: new Date().toLocaleString('pt-BR'),
    status: 'pending' as const,
  };
  if (existing) {
    return prisma.exerciseSubmission.update({ where: { id: existing.id }, data });
  }
  return prisma.exerciseSubmission.create({ data: { ...data, id: `sub-${Date.now()}` } });
}

export async function gradeSubmission(
  submissionId: string,
  input: { score: number; feedback: string; status: 'approved' | 'rejected' | 'revision' },
  requester: Requester
) {
  if (requester.role === 'student') {
    throw Errors.forbidden('Alunos não podem corrigir entregas.');
  }
  return prisma.exerciseSubmission
    .update({
      where: { id: submissionId },
      data: {
        score: input.score,
        feedback: input.feedback,
        status: input.status,
        gradedAt: new Date().toLocaleString('pt-BR'),
        gradedBy: requester.name,
      },
    })
    .catch(() => {
      throw Errors.notFound('Entrega não encontrada.');
    });
}
