// Garante que só contas com status 'active' acessem áreas internas (cursos, progresso,
// certificados, dados gerenciais, etc). Sempre reconsulta o banco — o status pode ter mudado
// depois que o token foi emitido (ex.: admin bloqueou a conta 5 minutos atrás).
import type { Response, NextFunction } from 'express';
import { prisma } from '../prisma';
import { Errors } from '../utils/ApiError';
import { asyncHandler } from '../utils/asyncHandler';
import type { AuthedRequest } from './auth';

export const requireActiveAccount = asyncHandler(async (req: AuthedRequest, _res: Response, next: NextFunction) => {
  if (!req.user) {
    next(Errors.unauthorized());
    return;
  }

  const account = await prisma.user.findUnique({
    where: { id: req.user.sub },
    select: { status: true },
  });

  if (!account) {
    next(Errors.unauthorized('Sessão inválida — usuário não encontrado.'));
    return;
  }

  if (account.status === 'blocked') {
    next(Errors.accountBlocked());
    return;
  }
  if (account.status === 'pending_confirmation') {
    next(Errors.accountPending());
    return;
  }

  next();
});
