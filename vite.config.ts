import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import {defineConfig} from 'vite';

export default defineConfig(() => {
  return {
    plugins: [react(), tailwindcss()],
    resolve: {
      alias: {
        '@': path.resolve(__dirname, '.'),
      },
    },
    server: {
      // Escuta em todas as interfaces, não só em 127.0.0.1 (o padrão do Vite),
      // para que outro computador da mesma rede alcance a página em
      // http://<ip-da-maquina>:5173. Sem isto o Vite nem abre a porta na rede e
      // a conexão de fora é recusada antes de chegar ao servidor.
      //
      // O backend continua em 127.0.0.1:8000 e NÃO precisa ser exposto: o proxy
      // de `/api` abaixo é feito pelo Vite, dentro desta máquina. Uma origem só
      // para o navegador, do jeito que já era — sem CORS nem cookie cross-origin.
      //
      // Vale só em desenvolvimento (`npm run dev`); o build de produção é servido
      // por outro servidor e não passa por aqui.
      host: true,
      // HMR is disabled in AI Studio via DISABLE_HMR env var.
      // Do not modifyâfile watching is disabled to prevent flickering during agent edits.
      hmr: process.env.DISABLE_HMR !== 'true',
      // Disable file watching when DISABLE_HMR is true to save CPU during agent edits.
      watch: process.env.DISABLE_HMR === 'true' ? null : {},
      // /api e /uploads são encaminhados para o backend Laravel (`npm run api`),
      // preservando uma única origem para o navegador (sem CORS/cookie cross-origin).
      // Fluxo de dev: `npm run api` (Laravel :8000) + `npm run dev` (Vite :5173).
      proxy: {
        '/api': { target: process.env.LARAVEL_URL || 'http://127.0.0.1:8000', changeOrigin: true },
        '/uploads': { target: process.env.LARAVEL_URL || 'http://127.0.0.1:8000', changeOrigin: true },
      },
    },
  };
});
