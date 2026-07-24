import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    name: 'backend',
    environment: 'node',
    setupFiles: ['./tests/setup.ts'],
    include: ['tests/**/*.test.ts'],
    exclude: ['tests/frontend/**'],
    testTimeout: 20000,
    hookTimeout: 20000,
    // Os testes de segurança batem no banco de verdade (Docker) e usam rate limit real —
    // rodar em série evita testes de lockout/rate-limit interferindo uns nos outros.
    fileParallelism: false,
  },
});
