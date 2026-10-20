import { test, expect } from '@playwright/test';
import { entrarComoAluno, rolaNaHorizontal } from './sessao';

/*
 * `/aluno/perfil` em Blade.
 *
 * Nome e senha são conferidos no PHPUnit, que pode trocá-los dentro de uma
 * transação — aqui NÃO se troca nada: o aluno de demonstração é compartilhado,
 * e um e2e que mudasse a senha dele trancaria todos os outros testes.
 */
test.describe('Perfil do aluno — tela em Blade', () => {
  test('o item do menu lateral leva à tela, servida pelo servidor', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);

    await page.getByRole('link', { name: /^Perfil$/ }).first().click();

    await expect(page).toHaveURL(/\/aluno\/perfil$/);
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: /^Perfil$/ })).toBeVisible();
  });

  test('cada campo tem rótulo, e a regra da senha vem antes da tentativa', async ({ page }) => {
    await entrarComoAluno(page);
    await page.goto('/aluno/perfil');

    await expect(page.getByLabel('Nome completo')).toBeVisible();
    await expect(page.getByLabel('Sua senha atual')).toBeVisible();
    await expect(page.getByLabel('Nova senha', { exact: true })).toBeVisible();
    await expect(page.getByLabel('Confirme a nova senha')).toBeVisible();

    // A regra está ligada ao campo por `aria-describedby`: o leitor de tela a
    // lê ao entrar no campo, e não só quem enxerga o texto abaixo.
    await expect(page.getByLabel('Nova senha', { exact: true }))
      .toHaveAccessibleDescription(/8 caracteres, com ao menos uma letra e um número/);
  });

  test('sair da conta encerra a sessão', async ({ page }) => {
    await entrarComoAluno(page);
    await page.goto('/aluno/perfil');

    await page.getByRole('button', { name: /^Sair da conta$/ }).click();

    // Sem sessão, a área do aluno volta a pedir login.
    await page.goto('/aluno/perfil');
    await expect(page).toHaveURL(/\/entrar/);
  });

  for (const w of [320, 375, 768, 1440]) {
    test(`${w}px: não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await entrarComoAluno(page);
      await page.goto('/aluno/perfil');

      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});
