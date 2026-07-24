import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

// Config separada dos testes de backend (vitest.config.ts): componentes React
// precisam de ambiente jsdom, enquanto os testes de backend batem no MySQL real
// e não podem rodar em paralelo (ver comentário em vitest.config.ts).
export default defineConfig({
  plugins: [react()],
  test: {
    name: 'frontend',
    environment: 'jsdom',
    setupFiles: ['./tests/frontend/setup.ts'],
    include: ['tests/frontend/**/*.test.{ts,tsx}'],
  },
});
