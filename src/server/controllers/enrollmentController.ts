import type { Response } from 'express';
import { asyncHandler } from '../utils/asyncHandler';
import { logAudit } from '../services/auditService';
import * as enrollmentService from '../services/enrollmentService';
import type { AuthedRequest } from '../middlewares/auth';

export const getProgress = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const { studentName } = req.query as { studentName?: string };
  res.json(await enrollmentService.getProgress(studentName, req.user!));
});

export const upsertProgress = asyncHandler(async (req: AuthedRequest, res: Response) => {
  res.json(await enrollmentService.upsertProgress(req.body, req.user!));
});

export const getEnrollments = asyncHandler(async (req: AuthedRequest, res: Response) => {
  res.json(await enrollmentService.getEnrollments(req.user!));
});

export const upsertEnrollment = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const updated = await enrollmentService.upsertEnrollment(req.params.studentName, req.body, req.user!);
  const isCancellation = req.body.enrolledCourseId === null;
  await logAudit(
    req,
    isCancellation ? 'Cancelamento de Inscrição' : 'Alteração de Matrícula',
    `Matrícula de "${req.params.studentName}" atualizada.`
  );
  res.json(updated);
});

export const listAdmissions = asyncHandler(async (req: AuthedRequest, res: Response) => {
  res.json(await enrollmentService.listAdmissions(req.user!));
});

export const createAdmission = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const admission = await enrollmentService.createAdmission(req.body, req.user!);
  await logAudit(req, 'Solicitação de Matrícula', `Matrícula solicitada por "${admission.studentName}" no curso ${admission.courseId}.`);
  res.status(201).json(admission);
});

export const updateAdmissionStatus = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const updated = await enrollmentService.updateAdmissionStatus(req.params.id, req.body.status, req.user!);
  await logAudit(
    req,
    'Aprovação de Solicitação',
    `Matrícula ${req.params.id} (${updated.studentName}) marcada como "${updated.status}".`
  );
  res.json(updated);
});
