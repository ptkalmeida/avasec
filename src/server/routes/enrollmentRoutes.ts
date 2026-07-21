import { Router } from 'express';
import { z } from 'zod';
import * as enrollmentController from '../controllers/enrollmentController';
import { requireAuth } from '../middlewares/auth';
import { requireActiveAccount } from '../middlewares/accountStatus';
import { requireRole } from '../middlewares/rbac';
import { validate } from '../middlewares/validate';
import { enrollmentLimiter } from '../middlewares/rateLimiters';
import {
  progressUpdateSchema,
  enrollmentUpdateSchema,
  admissionRequestSchema,
  admissionStatusUpdateSchema,
} from '../validators/enrollmentValidators';
import { nameSchema, idSchema } from '../validators/common';

export const progressRouter = Router();
progressRouter.get('/', requireAuth, requireActiveAccount, enrollmentController.getProgress);
progressRouter.post(
  '/',
  requireAuth,
  requireActiveAccount,
  requireRole('student', 'admin'),
  validate(progressUpdateSchema),
  enrollmentController.upsertProgress
);

export const enrollmentRouter = Router();
enrollmentRouter.get('/', requireAuth, requireActiveAccount, enrollmentController.getEnrollments);
enrollmentRouter.put(
  '/:studentName',
  requireAuth,
  requireActiveAccount,
  requireRole('instructor', 'admin'),
  validate(z.object({ studentName: nameSchema }), 'params'),
  validate(enrollmentUpdateSchema),
  enrollmentController.upsertEnrollment
);

export const admissionRouter = Router();
admissionRouter.get('/', requireAuth, requireActiveAccount, enrollmentController.listAdmissions);
admissionRouter.post(
  '/',
  enrollmentLimiter,
  requireAuth,
  requireActiveAccount,
  requireRole('student', 'admin'),
  validate(admissionRequestSchema),
  enrollmentController.createAdmission
);
admissionRouter.put(
  '/:id',
  requireAuth,
  requireActiveAccount,
  requireRole('instructor', 'admin'),
  validate(z.object({ id: idSchema }), 'params'),
  validate(admissionStatusUpdateSchema),
  enrollmentController.updateAdmissionStatus
);
