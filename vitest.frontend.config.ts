import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

// Componentes React precisam de ambiente jsdom. Único projeto de testes do
// Vitest hoje — o backend é coberto pelo PHPUnit (ver vitest.config.ts).
export default defineConfig({
  plugins: [react()],
  test: {
    name: 'frontend',
    environment: 'jsdom',
    setupFiles: ['./tests/frontend/setup.ts'],
    include: ['tests/frontend/**/*.test.{ts,tsx}'],
  },
});
