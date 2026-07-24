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

/** Nome do cookie de sessão (HttpOnly — inacessível a JavaScript no navegador). */
export const SESSION_COOKIE = 'ava_session';

export const SESSION_COOKIE_OPTIONS = {
  httpOnly: true,
  sameSite: 'lax' as const, // bloqueia envio em POSTs cross-site (mitigação de CSRF)
  secure: env.isProduction, // só trafega por HTTPS em produção
  path: '/',
  maxAge: 12 * 60 * 60 * 1000, // acompanha a expiração do JWT (12h)
};

/** Extrai o token da requisição: cookie HttpOnly (navegador, preferido) ou header
 * Authorization (clientes de API/testes). */
function extractToken(req: AuthedRequest): string | null {
  const fromCookie = (req as any).cookies?.[SESSION_COOKIE];
  if (typeof fromCookie === 'string' && fromCookie.length > 0) return fromCookie;
  const header = req.headers.authorization;
  return header?.startsWith('Bearer ') ? header.slice(7) : null;
}

/** Exige um token JWT válido. Não verifica papel nem status da conta — use os middlewares
 * requireRole/requireActiveAccount em conjunto quando a rota precisar disso. */
export function requireAuth(req: AuthedRequest, res: Response, next: NextFunction) {
  const token = extractToken(req);
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
  const token = extractToken(req);
  if (token) {
    try {
      req.user = jwt.verify(token, env.JWT_SECRET) as AuthTokenPayload;
    } catch {
      // token presente mas inválido: segue como anônimo, não é motivo para 401 aqui.
    }
  }
  next();
}
