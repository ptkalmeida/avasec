import type { Response } from 'express';
import { asyncHandler } from '../utils/asyncHandler';
import { logAudit } from '../services/auditService';
import * as settingsService from '../services/settingsService';
import type { AuthedRequest } from '../middlewares/auth';

export const getSettings = asyncHandler(async (_req: AuthedRequest, res: Response) => {
  res.json(await settingsService.getSystemSettings());
});

export const updateSettings = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const updated = await settingsService.updateSystemSettings(req.body);
  await logAudit(req, 'Alteração de Configurações do Sistema', `Configurações atualizadas: ${Object.keys(req.body).join(', ')}.`);
  res.json(updated);
});
