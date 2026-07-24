import { Router } from 'express';
import * as catalogController from '../controllers/catalogController';
import { requireAuth } from '../middlewares/auth';
import { requireActiveAccount } from '../middlewares/accountStatus';
import { requireRole } from '../middlewares/rbac';
import { validate } from '../middlewares/validate';
import { libraryItemSchema, webinarSchema } from '../validators/catalogValidators';

export const libraryRouter = Router();
libraryRouter.get('/', catalogController.listLibraryItems);
libraryRouter.post(
  '/',
  requireAuth,
  requireActiveAccount,
  requireRole('instructor', 'admin'),
  validate(libraryItemSchema),
  catalogController.createLibraryItem
);

export const webinarRouter = Router();
webinarRouter.get('/', catalogController.listWebinars);
webinarRouter.post(
  '/',
  requireAuth,
  requireActiveAccount,
  requireRole('instructor', 'admin'),
  validate(webinarSchema),
  catalogController.createWebinar
);
