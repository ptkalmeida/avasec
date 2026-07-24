import type { Response } from 'express';
import { asyncHandler } from '../utils/asyncHandler';
import { getPageParams, paginatedResponse } from '../utils/pagination';
import { logAudit } from '../services/auditService';
import * as certificateService from '../services/certificateService';
import type { AuthedRequest } from '../middlewares/auth';

export const listCertificates = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const pageParams = getPageParams(req);
  const { items, total } = await certificateService.listCertificates(req.user!, pageParams.skip, pageParams.take);
  res.json(paginatedResponse(items, total, pageParams));
});

export const verifyCertificate = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const cert = await certificateService.verifyCertificatePublic(String(req.query.q));
  res.json(cert ?? null);
});

export const issueCertificate = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const cert = await certificateService.issueCertificate(req.body, req.user!);
  await logAudit(req, 'Emissão de Certificado', `Certificado emitido para "${cert.studentName}" no curso ${cert.courseId}.`);
  res.status(201).json(cert);
});

export const deleteCertificate = asyncHandler(async (req: AuthedRequest, res: Response) => {
  await certificateService.deleteCertificate(req.params.id);
  await logAudit(req, 'Exclusão de Certificado', `Certificado ${req.params.id} removido.`, 'WARNING');
  res.json({ success: true });
});
