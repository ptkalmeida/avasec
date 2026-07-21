import { Router } from 'express';
import { z } from 'zod';
import * as requestController from '../controllers/requestController';
import { requireAuth } from '../middlewares/auth';
import { requireActiveAccount } from '../middlewares/accountStatus';
import { requireRole } from '../middlewares/rbac';
import { validate } from '../middlewares/validate';
import { justificationLimiter } from '../middlewares/rateLimiters';
import { academicRequestSchema, academicRequestStatusSchema } from '../validators/requestValidators';
import { idSchema } from '../validators/common';

export const academicRequestRouter = Router();

academicRequestRouter.get('/', requireAuth, requireActiveAccount, requestController.listAcademicRequests);

academicRequestRouter.post(
  '/',
  justificationLimiter,
  requireAuth,
  requireActiveAccount,
  requireRole('student', 'admin'),
  validate(academicRequestSchema),
  requestController.createAcademicRequest
);

academicRequestRouter.put(
  '/:id',
  requireAuth,
  requireActiveAccount,
  requireRole('instructor', 'admin'),
  validate(z.object({ id: idSchema }), 'params'),
  validate(academicRequestStatusSchema),
  requestController.updateAcademicRequestStatus
);
