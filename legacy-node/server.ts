import 'dotenv/config';
import path from 'path';
import express from 'express';
import { createServer as createViteServer } from 'vite';
import { env } from './src/server/config/env';
import { createApiApp, attachErrorHandler } from './src/server/app';

async function startServer() {
  const app = createApiApp();

  // --- VITE AND SPA FALLBACK MIDDLEWARES ---
  if (!env.isProduction) {
    // Config inline (configFile: false): carregar vite.config.ts por dentro do loader do tsx
    // falha no Windows/Node 18 (ERR_INVALID_URL_SCHEME). O vite.config.ts continua sendo
    // usado normalmente pelo `vite build` de produção.
    const [{ default: react }, { default: tailwindcss }] = await Promise.all([
      import('@vitejs/plugin-react'),
      import('@tailwindcss/vite'),
    ]);
    const vite = await createViteServer({
      configFile: false,
      plugins: [react(), tailwindcss()],
      resolve: { alias: { '@': path.resolve(process.cwd(), '.') } },
      server: {
        middlewareMode: true,
        hmr: process.env.DISABLE_HMR !== 'true',
        watch: process.env.DISABLE_HMR === 'true' ? null : {},
      },
      appType: 'spa',
    });
    app.use(vite.middlewares);
  } else {
    const distPath = path.join(process.cwd(), 'dist');
    app.use(express.static(distPath));
    app.get('*', (_req, res) => {
      res.sendFile(path.join(distPath, 'index.html'));
    });
  }

  attachErrorHandler(app);

  app.listen(env.PORT, '0.0.0.0', () => {
    console.log(`[AVASEC Full-Stack Server] running on http://localhost:${env.PORT} (${env.NODE_ENV})`);
  });
}

startServer();
