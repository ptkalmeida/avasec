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
const {prefixos, exatos = []} = JSON.parse(
  fs.readFileSync(path.resolve(__dirname, 'rotas-laravel.json'), 'utf-8'),
) as {prefixos: string[]; exatos?: string[]};

/*
 * Caminho EXATO vira expressão regular ancorada.
 *
 * O Vite trata uma chave começada em `^` como regex, e é disso que a migração
 * de uma árvore por partes depende: `/aluno` precisa chegar ao Laravel, e
 * `/aluno/certificados` — que ainda é React — não. Como prefixo, o primeiro
 * arrastaria o segundo junto, e o segundo abriria em branco em desenvolvimento,
 * porque o fallback do Laravel serve o `dist/` e o Vite não tem aqueles assets.
 *
 * `{param}` é um segmento: a entrada é a URI da rota do Laravel, copiada como
 * está, para não existirem duas grafias da mesma rota.
 *
 * A QUERY STRING entra no fim, e isso não é zelo: o Vite casa a chave contra
 * `req.url`, que inclui o `?...`. Sem o trecho opcional, `/?verify=<hash>` —
 * o endereço que o QR impresso no certificado carrega — não casava com `^/$`,
 * caía no fallback do Vite e abria o React. O mesmo valia para `/?janela=login`.
 * Em produção o Nginx manda tudo ao Laravel e o defeito não aparece, o que o
 * torna pior: só quebra na máquina de quem desenvolve, e só com parâmetro.
 */
const paraRegex = (uri: string): string =>
  '^'
  + uri
    .replace(/[.*+?^${}()|[\]\\]/g, (c) => (c === '{' || c === '}' ? c : `\\${c}`))
    .replace(/\{[^}]+\}/g, '[^/]+')
  + '(\\?.*)?$';

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
const proxyDoLaravel = Object.fromEntries([
  ...prefixos.map((prefixo) => [prefixo, {target: alvoLaravel, changeOrigin: false}]),
  ...exatos.map((uri) => [paraRegex(uri), {target: alvoLaravel, changeOrigin: false}]),
]);

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
