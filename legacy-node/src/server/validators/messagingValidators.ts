import { z } from 'zod';
import { idSchema, nameSchema } from './common';

export const chatMessageSchema = z.object({
  sessionId: idSchema,
  text: z.string().trim().min(1).max(2000),
});

export const directMessageSchema = z.object({
  studentName: nameSchema,
  text: z.string().trim().min(1).max(2000),
});
