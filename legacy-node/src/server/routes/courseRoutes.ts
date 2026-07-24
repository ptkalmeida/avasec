import { Router } from 'express';
import * as courseController from '../controllers/courseController';
import { requireAuth } from '../middlewares/auth';
import { requireActiveAccount } from '../middlewares/accountStatus';
import { requireRole } from '../middlewares/rbac';
import { validate } from '../middlewares/validate';
import { createCourseSchema, updateCourseSchema } from '../validators/courseValidators';
import { idSchema } from '../validators/common';
import { z } from 'zod';

export const courseRouter = Router();

// Catálogo público — mantém a página institucional acessível sem login (MVP do produto).
courseRouter.get('/', courseController.listCourses);

courseRouter.post(
  '/',
  requireAuth,
  requireActiveAccount,
  requireRole('instructor', 'admin'),
  validate(createCourseSchema),
  courseController.createCourse
);

courseRouter.put(
  '/:id',
  requireAuth,
  requireActiveAccount,
  requireRole('instructor', 'admin'),
  validate(z.object({ id: idSchema }), 'params'),
  validate(updateCourseSchema),
  courseController.updateCourse
);

courseRouter.delete(
  '/:id',
  requireAuth,
  requireActiveAccount,
  requireRole('instructor', 'admin'),
  validate(z.object({ id: idSchema }), 'params'),
  courseController.deleteCourse
);
