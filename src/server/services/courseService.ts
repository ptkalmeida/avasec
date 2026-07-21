// Regras de negócio de cursos: CRUD com sincronização de aulas/documentos/sessões ao vivo
// aninhadas, sempre em transação para não deixar o curso em estado parcial.
import { prisma } from '../prisma';
import { Errors } from '../utils/ApiError';
import type { Course, Lesson } from '../../types';

const COURSE_INCLUDE = { lessons: { include: { documents: true } }, liveSessions: true } as const;

function toCourseDTO(course: any): Course {
  return {
    ...course,
    lessons: (course.lessons ?? [])
      .map((l: any) => ({ ...l, documents: l.documents ?? [] }))
      .sort((a: Lesson, b: Lesson) => a.order - b.order),
    liveSessions: course.liveSessions ?? [],
  };
}

export async function listCourses() {
  const courses = await prisma.course.findMany({ include: COURSE_INCLUDE });
  return courses.map(toCourseDTO);
}

export async function getCourseById(id: string) {
  const course = await prisma.course.findUnique({ where: { id }, include: COURSE_INCLUDE });
  if (!course) throw Errors.notFound('Curso não encontrado.');
  return toCourseDTO(course);
}

async function assertCourseOwnership(courseId: string, requester: { role: string; name: string }) {
  const course = await prisma.course.findUnique({ where: { id: courseId }, select: { instructorName: true } });
  if (!course) throw Errors.notFound('Curso não encontrado.');
  if (requester.role === 'admin') return;
  if (requester.role === 'instructor' && course.instructorName === requester.name) return;
  throw Errors.forbidden('Você só pode gerenciar cursos vinculados ao seu próprio perfil de instrutor.');
}

export async function createCourse(input: Partial<Course>, requester: { role: string; name: string }) {
  const { lessons = [], liveSessions = [], ...courseData } = input;

  // Instrutor só pode criar curso em seu próprio nome — impede assumir a autoria de outro professor.
  const instructorName = requester.role === 'admin' ? courseData.instructorName ?? requester.name : requester.name;

  const id = courseData.id || `course-${Date.now()}`;

  await prisma.$transaction(async (tx) => {
    await tx.course.create({ data: { ...(courseData as any), id, instructorName } });

    for (const lesson of lessons) {
      const { documents, ...lessonData } = lesson;
      const created = await tx.lesson.create({ data: { ...lessonData, id: lessonData.id || `lesson-${id}-${Date.now()}`, courseId: id } });
      for (const doc of documents ?? []) {
        await tx.lessonDocument.create({ data: { ...doc, id: doc.id || `doc-${created.id}-${Date.now()}`, lessonId: created.id } });
      }
    }
    for (const session of liveSessions) {
      await tx.liveSession.create({ data: { ...session, id: session.id || `live-${id}-${Date.now()}`, courseId: id } });
    }
  });

  return getCourseById(id);
}

async function syncCourseLessons(tx: any, courseId: string, lessons: Lesson[]) {
  const keptLessonIds = lessons.filter((l) => l.id).map((l) => l.id);
  await tx.lesson.deleteMany({ where: { courseId, id: { notIn: keptLessonIds.length ? keptLessonIds : [''] } } });

  for (const lesson of lessons) {
    const { documents, ...lessonData } = lesson;
    const lessonId = lessonData.id || `lesson-${courseId}-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
    await tx.lesson.upsert({
      where: { id: lessonId },
      update: { ...lessonData, id: undefined, courseId },
      create: { ...lessonData, id: lessonId, courseId },
    });

    const keptDocIds = (documents ?? []).filter((d) => d.id).map((d) => d.id);
    await tx.lessonDocument.deleteMany({ where: { lessonId, id: { notIn: keptDocIds.length ? keptDocIds : [''] } } });
    for (const doc of documents ?? []) {
      const docId = doc.id || `doc-${lessonId}-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
      await tx.lessonDocument.upsert({
        where: { id: docId },
        update: { ...doc, id: undefined, lessonId },
        create: { ...doc, id: docId, lessonId },
      });
    }
  }
}

async function syncCourseLiveSessions(tx: any, courseId: string, liveSessions: any[]) {
  const keptIds = liveSessions.filter((s) => s.id).map((s) => s.id);
  await tx.liveSession.deleteMany({ where: { courseId, id: { notIn: keptIds.length ? keptIds : [''] } } });
  for (const session of liveSessions) {
    const sessionId = session.id || `live-${courseId}-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
    await tx.liveSession.upsert({
      where: { id: sessionId },
      update: { ...session, id: undefined, courseId },
      create: { ...session, id: sessionId, courseId },
    });
  }
}

export async function updateCourse(
  courseId: string,
  updates: Partial<Course>,
  requester: { role: string; name: string }
) {
  await assertCourseOwnership(courseId, requester);
  const { lessons, liveSessions, ...scalarUpdates } = updates;

  await prisma.$transaction(async (tx) => {
    if (Object.keys(scalarUpdates).length > 0) {
      await tx.course.update({ where: { id: courseId }, data: scalarUpdates as any });
    }
    if (lessons) await syncCourseLessons(tx, courseId, lessons);
    if (liveSessions) await syncCourseLiveSessions(tx, courseId, liveSessions);
  });

  return getCourseById(courseId);
}

export async function deleteCourse(courseId: string, requester: { role: string; name: string }) {
  await assertCourseOwnership(courseId, requester);
  // onDelete: Cascade remove aulas, documentos e sessões ao vivo na mesma operação atômica do banco.
  await prisma.course.delete({ where: { id: courseId } });
}
