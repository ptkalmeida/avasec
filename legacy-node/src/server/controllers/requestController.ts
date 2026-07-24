import type { Response } from 'express';
import { asyncHandler } from '../utils/asyncHandler';
import { logAudit } from '../services/auditService';
import * as requestService from '../services/requestService';
import type { AuthedRequest } from '../middlewares/auth';

export const listAcademicRequests = asyncHandler(async (req: AuthedRequest, res: Response) => {
  res.json(await requestService.listAcademicRequests(req.user!));
});

export const createAcademicRequest = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const request = await requestService.createAcademicRequest(req.body, req.user!);
  await logAudit(req, 'Solicitação Acadêmica', `Solicitação de "${request.type}" enviada por ${request.studentName}.`);
  res.status(201).json(request);
});

export const updateAcademicRequestStatus = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const updated = await requestService.updateAcademicRequestStatus(req.params.id, req.body.status);
  await logAudit(
    req,
    'Aprovação de Solicitação',
    `Solicitação acadêmica ${req.params.id} (${updated.studentName}) marcada como "${updated.status}".`
  );
  res.json(updated);
});
