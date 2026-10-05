import { defineConfig } from 'vitest/config';

// O Vitest cobre apenas o FRONTEND (jsdom + React Testing Library). O backend é
// testado pelo PHPUnit do Laravel (`npm run test:api` /
// `php backend-laravel/artisan test`).
export default defineConfig({
  test: {
    projects: ['./vitest.frontend.config.ts'],
  },
});
