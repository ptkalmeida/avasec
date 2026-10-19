import { test, expect } from '@playwright/test';
import { rolaNaHorizontal } from './sessao';

/*
 * A porta de entrada, agora como endereço (fase M2).
 *
 * O PHPUnit cobre quem passa e quem não passa. Aqui fica o que só o navegador
 * responde: o formulário é usável com teclado, o seletor de papel troca o
 * campo sem ida ao servidor, e a tela cabe em 320px.
 *
 * Não há teste de login bem-sucedido aqui de propósito: ele precisaria de uma
 * senha real, e as de demonstração foram rotatadas e não estão no repositório.
 * Esse caminho é coberto no PHPUnit, que cria a conta dentro da transação.
 */
test.describe('Entrar — tela em Blade', () => {
  test('responde no endereço e não é o casco do React', async ({ page }) => {
    const resposta = await page.goto('/entrar');

    expect(resposta?.status()).toBe(200);
    await expect(page.getByRole('heading', { level: 1, name: 'Entrar no AVASEC' })).toBeVisible();
    await expect(page.locator('#root')).toHaveCount(0);
  });

  test('o seletor de papel troca o rótulo do identificador', async ({ page }) => {
    await page.goto('/entrar');

    // Aluno entra por CPF; gestão e admin por e-mail (ADR 11).
    await expect(page.getByText('CPF', { exact: true })).toBeVisible();

    // Clique no RÓTULO, que é o controle visível: o rádio é `sr-only` e o
    // Playwright (com razão) recusa agir sobre um elemento de 1px.
    await page.getByText('Gestão', { exact: true }).click();
    await expect(page.getByText('E-mail', { exact: true })).toBeVisible();
    await expect(page.locator('#identificador')).toHaveAttribute('type', 'email');
  });

  test('mostrar a senha é um botão de estado, não um truque', async ({ page }) => {
    await page.goto('/entrar');

    const senha = page.locator('#senha');
    const alternar = page.getByRole('button', { name: /Mostrar|Ocultar/ });

    await expect(senha).toHaveAttribute('type', 'password');
    await expect(alternar).toHaveAttribute('aria-pressed', 'false');

    await alternar.click();
    await expect(senha).toHaveAttribute('type', 'text');
    await expect(alternar).toHaveAttribute('aria-pressed', 'true');
  });

  test('credencial errada volta com aviso e sem perder o identificador', async ({ page }) => {
    await page.goto('/entrar');

    await page.locator('#identificador').fill('529.982.247-25');
    await page.locator('#senha').fill('uma-senha-que-nao-existe');
    await page.getByRole('button', { name: 'Entrar', exact: true }).click();

    await expect(page.getByRole('alert')).toBeVisible();
    // Redigitar o identificador a cada erro é o que faz a pessoa desistir.
    await expect(page.locator('#identificador')).toHaveValue('529.982.247-25');
    // A senha NÃO volta: ela entraria no histórico e no cache do navegador.
    await expect(page.locator('#senha')).toHaveValue('');
  });

  test('cadastre-se leva à tela de cadastro, e ela avisa antes de preencher', async ({ page }) => {
    await page.goto('/entrar');
    // "Cadastre-se" também está no cabeçalho; aqui interessa o do corpo.
    await page.locator('#conteudo').getByRole('link', { name: 'Cadastre-se' }).click();

    await expect(page.getByRole('heading', { level: 1, name: 'Criar meu cadastro' })).toBeVisible();
    await expect(page.getByText(/liberado pela coordenação/)).toBeVisible();
  });

  for (const w of [320, 375, 768, 1440]) {
    test(`${w}px: não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await page.goto('/entrar');

      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});

test.describe('Página inicial — tela em Blade', () => {
  test('a raiz é Blade e termina no Patrimônio Vivo', async ({ page }) => {
    await page.goto('/');

    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Escola Estadual da Cultura');
    await expect(page.getByText('Patrimônio Vivo')).toBeVisible();

    // As duas seções que saíram da home.
    await expect(page.getByText('Sentiu falta de algum curso')).toHaveCount(0);
    await expect(page.getByText('A educação que orienta esta escola')).toHaveCount(0);
  });

  for (const w of [320, 768, 1440]) {
    test(`${w}px: a home não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await page.goto('/');

      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});
