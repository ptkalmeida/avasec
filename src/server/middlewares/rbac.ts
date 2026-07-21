// Controle de acesso por perfil (RBAC). Sempre roda depois de requireAuth.
// Botão escondido no frontend não é controle de acesso — a validação real é aqui.
import type { Response, NextFunction } from 'express';
import { Errors } from '../utils/ApiError';
import type { AuthedRequest } from './auth';

export function requireRole(...roles: Array<'student' | 'instructor' | 'admin'>) {
  return (req: AuthedRequest, _res: Response, next: NextFunction) => {
    if (!req.user || !roles.includes(req.user.role)) {
      next(Errors.forbidden());
      return;
    }
    next();
  };
}

/** Aluno só pode operar sobre o próprio `studentName` (vindo de :studentName, body.studentName
 * ou query.studentName) — admin/instrutor têm rotas próprias para agir em nome de outros. */
export function requireSelfStudent(getStudentName: (req: AuthedRequest) => string | undefined) {
  return (req: AuthedRequest, _res: Response, next: NextFunction) => {
    if (!req.user) {
      next(Errors.unauthorized());
      return;
    }
    if (req.user.role === 'admin') {
      next();
      return;
    }
    const target = getStudentName(req);
    if (req.user.role === 'student' && target === req.user.name) {
      next();
      return;
    }
    next(Errors.forbidden('Você só pode acessar os seus próprios dados.'));
  };
}
