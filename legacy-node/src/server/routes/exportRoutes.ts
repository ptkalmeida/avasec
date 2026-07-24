import { Router } from 'express';
import * as exportController from '../controllers/exportController';
import { requireAuth } from '../middlewares/auth';
import { requireActiveAccount } from '../middlewares/accountStatus';
import { requireRole } from '../middlewares/rbac';
import { exportLimiter } from '../middlewares/rateLimiters';

export const exportRouter = Router();

// Dados Gerenciais: exclusivo para admin, com rate limit e auditoria (quem/quando/qual base).
exportRouter.get(
  '/:dataset',
  exportLimiter,
  requireAuth,
  requireActiveAccount,
  requireRole('admin'),
  exportController.exportDataset
);
