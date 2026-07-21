import { z } from 'zod';
import { idSchema, nameSchema } from './common';

export const issueCertificateSchema = z.object({
  studentName: nameSchema,
  courseId: idSchema,
});
