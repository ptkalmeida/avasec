import { defineConfig } from '@playwright/test';

/*
 * NAVEGADOR: usa o que já está no disco, de propósito.
 *
 * O Playwright 1.64 pede o build `chromium-1248`; esta máquina tem o 1234
 * (Chrome 151), baixado por uma instalação anterior. Baixar o 1248 seriam ~300 MB
 * de Chromium + headless shell + ffmpeg para ganhar quatro versões de Chrome que
 * não mudam nada no que medimos aqui — largura, rolagem e presença de elemento.
 * Testado: o 1.64.1 dirige o 1234 sem reclamar.
 *
 * O `.npmrc` tem `ignore-scripts=true`, então o pós-instalação do Playwright (que
 * baixaria navegador) não roda. Isso é intencional e é a razão de o caminho estar
 * escrito aqui: sem ele, o `chromium.launch()` procura o 1248 e falha.
 *
 * No dia em que a máquina tiver o 1248, basta apagar `executablePath`.
 */
const NAVEGADOR =
  process.env.AVASEC_CHROMIUM ??
  `${process.env.LOCALAPPDATA}/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe`;

/*
 * Estes testes exigem os DOIS servidores de pé (`npm run dev` e o `artisan serve`).
 * Não subimos nada por `webServer` porque o projeto tem a skill `rodar-avasec`, que
 * trata as armadilhas desta máquina — processo órfão preso na porta, Vite escorregando
 * para 5174, proxy do MySQL obsoleto depois de suspensão. Duplicar isso aqui daria
 * uma segunda verdade sobre como subir o sistema, e a que ninguém lê é a que apodrece.
 *
 * Por isso NÃO entram em `npm test`: os 722 do Vitest rodam em memória, em 22 s, sem
 * depender de nada. Estes rodam por `npm run e2e`, quando se quer olhar a tela montada.
 */
export default defineConfig({
  testDir: './tests/e2e',
  /*
   * Um worker: os testes compartilham a mesma sessão, o mesmo aluno e o mesmo
   * banco de desenvolvimento.
   *
   * E isso vale para a MÁQUINA, não só para esta execução: `workers: 1` serializa
   * dentro de uma rodada, e nada impede duas rodadas simultâneas. Já aconteceu
   * aqui — uma suíte em segundo plano e outra em primeiro derrubaram dois testes
   * de largura estreita que passam sozinhos. Não era regressão; era a mesma conta
   * navegando em dois navegadores ao mesmo tempo. Rode uma de cada vez.
   */
  workers: 1,
  // Em tela, 5 s é pouco: a primeira montagem carrega blocos por `React.lazy`.
  timeout: 60_000,
  expect: { timeout: 10_000 },
  reporter: process.env.CI ? 'list' : [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: process.env.AVASEC_URL ?? 'http://localhost:5173',
    launchOptions: { executablePath: NAVEGADOR },
    // Só guarda rastro do que falhou — o disco deste projeto já foi problema uma vez.
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    video: 'off',
  },
});
