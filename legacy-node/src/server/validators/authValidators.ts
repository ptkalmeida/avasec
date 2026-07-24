import { z } from 'zod';
import { emailSchema, nameSchema, passwordSchema, roleSchema, accountStatusSchema } from './common';

export const registerSchema = z.object({
  name: nameSchema,
  email: emailSchema,
  password: passwordSchema,
  role: roleSchema.optional(),
  cpf: z.string().trim().max(20).optional(),
  municipio: z.string().trim().max(120).optional(),
  uf: z.string().trim().max(2).optional(),
  areaInteresse: z.string().trim().max(120).optional(),
  dataCadastro: z.string().trim().max(30).optional(),
});

export const loginSchema = z
  .object({
    name: z.string().trim().min(1).max(150).optional(),
    email: emailSchema.optional(),
    password: z.string().min(1, 'Senha é obrigatória.').max(128),
  })
  .refine((data) => !!data.name || !!data.email, {
    message: 'Informe nome ou e-mail para login.',
    path: ['name'],
  });

export const changePasswordSchema = z.object({
  currentPassword: z.string().max(128).optional(),
  newPassword: passwordSchema,
});

export const updateAccountStatusSchema = z.object({
  status: accountStatusSchema,
});
