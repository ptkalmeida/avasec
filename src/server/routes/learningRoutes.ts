import { Router } from 'express';
import { z } from 'zod';
import * as learningController from '../controllers/learningController';
import { requireAuth } from '../middlewares/auth';
import { requireActiveAccount } from '../middlewares/accountStatus';
import { requireRole } from '../middlewares/rbac';
import { validate } from '../middlewares/validate';
import {
  quizSchema,
  quizSubmissionSchema,
  forumMessageSchema,
  practicalExerciseSchema,
  practicalExerciseUpdateSchema,
  exerciseSubmissionSchema,
  gradeSubmissionSchema,
} from '../validators/learningValidators';
import { idSchema } from '../validators/common';

const idParams = validate(z.object({ id: idSchema }), 'params');

export const quizRouter = Router();
quizRouter.get('/', learningController.listQuizzes);
quizRouter.post('/', requireAuth, requireActiveAccount, requireRole('instructor', 'admin'), validate(quizSchema), learningController.createQuiz);
quizRouter.delete('/:id', requireAuth, requireActiveAccount, requireRole('instructor', 'admin'), idParams, learningController.deleteQuiz);

export const quizSubmissionRouter = Router();
quizSubmissionRouter.get('/', requireAuth, requireActiveAccount, learningController.listQuizSubmissions);
quizSubmissionRouter.post(
  '/',
  requireAuth,
  requireActiveAccount,
  requireRole('student'),
  validate(quizSubmissionSchema),
  learningController.submitQuiz
);

export const forumRouter = Router();
forumRouter.get('/', requireAuth, requireActiveAccount, learningController.listForumMessages);
forumRouter.post('/', requireAuth, requireActiveAccount, validate(forumMessageSchema), learningController.createForumMessage);
forumRouter.put('/:id/like', requireAuth, requireActiveAccount, idParams, learningController.toggleForumLike);
forumRouter.delete('/:id', requireAuth, requireActiveAccount, idParams, learningController.deleteForumMessage);

export const exerciseRouter = Router();
exerciseRouter.get('/', requireAuth, requireActiveAccount, learningController.listExercises);
exerciseRouter.post(
  '/',
  requireAuth,
  requireActiveAccount,
  requireRole('instructor', 'admin'),
  validate(practicalExerciseSchema),
  learningController.createExercise
);
exerciseRouter.put(
  '/:id',
  requireAuth,
  requireActiveAccount,
  requireRole('instructor', 'admin'),
  idParams,
  validate(practicalExerciseUpdateSchema),
  learningController.updateExercise
);
exerciseRouter.delete('/:id', requireAuth, requireActiveAccount, requireRole('instructor', 'admin'), idParams, learningController.deleteExercise);

export const exerciseSubmissionRouter = Router();
exerciseSubmissionRouter.get('/', requireAuth, requireActiveAccount, learningController.listExerciseSubmissions);
exerciseSubmissionRouter.post(
  '/',
  requireAuth,
  requireActiveAccount,
  requireRole('student'),
  validate(exerciseSubmissionSchema),
  learningController.submitExercise
);
exerciseSubmissionRouter.put(
  '/:id/grade',
  requireAuth,
  requireActiveAccount,
  requireRole('instructor', 'admin'),
  idParams,
  validate(gradeSubmissionSchema),
  learningController.gradeSubmission
);
