import { z } from 'zod';
import { idSchema, justificationTextSchema, nameSchema } from './common';

export const academicRequestSchema = z.object({
  studentName: nameSchema,
  type: z.enum(['certificado', 'historico', 'matricula', 'outro']),
  description: justificationTextSchema,
  courseTitle: z.string().trim().max(200).optional(),
});

export const academicRequestStatusSchema = z.object({
  status: z.enum(['approved', 'rejected']),
});

export const certLookupQuerySchema = z.object({
  q: z.string().trim().min(1, 'Informe o código ou nome para consulta.').max(200),
});

export const idParamSchema = z.object({
  id: idSchema,
});
