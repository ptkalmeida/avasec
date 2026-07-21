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
    const vite = await createViteServer({
      server: { middlewareMode: true },
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
