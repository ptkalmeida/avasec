import { z } from 'zod';
import { idSchema, looseDateSchema } from './common';

export const lessonDocumentSchema = z.object({
  id: idSchema.optional(),
  title: z.string().trim().min(1).max(200),
  type: z.enum(['pdf', 'doc', 'url', 'drive', 'outro']),
  url: z.string().trim().min(1).max(2000),
  size: z.string().trim().max(30).optional(),
});

export const lessonSchema = z.object({
  id: idSchema.optional(),
  courseId: idSchema.optional(),
  title: z.string().trim().min(1).max(200),
  duration: z.string().trim().min(1).max(30),
  videoUrl: z.string().trim().max(2000).optional(),
  content: z.string().trim().max(20000).optional(),
  order: z.coerce.number().int().min(0),
  documents: z.array(lessonDocumentSchema).optional(),
});

export const liveSessionSchema = z.object({
  id: idSchema.optional(),
  courseId: idSchema.optional(),
  title: z.string().trim().min(1).max(200),
  scheduledAt: z.string().trim().min(1).max(100),
  durationMinutes: z.coerce.number().int().positive().max(600),
  meetingLink: z.string().trim().min(1).max(2000),
  isLive: z.boolean().optional(),
});

export const createCourseSchema = z.object({
  id: idSchema.optional(),
  title: z.string().trim().min(3, 'Título deve ter ao menos 3 caracteres.').max(200),
  description: z.string().trim().min(10, 'Descrição deve ter ao menos 10 caracteres.').max(5000),
  category: z.string().trim().min(1).max(120),
  thumbnail: z.string().trim().min(1).max(2000),
  instructorName: z.string().trim().min(1).max(150),
  coverImage: z.string().trim().max(2000).optional(),
  courseType: z.enum(['fixo', 'ao_vivo']).optional(),
  hasChat: z.boolean().optional(),
  minAttendance: z.coerce.number().min(0).max(100).optional(),
  contractExpirationDate: looseDateSchema.optional(),
  areaTematica: z.string().trim().max(150).optional(),
  cargaHoraria: z.coerce.number().int().positive().max(2000).optional(),
  modalidade: z.string().trim().max(60).optional(),
  nivel: z.string().trim().max(60).optional(),
  emiteCertificado: z.boolean().optional(),
  statusCurso: z.string().trim().max(60).optional(),
  lessons: z.array(lessonSchema).optional(),
  liveSessions: z.array(liveSessionSchema).optional(),
});

// Atualização parcial: mesmos campos, todos opcionais.
export const updateCourseSchema = createCourseSchema.partial();
