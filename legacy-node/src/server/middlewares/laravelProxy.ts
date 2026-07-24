// Proxy da migração incremental Node -> Laravel (strangler pattern).
//
// Enquanto o backend é migrado módulo a módulo, Node e Laravel rodam lado a lado.
// Para manter UMA ÚNICA ORIGEM para o frontend (evitando CORS/cookie cross-origin
// durante a transição), o Node é o ponto de entrada e encaminha para o Laravel
// apenas os prefixos de rota já migrados.
//
// Quais prefixos são encaminhados é controlado pela env var LARAVEL_PROXY_PREFIXES
// (lista separada por vírgula, ex.: "/api/library,/api/webinars"). Vazia = nada é
// encaminhado (comportamento atual, 100% Node). Assim dá para ligar/desligar um
// módulo sem alterar código.
//
// Este middleware é montado ANTES do express.json(), então o corpo das requisições
// encaminhadas chega intacto ao Laravel (o Node não consome o stream).
import type { RequestHandler } from 'express';
import { createProxyMiddleware } from 'http-proxy-middleware';

const LARAVEL_URL = process.env.LARAVEL_URL || 'http://localhost:8000';

export function parseProxyPrefixes(raw: string | undefined): string[] {
  return (raw || '')
    .split(',')
    .map((p) => p.trim())
    .filter((p) => p.length > 0);
}

/**
 * Retorna um middleware que encaminha para o Laravel as requisições cujo path
 * começa com algum dos prefixos configurados; caso contrário chama next() e o
 * Node segue tratando normalmente. Retorna null se nenhum prefixo estiver ativo.
 */
export function createLaravelProxy(): RequestHandler | null {
  const prefixes = parseProxyPrefixes(process.env.LARAVEL_PROXY_PREFIXES);
  if (prefixes.length === 0) return null;

  const proxy = createProxyMiddleware({
    target: LARAVEL_URL,
    changeOrigin: true,
    xfwd: true,
  });

  return (req, res, next) => {
    const path = req.path;
    const matches = prefixes.some((prefix) => path === prefix || path.startsWith(prefix + '/') || path.startsWith(prefix));
    if (matches) {
      return proxy(req, res, next);
    }
    return next();
  };
}
