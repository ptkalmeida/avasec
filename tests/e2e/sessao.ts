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
export function emitirToken(email: string): string {
  const emCache = emitidos.get(email);
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

  emitidos.set(email, token);

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
export async function entrarComoAluno(page: Page, email = 'joao.silva@lms.edu'): Promise<void> {
  const token = emitirToken(email);

  await page.context().addCookies([
    { name: 'ava_session', value: token, domain: 'localhost', path: '/', httpOnly: true },
  ]);

  await page.goto('/aluno');
  await expect(page.getByRole('button', { name: /sair/i }).first()).toBeVisible();
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
   * Dois rótulos porque há dois caminhos de entrada: "Continuar curso", na
   * seção "Meus cursos" do painel, e "Entrar na sala de aula", que sobrevive no
   * cartão do curso com vigência encerrada e na ficha do catálogo.
   */
  await page
    .getByRole('button', { name: /continuar curso|entrar na sala de aula/i })
    .first()
    .click();

  const iniciar = page.getByRole('button', { name: /iniciar primeira aula|continuar de onde parei/i });
  if (await iniciar.count()) {
    await iniciar.first().click();
  } else {
    // Curso já começado: a primeira aula da trilha serve igual.
    await page.getByRole('button', { name: /^\s*1[\.\s]/ }).first().click();
  }

  await expect(page.getByRole('button', { name: /material didático/i })).toBeVisible();
}

/** `true` quando a página inteira rola na horizontal — nunca deve acontecer. */
export async function rolaNaHorizontal(page: Page): Promise<boolean> {
  return page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
}
