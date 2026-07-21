// Limitadores de taxa para as rotas mais sensíveis a abuso/força-bruta/scraping.
// Todos usam o mesmo formato de erro padronizado (via next(ApiError)) em vez da resposta
// default do express-rate-limit.
import rateLimit from 'express-rate-limit';
import { Errors } from '../utils/ApiError';

const rateLimitHandler = (message: string) => (_req: any, _res: any, next: any) => {
  next(Errors.tooManyRequests(message));
};

const baseOptions = {
  standardHeaders: true,
  legacyHeaders: false,
};

/** Login: 10 tentativas por IP a cada 15 minutos. O bloqueio por conta (após N falhas
 * seguidas) é reforçado à parte em authService, pois um atacante pode trocar de IP. */
export const loginLimiter = rateLimit({
  ...baseOptions,
  windowMs: 15 * 60 * 1000,
  limit: 10,
  handler: rateLimitHandler('Muitas tentativas de login. Aguarde alguns minutos antes de tentar novamente.'),
});

export const registerLimiter = rateLimit({
  ...baseOptions,
  windowMs: 60 * 60 * 1000,
  limit: 10,
  handler: rateLimitHandler('Muitas tentativas de cadastro. Aguarde antes de tentar novamente.'),
});

export const passwordChangeLimiter = rateLimit({
  ...baseOptions,
  windowMs: 15 * 60 * 1000,
  limit: 5,
  handler: rateLimitHandler('Muitas tentativas de alteração de senha. Aguarde alguns minutos.'),
});

/** Envio de justificativas/solicitações acadêmicas. */
export const justificationLimiter = rateLimit({
  ...baseOptions,
  windowMs: 60 * 60 * 1000,
  limit: 20,
  handler: rateLimitHandler('Muitas solicitações enviadas em pouco tempo. Aguarde antes de enviar outra.'),
});

/** Matrícula/admissão em cursos. */
export const enrollmentLimiter = rateLimit({
  ...baseOptions,
  windowMs: 15 * 60 * 1000,
  limit: 20,
  handler: rateLimitHandler('Muitas tentativas de matrícula em pouco tempo. Aguarde antes de tentar novamente.'),
});

/** Exportação de Dados Gerenciais — operação pesada e sensível, restrita a admin. */
export const exportLimiter = rateLimit({
  ...baseOptions,
  windowMs: 60 * 60 * 1000,
  limit: 10,
  handler: rateLimitHandler('Limite de exportações por hora atingido. Aguarde antes de exportar novamente.'),
});

/** Validação pública de certificado (busca por hash/ID sem autenticação). */
export const certLookupLimiter = rateLimit({
  ...baseOptions,
  windowMs: 15 * 60 * 1000,
  limit: 30,
  handler: rateLimitHandler('Muitas consultas de certificado em pouco tempo. Aguarde antes de tentar novamente.'),
});

export const uploadLimiter = rateLimit({
  ...baseOptions,
  windowMs: 15 * 60 * 1000,
  limit: 30,
  handler: rateLimitHandler('Muitos envios de arquivo em pouco tempo. Aguarde antes de enviar outro.'),
});

/** Limite global, generoso, para toda a API — rede de segurança contra scraping/DoS básico. */
export const globalApiLimiter = rateLimit({
  ...baseOptions,
  windowMs: 60 * 1000,
  limit: 300,
  handler: rateLimitHandler('Muitas requisições em pouco tempo. Aguarde um instante.'),
});
