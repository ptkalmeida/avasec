/**
 * Quais endereços o servidor (Laravel) atende — a regra num lugar só.
 *
 * Dois consumidores leem `rotas-laravel.json` e precisam concordar sobre o que
 * ele quer dizer:
 *
 *  - o `vite.config.ts`, que monta o proxy de desenvolvimento;
 *  - o próprio SPA, que precisa saber quando NÃO desenhar uma tela.
 *
 * O segundo existe por um defeito da migração por partes. O React navega do
 * lado do cliente: um `navigate('/aluno')` dentro do SPA não pede nada ao
 * servidor, e o React Router desenha ali a versão ANTIGA do painel. O aluno
 * saía de uma seção ainda em React e via o painel de antes do redesenho, no
 * mesmo endereço em que, recarregando, veria o novo. Com esta regra, o SPA
 * entrega esses endereços ao servidor (ver `App.tsx`).
 *
 * Sem dependência de React nem de Node, de propósito: é importado pelo
 * `vite.config.ts` e pelo navegador, e testado sem nenhum dos dois.
 */

import contrato from '../../rotas-laravel.json';

export interface ContratoDeRotas {
  prefixos: string[];
  exatos?: string[];
}

/*
 * Caminho EXATO vira expressão regular ancorada.
 *
 * O Vite trata uma chave começada em `^` como regex, e é disso que a migração
 * de uma árvore por partes depende: `/aluno` precisa chegar ao Laravel, e
 * `/aluno/eventos` — que ainda é React — não. Como prefixo, o primeiro
 * arrastaria o segundo junto.
 *
 * `{param}` é um segmento: a entrada é a URI da rota do Laravel, copiada como
 * está, para não existirem duas grafias da mesma rota.
 *
 * A QUERY STRING entra no fim, e isso não é zelo: o Vite casa a chave contra
 * `req.url`, que inclui o `?...`. Sem o trecho opcional, `/?verify=<hash>` — o
 * endereço que o QR impresso no certificado carrega — não casava com `^/$` e
 * abria o React. Em produção o Nginx manda tudo ao Laravel e o defeito não
 * aparece, o que o torna pior: só quebra na máquina de quem desenvolve.
 */
export const paraRegex = (uri: string): string =>
  '^'
  + uri
    .replace(/[.*+?^${}()|[\]\\]/g, (c) => (c === '{' || c === '}' ? c : `\\${c}`))
    .replace(/\{[^}]+\}/g, '[^/]+')
  + '(\\?.*)?$';

/**
 * O prefixo cobre este caminho?
 *
 * Na FRONTEIRA do segmento, como faz o `RoteadorMpaTest`: `/aluno` cobre
 * `/aluno` e `/aluno/perfil`, e não `/alunos-de-2025`. Com `startsWith` puro,
 * o SPA entregaria ao servidor um endereço que o servidor devolveria ao SPA.
 *
 * Prefixo terminado em `-` é família (`/livewire-<hash>`) e casa por início,
 * sem exigir barra — é a mesma exceção do teste.
 */
const cobertoPor = (caminho: string, prefixo: string): boolean =>
  prefixo.endsWith('-')
    ? caminho.startsWith(prefixo)
    : caminho === prefixo || caminho.startsWith(`${prefixo}/`);

/**
 * O servidor atende este caminho?
 *
 * Mesma semântica do proxy do Vite: prefixo casa por `startsWith`, exato casa
 * pela expressão ancorada. Se as duas divergissem, o SPA entregaria ao servidor
 * um endereço que o proxy devolveria ao SPA — e a página recarregaria para
 * sempre.
 */
export function servidoPeloServidor(caminho: string, regras: ContratoDeRotas = contrato): boolean {
  if (regras.prefixos.some((prefixo) => cobertoPor(caminho, prefixo))) return true;

  return (regras.exatos ?? []).some((uri) => new RegExp(paraRegex(uri)).test(caminho));
}
