// Política de CORS: em produção só libera as origens explicitamente configuradas em CORS_ORIGIN.
// Nunca usar "*" com credenciais (cookies/Authorization) — abriria a API para qualquer site.
import type { CorsOptions } from 'cors';
import { env } from './env';

export const corsOptions: CorsOptions = {
  origin(origin, callback) {
    // Requisições sem header Origin (curl, apps mobile, health checks) são permitidas.
    if (!origin) {
      callback(null, true);
      return;
    }
    if (env.corsOrigins.includes(origin)) {
      callback(null, true);
      return;
    }
    callback(new Error('Origem não autorizada pela política de CORS.'));
  },
  credentials: true,
  methods: ['GET', 'POST', 'PUT', 'DELETE'],
  allowedHeaders: ['Content-Type', 'Authorization'],
};
