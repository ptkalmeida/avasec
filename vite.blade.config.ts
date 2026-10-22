import path from 'node:path';
import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

/*
 * Build dos assets das telas em Blade (fase M1 do plano 16).
 *
 * POR QUE AQUI, E NÃO EM `backend-laravel/`
 *
 * O `laravel new` deixou um `backend-laravel/package.json` próprio, com Vite 8 e
 * Tailwind 4 — uma SEGUNDA cadeia de build, com `node_modules` próprio. Ele não
 * é usado: `node_modules` nunca foi instalado lá.
 *
 * Não vale instalar, por um motivo que não é economia de disco. O
 * `resources/css/app.css` que veio no andaime declara `Instrument Sans` e
 * **nenhum token `ava-*`**. Uma tela migrada construída a partir dele não se
 * pareceria com a tela React ao lado — e a migração é gradual, então as duas
 * convivem na mesma sessão do mesmo usuário por meses. Duas cadeias de build
 * significam duas definições de paleta, e a que ninguém olha é a que diverge.
 *
 * Então: uma cadeia só, a da raiz. Desde a fase M7 (ADR 020) é a ÚNICA: o React e
 * o `vite.config.ts` saíram, e os tokens do portal moram em
 * `backend-laravel/resources/css/tokens.css`.
 *
 * Saída em `backend-laravel/public/build`, que é onde o `@vite` do Blade procura
 * o manifesto.
 */
const RAIZ_LARAVEL = path.resolve(__dirname, 'backend-laravel');

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'backend-laravel/resources/css/blade.css',
        // Back-office: Anexo D §1 (tokens próprios, FontAwesome), sem os tokens do portal.
        'backend-laravel/resources/css/admin.css',
        'backend-laravel/resources/js/blade.ts',
      ],
      // O refresh do plugin reinicia a página quando um .blade.php muda.
      refresh: ['backend-laravel/resources/views/**'],
      publicDirectory: 'backend-laravel/public',
      buildDirectory: 'build',
      hotFile: path.join(RAIZ_LARAVEL, 'public', 'hot'),
    }),
    tailwindcss(),
  ],
  build: {
    outDir: path.join(RAIZ_LARAVEL, 'public', 'build'),
    emptyOutDir: true,
    manifest: 'manifest.json',
  },
  server: {
    // Só para `npm run dev` (recarga do CSS). Em uso normal basta `npm run build`.
    port: 5174,
    strictPort: true,
  },
});
