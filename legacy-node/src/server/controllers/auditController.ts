import type { Response } from 'express';
import { asyncHandler } from '../utils/asyncHandler';
import { getPageParams, paginatedResponse } from '../utils/pagination';
import * as auditLogService from '../services/auditLogService';
import type { AuthedRequest } from '../middlewares/auth';

export const listSecurityLogs = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const pageParams = getPageParams(req);
  const { items, total } = await auditLogService.listSecurityLogs(pageParams.skip, pageParams.take);
  res.json(paginatedResponse(items, total, pageParams));
});

export const clearSecurityLogs = asyncHandler(async (_req: AuthedRequest, res: Response) => {
  await auditLogService.clearSecurityLogs();
  res.json({ success: true, message: 'Logs de auditoria zerados com sucesso.' });
});

// ---------- TELEMETRIA (eventos de UI enviados pelo frontend) ----------

export const recordClientEvent = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const actor = req.user ? { name: req.user.name, role: req.user.role } : { name: 'Visitante Anônimo', role: 'anonymous' };
  const forwarded = req.headers['x-forwarded-for'];
  const ip = typeof forwarded === 'string' ? forwarded.split(',')[0].trim() : req.socket.remoteAddress || 'desconhecido';
  const event = await auditLogService.recordClientEvent(req.body, actor, ip, String(req.headers['user-agent'] || ''));
  res.status(201).json(event);
});

export const listClientEvents = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const pageParams = getPageParams(req);
  const { items, total } = await auditLogService.listClientEvents(pageParams.skip, pageParams.take);
  res.json(paginatedResponse(items, total, pageParams));
});
