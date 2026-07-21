import { z } from 'zod';

export const clientEventSchema = z.object({
  action: z.string().trim().min(1).max(200),
  details: z.string().trim().min(1).max(1000),
  status: z.enum(['SUCCESS', 'WARNING', 'FAILED']).optional(),
});
