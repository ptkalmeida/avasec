import { Router } from 'express';
import * as settingsController from '../controllers/settingsController';
import { requireAuth } from '../middlewares/auth';
import { requireActiveAccount } from '../middlewares/accountStatus';
import { requireRole } from '../middlewares/rbac';

export const settingsRouter = Router();

settingsRouter.get('/', settingsController.getSettings);
settingsRouter.put('/', requireAuth, requireActiveAccount, requireRole('admin'), settingsController.updateSettings);
