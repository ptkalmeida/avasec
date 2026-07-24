import { z } from 'zod';
import { idSchema } from './common';

export const libraryItemSchema = z.object({
  id: idSchema.optional(),
  title: z.string().trim().min(1).max(200),
  type: z.enum(['pdf', 'video', 'link']),
  category: z.string().trim().min(1).max(120),
  description: z.string().trim().max(2000).optional(),
  url: z.string().trim().min(1).max(2000),
});

export const webinarSchema = z.object({
  id: idSchema.optional(),
  title: z.string().trim().min(1).max(200),
  date: z.string().trim().min(1).max(30),
  time: z.string().trim().min(1).max(20),
  description: z.string().trim().max(2000),
  link: z.string().trim().min(1).max(2000),
  image: z.string().trim().max(2000).optional(),
});
