import type { Response } from 'express';
import { asyncHandler } from '../utils/asyncHandler';
import { logAudit } from '../services/auditService';
import * as learningService from '../services/learningService';
import type { AuthedRequest } from '../middlewares/auth';

export const listQuizzes = asyncHandler(async (_req: AuthedRequest, res: Response) => {
  res.json(await learningService.listQuizzes());
});

export const createQuiz = asyncHandler(async (req: AuthedRequest, res: Response) => {
  res.status(201).json(await learningService.createQuiz(req.body));
});

export const deleteQuiz = asyncHandler(async (req: AuthedRequest, res: Response) => {
  await learningService.deleteQuiz(req.params.id);
  res.json({ success: true });
});

export const listQuizSubmissions = asyncHandler(async (req: AuthedRequest, res: Response) => {
  res.json(await learningService.listQuizSubmissions(req.user!));
});

export const submitQuiz = asyncHandler(async (req: AuthedRequest, res: Response) => {
  res.status(201).json(await learningService.submitQuiz(req.body, req.user!));
});

export const listForumMessages = asyncHandler(async (_req: AuthedRequest, res: Response) => {
  res.json(await learningService.listForumMessages());
});

export const createForumMessage = asyncHandler(async (req: AuthedRequest, res: Response) => {
  res.status(201).json(await learningService.createForumMessage(req.body, req.user!));
});

export const toggleForumLike = asyncHandler(async (req: AuthedRequest, res: Response) => {
  res.json(await learningService.toggleForumLike(req.params.id, req.user!));
});

export const deleteForumMessage = asyncHandler(async (req: AuthedRequest, res: Response) => {
  await learningService.deleteForumMessage(req.params.id, req.user!);
  res.json({ success: true });
});

export const listExercises = asyncHandler(async (_req: AuthedRequest, res: Response) => {
  res.json(await learningService.listExercises());
});

export const createExercise = asyncHandler(async (req: AuthedRequest, res: Response) => {
  res.status(201).json(await learningService.createExercise(req.body));
});

export const updateExercise = asyncHandler(async (req: AuthedRequest, res: Response) => {
  res.json(await learningService.updateExercise(req.params.id, req.body));
});

export const deleteExercise = asyncHandler(async (req: AuthedRequest, res: Response) => {
  await learningService.deleteExercise(req.params.id);
  res.json({ success: true });
});

export const listExerciseSubmissions = asyncHandler(async (req: AuthedRequest, res: Response) => {
  res.json(await learningService.listExerciseSubmissions(req.user!));
});

export const submitExercise = asyncHandler(async (req: AuthedRequest, res: Response) => {
  res.status(201).json(await learningService.submitExercise(req.body, req.user!));
});

export const gradeSubmission = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const graded = await learningService.gradeSubmission(req.params.id, req.body, req.user!);
  await logAudit(req, 'Correção de Entrega', `Entrega ${req.params.id} corrigida (${graded.status}, nota ${graded.score}).`);
  res.json(graded);
});
