import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { expect, type Page } from '@playwright/test';

/*
 * O projeto é ESM, então não há `__dirname`. Resolvemos pela raiz de execução — o
 * Playwright roda a partir da pasta onde está o `playwright.config.ts`. A checagem
 * existe para o caso de alguém invocar de outro lugar: um caminho errado aqui faria
 * o `execFileSync` falhar com "artisan não encontrado", que manda procurar o
 * problema no PHP em vez de no caminho.
 */
const BACKEND = path.resolve(process.cwd(), 'backend-laravel');

if (!existsSync(path.join(BACKEND, 'artisan'))) {
  throw new Error(
    `Não achei o Laravel em ${BACKEND}. Rode os testes da raiz do projeto: \`npm run e2e\`.`,
  );
}

/** Memoriza por processo: emitir token custa ~2 s de boot do Laravel por chamada. */
const emitidos = new Map<string, string>();

/**
 * Emite um JWT pelo próprio Laravel, sem saber a senha de ninguém.
 *
 * As senhas de demonstração foram rotacionadas em 08/09/2026 e as atuais não estão
 * no repositório — nem devem estar. Tentar adivinhar já bloqueou uma conta aqui
 * (5 erros = 15 min). O token vale 12 h e não altera nada no banco.
 */
export function emitirToken(email: string, sessaoPropria = false): string {
  /*
   * `sessaoPropria`: token novo, fora do cache. O "Sair" cancela o token no servidor (ADR 022),
   * então um teste que sai da conta com o token compartilhado derrubaria todos os seguintes.
   */
  const emCache = sessaoPropria ? undefined : emitidos.get(email);
  if (emCache !== undefined) return emCache;

  const php = `
    $u = \\App\\Models\\User::withTrashed()->where("email", "${email}")->first();
    if (!$u) { echo "SEM_USUARIO"; return; }
    echo \\App\\Support\\Jwt::issue($u->id, $u->name, $u->role);
  `.replace(/\s+/g, ' ');

  const saida = execFileSync('php', ['artisan', 'tinker', `--execute=${php}`], {
    cwd: BACKEND,
    encoding: 'utf-8',
  });

  // O tinker imprime avisos antes do valor; o token é o único trecho com a cara de JWT.
  const token = saida.match(/eyJ[\w-]+\.[\w-]+\.[\w-]+/)?.[0];
  if (!token) {
    throw new Error(
      `Não foi possível emitir token para ${email}. Saída do artisan:\n${saida}\n` +
        'O banco está de pé? Ver a skill `rodar-avasec`.',
    );
  }

  if (!sessaoPropria) emitidos.set(email, token);

  return token;
}

/**
 * Entra como o aluno e para na raiz da área dele.
 *
 * Vai direto por URL (`/aluno`) em vez de clicar no cabeçalho. Não é atalho: o
 * rótulo do botão MUDA conforme a largura — "Ambiente de Estudos" em 1440 px vira
 * "Estudos" em 375 px — e o harness anterior, que casava texto, falhava calado nas
 * larguras estreitas. Rota não muda de nome com a janela.
 */
export async function entrarComoAluno(
  page: Page,
  email = 'joao.silva@lms.edu',
  sessaoPropria = false,
): Promise<void> {
  const token = emitirToken(email, sessaoPropria);

  /*
   * `localhost` e `127.0.0.1` são origens diferentes para o cookie. O padrão do
   * harness é `localhost` (baseURL do playwright.config), mas quem roda apontando
   * direto para o Laravel usa `127.0.0.1` — e aí o cookie não acompanha, a tela
   * redireciona para o login e o teste falha dizendo "não achei o painel".
   */
  const base = process.env.AVASEC_URL ?? 'http://localhost:8000';

  await page.context().addCookies([
    { name: 'ava_session', value: token, url: base, httpOnly: true },
  ]);

  await page.goto('/aluno');

  /*
   * A confirmação mudou junto com a tela: `/aluno` passou a ser Blade (fase M3)
   * e a saudação é o que sempre existe nela, com ou sem curso. Antes se esperava
   * o botão "Sair" do cabeçalho do React — que nesta tela não existe mais, porque
   * quem encerra a sessão continua sendo o portal (ver barra-estreita.blade.php).
   */
  await expect(page.getByRole('heading', { level: 1, name: /^Olá, / })).toBeVisible();
}

/**
 * Abre a primeira aula do curso ativo e confirma que chegou lá.
 *
 * A confirmação é a tira de abas: "Material Didático" só existe dentro de uma aula
 * aberta. Sem ela, um teste de layout passaria medindo a tela errada — foi o que
 * aconteceu com o harness anterior, que seguia adiante depois de um clique perdido.
 */
export async function abrirPrimeiraAula(page: Page): Promise<void> {
  /*
   * Painel → curso → aula, os três em Blade (fase M3). A confirmação é a aba
   * "Material Didático", que só existe dentro de uma aula aberta — sem ela, um
   * teste de layout passaria medindo a tela errada.
   *
   * `role: 'tab'`, e não `button`: no React era um `<button>` solto, e agora a
   * tira tem papéis de verdade (`tablist`/`tab`), que é o que o leitor de tela
   * precisa para anunciar "aba 1 de 2". O mesmo passo com o papel antigo falha
   * com "elemento não encontrado" e manda procurar o problema no lugar errado.
   */
  await page.getByRole('link', { name: /^Continuar curso$/ }).first().click();
  await page.getByRole('link', { name: /^Iniciar aula$/ }).click();

  await expect(page.getByRole('tab', { name: /material didático/i })).toBeVisible();
}

/**
 * Entra como o instrutor e para na escolha de curso.
 *
 * Mesma emissão de token do aluno: a conta de demonstração do gestor é
 * `professor@avasec.local`, e a senha dela não está no repositório.
 */
export async function entrarComoInstrutor(
  page: Page,
  email = 'professor@avasec.local',
  sessaoPropria = false,
): Promise<void> {
  const token = emitirToken(email, sessaoPropria);
  const base = process.env.AVASEC_URL ?? 'http://localhost:8000';

  await page.context().addCookies([
    { name: 'ava_session', value: token, url: base, httpOnly: true },
  ]);

  await page.goto('/inst');

  await expect(page.getByRole('heading', { level: 1, name: /^Meus cursos$/ })).toBeVisible();
}

/**
 * Entra como o administrador e para em "Meu perfil", a primeira tela do painel
 * que é do servidor (as demais seções do Admin migram por fatias).
 */
export async function entrarComoAdmin(page: Page, email = 'admin@avasec.local'): Promise<void> {
  const token = emitirToken(email);
  const base = process.env.AVASEC_URL ?? 'http://localhost:8000';

  await page.context().addCookies([
    { name: 'ava_session', value: token, url: base, httpOnly: true },
  ]);

  await page.goto('/admin/perfil');

  await expect(page.getByRole('heading', { level: 1, name: /^Meu perfil$/ })).toBeVisible();
}

/** `true` quando a página inteira rola na horizontal — nunca deve acontecer. */
export async function rolaNaHorizontal(page: Page): Promise<boolean> {
  // Com o Laravel servindo direto, a página aparece enquanto ainda carrega, e a largura
  // medida nesse intervalo não é a que o usuário fica vendo: espera o fim do carregamento.
  await page.waitForLoadState('load');
  return page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
}
