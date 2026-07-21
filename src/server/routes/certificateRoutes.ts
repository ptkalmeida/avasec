import { Router } from 'express';
import { z } from 'zod';
import * as certificateController from '../controllers/certificateController';
import { requireAuth } from '../middlewares/auth';
import { requireActiveAccount } from '../middlewares/accountStatus';
import { requireRole } from '../middlewares/rbac';
import { validate } from '../middlewares/validate';
import { certLookupLimiter } from '../middlewares/rateLimiters';
import { issueCertificateSchema } from '../validators/certificateValidators';
import { certLookupQuerySchema, idParamSchema } from '../validators/requestValidators';
import { paginationQuerySchema, idSchema } from '../validators/common';

export const certificateRouter = Router();

// Verificação pública de autenticidade — sem login, com rate limit contra scraping.
certificateRouter.get(
  '/verify',
  certLookupLimiter,
  validate(certLookupQuerySchema, 'query'),
  certificateController.verifyCertificate
);

// Listagem interna: aluno vê só os próprios; instrutor/admin veem todos.
certificateRouter.get(
  '/',
  requireAuth,
  requireActiveAccount,
  validate(paginationQuerySchema, 'query'),
  certificateController.listCertificates
);

certificateRouter.post(
  '/',
  requireAuth,
  requireActiveAccount,
  validate(issueCertificateSchema),
  certificateController.issueCertificate
);

certificateRouter.delete(
  '/:id',
  requireAuth,
  requireActiveAccount,
  requireRole('admin'),
  validate(z.object({ id: idSchema }), 'params'),
  certificateController.deleteCertificate
);
