import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import fs from 'fs';
import path from 'path';
import {defineConfig} from 'vite';

/**
 * Os caminhos que o Laravel atende direto, lidos de `rotas-laravel.json`.
 *
 * A lista NÃO é escrita aqui de propósito. Durante a migração para MPA (ADR 017)
 * ela cresce a cada tela que passa para Blade, e manter uma cópia neste arquivo
 * garantiria que um dia as duas discordassem — com o sintoma mais confuso
 * possível: a tela funciona em produção e não funciona em desenvolvimento.
 * `RoteadorMpaTest` falha se uma rota do Laravel não estiver no JSON.
 */
const alvoLaravel = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
const {prefixos} = JSON.parse(
  fs.readFileSync(path.resolve(__dirname, 'rotas-laravel.json'), 'utf-8'),
) as {prefixos: string[]};

/*
 * `changeOrigin: false`, e isto não é detalhe.
 *
 * Com `true`, o proxy reescrevia o cabeçalho `Host` para o alvo — e o Laravel
 * monta URL absoluta a partir do Host que recebe. O efeito só apareceu quando a
 * primeira tela em Livewire entrou: o runtime era pedido em
 * `http://127.0.0.1:8000/livewire-<hash>/livewire.js`, ou seja, numa ORIGEM
 * diferente da página (`localhost:5173`). O script até carregava, mas o POST de
 * atualização do componente saía cross-origin e morria em silêncio — o acordeão
 * abria no servidor e nunca no navegador.
 *
 * Com `false`, o Laravel vê `Host: localhost:5173` e gera URL da mesma origem,
 * que volta pelo proxy. Para as chamadas do React nada muda: elas sempre foram
 * relativas.
 */
const proxyDoLaravel = Object.fromEntries(
  prefixos.map((prefixo) => [prefixo, {target: alvoLaravel, changeOrigin: false}]),
);

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
      // Encaminhados para o backend Laravel (`npm run api`), preservando uma única
      // origem para o navegador (sem CORS/cookie cross-origin). Fluxo de dev:
      // `npm run api` (Laravel :8000) + `npm run dev` (Vite :5173).
      //
      // A lista vem de `rotas-laravel.json` — ver a nota no topo deste arquivo.
      proxy: proxyDoLaravel,
    },
  };
});
