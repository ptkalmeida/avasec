// Handler de erro único da API. Formato padronizado, sem vazar stack trace ou detalhes
// internos ao cliente — erros inesperados são logados no servidor e viram um 500 genérico.
import type { Request, Response, NextFunction } from 'express';
import { ZodError } from 'zod';
import { MulterError } from 'multer';
import { ApiError } from '../utils/ApiError';

export function notFoundHandler(req: Request, res: Response) {
  res.status(404).json({ error: true, code: 'NOT_FOUND', message: 'Rota não encontrada.' });
}

export function errorHandler(err: unknown, req: Request, res: Response, _next: NextFunction) {
  if (err instanceof ApiError) {
    res.status(err.status).json({ error: true, code: err.code, message: err.message });
    return;
  }

  if (err instanceof ZodError) {
    const message = err.issues.map((i) => `${i.path.join('.')}: ${i.message}`).join('; ');
    res.status(400).json({ error: true, code: 'VALIDATION_ERROR', message });
    return;
  }

  if (err instanceof MulterError) {
    res.status(400).json({ error: true, code: 'UPLOAD_ERROR', message: err.message });
    return;
  }

  // CORS rejeitado por cors() chama next(err) com uma Error simples.
  if (err instanceof Error && err.message === 'Origem não autorizada pela política de CORS.') {
    res.status(403).json({ error: true, code: 'CORS_FORBIDDEN', message: err.message });
    return;
  }

  console.error('[API ERROR]', err);
  res.status(500).json({
    error: true,
    code: 'INTERNAL_ERROR',
    message: 'Erro interno do servidor. Tente novamente mais tarde.',
  });
}
