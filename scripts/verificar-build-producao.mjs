// Guarda de build: falha se o artefato em dist/ sair com marca de DESENVOLVIMENTO.
//
// Por que existe: o Vite lê o .env antes de resolver o modo, então NODE_ENV=development
// no .env da raiz faz `npm run build` produzir pacote de dev — e `vite build --mode
// production` NÃO corrige, o .env vence. Nesse estado import.meta.env.DEV vale true em
// produção, e tudo que o código esconde atrás desse guard (os PINs de demonstração na
// tela de login, o atalho de troca rápida de perfil) aparece para qualquer visitante.
// Isso aconteceu de fato neste projeto e passou despercebido; a regressão é silenciosa,
// porque o build termina com sucesso. Daí a verificação ser automática (npm postbuild)
// em vez de uma anotação no README.
//
// Uso: roda sozinho no postbuild. Manualmente: node scripts/verificar-build-producao.mjs

import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

const DIST = 'dist';

// Cada marca é um sinal INEQUÍVOCO de pacote de desenvolvimento. Nada aqui pode
// aparecer num build de produção legítimo — do contrário o gate viraria ruído e
// alguém o desligaria.
const MARCAS = [
  {
    padrao: 'jsxDEV',
    explica: 'transformação JSX de desenvolvimento (jsxDEV) — o build saiu em modo dev',
  },
  {
    padrao: 'Dica para Avaliação do Fluxo',
    explica: 'dica de PINs da tela de login, que só deveria existir atrás de import.meta.env.DEV',
  },
  {
    padrao: 'react-refresh',
    explica: 'runtime de hot reload do React',
  },
];

/** Lista recursivamente os arquivos de texto do artefato. */
function arquivos(dir) {
  const saida = [];
  for (const nome of readdirSync(dir)) {
    const caminho = join(dir, nome);
    if (statSync(caminho).isDirectory()) {
      saida.push(...arquivos(caminho));
      continue;
    }
    if (/\.(js|mjs|cjs|css|html|map)$/i.test(nome)) saida.push(caminho);
  }
  return saida;
}

let alvos;
try {
  alvos = arquivos(DIST);
} catch {
  console.error(`\n✖ ${DIST}/ não encontrado. Rode o build antes desta verificação.\n`);
  process.exit(1);
}

if (alvos.length === 0) {
  console.error(`\n✖ ${DIST}/ está vazio — nada para verificar. O build falhou?\n`);
  process.exit(1);
}

const encontrados = [];
for (const caminho of alvos) {
  const conteudo = readFileSync(caminho, 'utf8');
  for (const marca of MARCAS) {
    if (conteudo.includes(marca.padrao)) {
      encontrados.push({ caminho, ...marca });
    }
  }
}

if (encontrados.length > 0) {
  console.error('\n✖ BUILD REPROVADO: o artefato tem marca de desenvolvimento.\n');
  for (const { caminho, padrao, explica } of encontrados) {
    console.error(`  ${caminho}`);
    console.error(`    encontrado: ${padrao}`);
    console.error(`    significa:  ${explica}\n`);
  }
  console.error('  Causa mais provável: NODE_ENV definido no .env da raiz.');
  console.error('  Confira com: grep -n NODE_ENV .env');
  console.error('  Remova a linha e rode o build de novo. Passar --mode production NÃO resolve.\n');
  process.exit(1);
}

console.log(`✔ Build de produção verificado: ${alvos.length} arquivos, nenhuma marca de desenvolvimento.`);
