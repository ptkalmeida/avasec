// Schemas Zod reutilizados por vários validators de domínio.
import { z } from 'zod';

export const idSchema = z.string().trim().min(1, 'Identificador é obrigatório.').max(191);

export const emailSchema = z
  .string()
  .trim()
  .toLowerCase()
  .email('E-mail inválido.')
  .max(254);

export const nameSchema = z.string().trim().min(2, 'Nome deve ter ao menos 2 caracteres.').max(150);

export const passwordSchema = z
  .string()
  .min(6, 'Senha deve ter ao menos 6 caracteres.')
  .max(128);

export const roleSchema = z.enum(['student', 'instructor', 'admin']);

export const accountStatusSchema = z.enum(['active', 'blocked', 'pending_confirmation']);

export const requestStatusSchema = z.enum(['pending', 'approved', 'rejected']);

// Aceita 'YYYY-MM-DD', 'DD/MM/YYYY' ou um ISO datetime — os mesmos formatos já usados no app.
export const looseDateSchema = z
  .string()
  .trim()
  .refine((val) => {
    if (!val) return false;
    const isoLike = /^\d{4}-\d{2}-\d{2}/.test(val);
    const brLike = /^\d{2}\/\d{2}\/\d{4}/.test(val);
    return isoLike || brLike || !isNaN(Date.parse(val));
  }, 'Data inválida.');

export const attendancePercentSchema = z.coerce.number().min(0).max(100);

export const justificationTextSchema = z
  .string()
  .trim()
  .min(10, 'A justificativa deve ter ao menos 10 caracteres.')
  .max(4000, 'A justificativa não pode passar de 4000 caracteres.');

export const paginationQuerySchema = z.object({
  page: z.coerce.number().int().positive().optional(),
  pageSize: z.coerce.number().int().positive().max(100).optional(),
});
