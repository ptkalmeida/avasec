import { defineConfig } from 'vitest/config';

// Após o corte da migração para Laravel, o Vitest cobre apenas o FRONTEND (jsdom +
// React Testing Library). O backend é testado pelo PHPUnit do Laravel
// (`npm run test:api` / `php backend-laravel/artisan test`). A antiga suíte Node
// (50 testes, supertest) está arquivada em legacy-node/tests/.
export default defineConfig({
  test: {
    projects: ['./vitest.frontend.config.ts'],
  },
});
