import { Router } from 'express';
import * as auditController from '../controllers/auditController';
import { requireAuth, attachUserIfPresent } from '../middlewares/auth';
import { requireActiveAccount } from '../middlewares/accountStatus';
import { requireRole } from '../middlewares/rbac';
import { validate } from '../middlewares/validate';
import { clientEventSchema } from '../validators/auditValidators';
import { paginationQuerySchema } from '../validators/common';

// Trilha de auditoria (SecurityLog): SOMENTE leitura/limpeza por admin.
// Não existe mais POST — a auditoria é gravada exclusivamente pelo servidor
// (auditService.logAudit) a partir das ações reais; o cliente não escreve nela.
export const auditRouter = Router();

auditRouter.get(
  '/',
  requireAuth,
  requireActiveAccount,
  requireRole('admin'),
  validate(paginationQuerySchema, 'query'),
  auditController.listSecurityLogs
);

auditRouter.delete('/', requireAuth, requireActiveAccount, requireRole('admin'), auditController.clearSecurityLogs);

// Telemetria (ClientEvent): eventos de UI de baixo risco enviados pelo frontend.
// Aceita anônimo, mas a identidade registrada vem do token (se houver), nunca do corpo.
export const telemetryRouter = Router();

telemetryRouter.post('/', attachUserIfPresent, validate(clientEventSchema), auditController.recordClientEvent);

telemetryRouter.get(
  '/',
  requireAuth,
  requireActiveAccount,
  requireRole('admin'),
  validate(paginationQuerySchema, 'query'),
  auditController.listClientEvents
);
