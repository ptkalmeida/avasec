import { Router } from 'express';
import * as auditController from '../controllers/auditController';
import { requireAuth, attachUserIfPresent } from '../middlewares/auth';
import { requireActiveAccount } from '../middlewares/accountStatus';
import { requireRole } from '../middlewares/rbac';
import { validate } from '../middlewares/validate';
import { clientEventSchema } from '../validators/auditValidators';
import { paginationQuerySchema } from '../validators/common';

export const auditRouter = Router();

// Trilha de auditoria completa: exclusiva para admin.
auditRouter.get(
  '/',
  requireAuth,
  requireActiveAccount,
  requireRole('admin'),
  validate(paginationQuerySchema, 'query'),
  auditController.listSecurityLogs
);

// Eventos de UI de baixo risco (navegação, narração) — aceita anônimo, mas nunca confia no
// nome/papel enviado pelo cliente (usa a identidade do token, se houver).
auditRouter.post('/', attachUserIfPresent, validate(clientEventSchema), auditController.recordClientEvent);

auditRouter.delete('/', requireAuth, requireActiveAccount, requireRole('admin'), auditController.clearSecurityLogs);
