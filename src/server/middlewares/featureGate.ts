// Gate de feature flags no BACKEND. Esconder um botão no frontend não desativa uma
// funcionalidade — a rota precisa recusar a requisição quando a flag está desligada.
// A fonte das flags é o mesmo arquivo compartilhado do frontend (src/config/features.ts),
// garantindo que menu, componente, rota e regra de negócio liguem/desliguem juntos.
import type { Request, Response, NextFunction } from 'express';
import { features } from '../../config/features';
import { ApiError } from '../utils/ApiError';

export type FeatureFlag = keyof typeof features;

export function requireFeature(flag: FeatureFlag) {
  return (_req: Request, _res: Response, next: NextFunction) => {
    if (!features[flag]) {
      next(
        new ApiError(
          404,
          'FEATURE_DISABLED',
          'Esta funcionalidade não está disponível nesta versão da plataforma.'
        )
      );
      return;
    }
    next();
  };
}
