// Middleware de autenticação: verifica o JWT e anexa a identidade do usuário à requisição.
// Não confia em nada vindo do cliente além do token assinado — role/status reais são
// sempre reconferidos no banco pelos middlewares seguintes (accountStatus, rbac).
import type { Request, Response, NextFunction } from 'express';
import jwt from 'jsonwebtoken';
import { env } from '../config/env';
import { Errors } from '../utils/ApiError';

export interface AuthTokenPayload {
  sub: string; // user id
  name: string;
  role: 'student' | 'instructor' | 'admin';
}

export interface AuthedRequest extends Request {
  user?: AuthTokenPayload;
}

export function signToken(payload: AuthTokenPayload): string {
  return jwt.sign(payload, env.JWT_SECRET, { expiresIn: '12h' });
}

/** Exige um token JWT válido. Não verifica papel nem status da conta — use os middlewares
 * requireRole/requireActiveAccount em conjunto quando a rota precisar disso. */
export function requireAuth(req: AuthedRequest, res: Response, next: NextFunction) {
  const header = req.headers.authorization;
  const token = header?.startsWith('Bearer ') ? header.slice(7) : null;
  if (!token) {
    next(Errors.unauthorized('Token de autenticação ausente.'));
    return;
  }
  try {
    req.user = jwt.verify(token, env.JWT_SECRET) as AuthTokenPayload;
    next();
  } catch {
    next(Errors.unauthorized('Token inválido ou expirado.'));
  }
}

/** Igual a requireAuth, mas não falha se não houver token — usado em rotas públicas que
 * mudam de comportamento quando o usuário está autenticado (ex.: registro provisionado por admin). */
export function attachUserIfPresent(req: AuthedRequest, _res: Response, next: NextFunction) {
  const header = req.headers.authorization;
  const token = header?.startsWith('Bearer ') ? header.slice(7) : null;
  if (token) {
    try {
      req.user = jwt.verify(token, env.JWT_SECRET) as AuthTokenPayload;
    } catch {
      // token presente mas inválido: segue como anônimo, não é motivo para 401 aqui.
    }
  }
  next();
}
