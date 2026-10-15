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
 * Então: uma cadeia só, a da raiz, e o MESMO arquivo de tokens (`src/index.css`,
 * 314 linhas de paleta, escada tipográfica e a variante `ava:`). Quando a fase
 * M7 apagar o React, este arquivo de configuração continua válido — o que sai é
 * o `vite.config.ts`, não este.
 *
 * Saída em `backend-laravel/public/build`, que é onde o `@vite` do Blade procura
 * o manifesto.
 */
const RAIZ_LARAVEL = path.resolve(__dirname, 'backend-laravel');

export default defineConfig({
  plugins: [
    laravel({
      input: ['backend-laravel/resources/css/blade.css', 'backend-laravel/resources/js/blade.ts'],
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
    // Porta própria: a 5173 é do Vite do React, e as duas rodam juntas enquanto
    // a migração durar.
    port: 5174,
    strictPort: true,
  },
});
