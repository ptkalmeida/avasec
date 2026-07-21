import type { Response } from 'express';
import { asyncHandler } from '../utils/asyncHandler';
import { logAudit } from '../services/auditService';
import * as courseService from '../services/courseService';
import type { AuthedRequest } from '../middlewares/auth';

export const listCourses = asyncHandler(async (_req: AuthedRequest, res: Response) => {
  res.json(await courseService.listCourses());
});

export const createCourse = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const course = await courseService.createCourse(req.body, req.user!);
  await logAudit(req, 'Criação de Curso', `Curso "${course.title}" criado.`);
  res.status(201).json(course);
});

export const updateCourse = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const course = await courseService.updateCourse(req.params.id, req.body, req.user!);
  await logAudit(req, 'Alteração de Curso', `Curso "${course.title}" (${req.params.id}) atualizado.`);
  res.json(course);
});

export const deleteCourse = asyncHandler(async (req: AuthedRequest, res: Response) => {
  await courseService.deleteCourse(req.params.id, req.user!);
  await logAudit(req, 'Exclusão de Curso', `Curso ${req.params.id} excluído.`, 'WARNING');
  res.json({ success: true });
});
