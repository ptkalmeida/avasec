import { Router } from 'express';
import * as authController from '../controllers/authController';
import { requireAuth, attachUserIfPresent } from '../middlewares/auth';
import { requireActiveAccount } from '../middlewares/accountStatus';
import { requireRole } from '../middlewares/rbac';
import { validate } from '../middlewares/validate';
import { loginLimiter, registerLimiter, passwordChangeLimiter } from '../middlewares/rateLimiters';
import { registerSchema, loginSchema, changePasswordSchema, updateAccountStatusSchema } from '../validators/authValidators';
import { paginationQuerySchema, idSchema } from '../validators/common';
import { z } from 'zod';

export const authRouter = Router();

// Público, mas detecta se um admin autenticado está criando a conta (provisionamento).
authRouter.post('/register', registerLimiter, attachUserIfPresent, validate(registerSchema), authController.register);

authRouter.post('/login', loginLimiter, validate(loginSchema), authController.login);

authRouter.get('/me', requireAuth, authController.me);

// Encerra a sessão do navegador limpando o cookie HttpOnly.
authRouter.post('/logout', authController.logout);

authRouter.put(
  '/password',
  passwordChangeLimiter,
  requireAuth,
  validate(changePasswordSchema),
  authController.changePassword
);

// Listagem de usuários: exige sessão ativa; o escopo (aluno/instrutor/admin) é resolvido no controller.
authRouter.get(
  '/users',
  requireAuth,
  requireActiveAccount,
  validate(paginationQuerySchema.extend({ role: z.enum(['student', 'instructor', 'admin']).optional() }), 'query'),
  authController.listUsers
);

authRouter.put(
  '/users/:id/status',
  requireAuth,
  requireActiveAccount,
  requireRole('admin'),
  validate(z.object({ id: idSchema }), 'params'),
  validate(updateAccountStatusSchema),
  authController.updateStatus
);

authRouter.delete(
  '/users/:id',
  requireAuth,
  requireActiveAccount,
  requireRole('admin'),
  validate(z.object({ id: idSchema }), 'params'),
  authController.removeUser
);
