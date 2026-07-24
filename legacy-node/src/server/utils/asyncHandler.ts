// Envolve handlers async: qualquer rejeição vira next(err) em vez de derrubar o processo
// ou travar a requisição sem resposta.
import type { Request, Response, NextFunction, RequestHandler } from 'express';

export const asyncHandler =
  (fn: (req: Request, res: Response, next: NextFunction) => Promise<void>): RequestHandler =>
  (req, res, next) => {
    fn(req, res, next).catch(next);
  };
