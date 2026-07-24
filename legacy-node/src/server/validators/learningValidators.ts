import { z } from 'zod';
import { idSchema } from './common';

export const quizQuestionSchema = z.object({
  id: idSchema.optional(),
  questionText: z.string().trim().min(1).max(2000),
  options: z.array(z.string().trim().min(1).max(500)).min(2).max(10),
  correctOptionIndex: z.coerce.number().int().min(0),
  explanation: z.string().trim().max(2000).optional(),
  reviewMessage: z.string().trim().max(2000).optional(),
  recommendedModule: z.string().trim().max(200).optional(),
  allowRetry: z.boolean().optional(),
});

export const quizSchema = z.object({
  id: idSchema.optional(),
  courseId: idSchema,
  title: z.string().trim().min(1).max(200),
  questions: z.array(quizQuestionSchema).min(1),
});

export const quizSubmissionSchema = z.object({
  courseId: idSchema,
  quizId: idSchema,
  scorePercent: z.coerce.number().min(0).max(100),
  passed: z.boolean(),
});

export const forumMessageSchema = z.object({
  courseId: idSchema,
  text: z.string().trim().min(1, 'Mensagem não pode ser vazia.').max(3000),
});

export const forumLikeSchema = z.object({});

export const practicalExerciseSchema = z.object({
  id: idSchema.optional(),
  courseId: idSchema,
  title: z.string().trim().min(1).max(200),
  description: z.string().trim().min(1).max(5000),
  instructions: z.string().trim().min(1).max(5000),
  maxPoints: z.coerce.number().int().positive().max(1000),
  dueDate: z.string().trim().max(30).optional(),
});

export const practicalExerciseUpdateSchema = practicalExerciseSchema.partial();

export const exerciseSubmissionSchema = z.object({
  exerciseId: idSchema,
  submissionText: z.string().trim().min(1, 'Escreva sua resposta antes de enviar.').max(10000),
  fileUrl: z.string().trim().max(2000).optional(),
  fileName: z.string().trim().max(300).optional(),
});

export const gradeSubmissionSchema = z.object({
  score: z.coerce.number().min(0).max(1000),
  feedback: z.string().trim().max(3000),
  status: z.enum(['approved', 'rejected', 'revision']),
});
