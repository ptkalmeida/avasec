// Validação de entrada com Zod. Nunca confiar em body/query/params crus do cliente.
import type { Request, Response, NextFunction } from 'express';
import type { ZodSchema } from 'zod';
import { Errors } from '../utils/ApiError';

type Source = 'body' | 'query' | 'params';

export function validate(schema: ZodSchema, source: Source = 'body') {
  return (req: Request, _res: Response, next: NextFunction) => {
    const result = schema.safeParse(req[source]);
    if (!result.success) {
      const message = result.error.issues
        .map((issue) => `${issue.path.join('.') || source}: ${issue.message}`)
        .join('; ');
      next(Errors.validation(message));
      return;
    }
    // Usa o dado já validado/normalizado (ex.: strings de data coagidas, defaults aplicados).
    (req as any)[source] = result.data;
    next();
  };
}
