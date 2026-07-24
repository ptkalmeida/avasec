import type { Response } from 'express';
import { asyncHandler } from '../utils/asyncHandler';
import { logAudit } from '../services/auditService';
import * as exportService from '../services/exportService';
import { Errors } from '../utils/ApiError';
import { EXPORTABLE_DATASETS } from '../services/exportService';
import type { AuthedRequest } from '../middlewares/auth';

export const exportDataset = asyncHandler(async (req: AuthedRequest, res: Response) => {
  const dataset = req.params.dataset as any;
  if (!EXPORTABLE_DATASETS.includes(dataset)) {
    throw Errors.badRequest(`Base inválida. Bases disponíveis: ${EXPORTABLE_DATASETS.join(', ')}.`);
  }

  const data = await exportService.exportDataset(dataset);

  // Auditoria: quem exportou, quando e qual base — exigência explícita da coordenação.
  await logAudit(
    req,
    'Exportação de Dados Gerenciais',
    `Base "${dataset}" exportada (${data.length} registros).`
  );

  res.json({ dataset, exportedAt: new Date().toISOString(), count: data.length, data });
});
