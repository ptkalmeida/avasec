import { test, expect } from '@playwright/test';
import { entrarComoAdmin, rolaNaHorizontal } from './sessao';

/*
 * A moldura do painel administrativo (Anexo D §1, vinculante) e o menu do
 * usuário.
 *
 * Trocar a senha de verdade é coberto no PHPUnit, dentro de uma transação:
 * a conta de admin é compartilhada, e um e2e que mudasse a senha trancaria
 * todos os outros testes.
 */
test.describe('Painel administrativo — moldura em Blade', () => {
  test('tela larga: sidebar sempre visível, topbar com o usuário e o menu suspenso', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);

    await expect(page.locator('#root')).toHaveCount(0);

    const sidebar = page.getByRole('navigation', { name: 'Navegação do painel administrativo' });
    await expect(sidebar).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /Dashboard & Relatórios/ })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /Equipe Pedagógica/ })).toBeVisible();

    // Sem botão de menu: a sidebar já está à vista.
    await expect(page.getByRole('button', { name: 'Abrir o menu' })).toBeHidden();

    // O nome e o perfil estão à vista sem clicar em nada.
    await expect(page.getByText('Administrador', { exact: true }).first()).toBeVisible();

    await page.getByRole('button', { name: /Administrador/ }).first().click();
    const menu = page.getByRole('menu', { name: 'Menu do usuário' });
    await expect(menu.getByRole('menuitem', { name: 'Meu perfil' })).toBeVisible();
    await expect(menu.getByRole('menuitem', { name: 'Trocar senha' })).toBeVisible();
    await expect(menu.getByRole('menuitem', { name: 'Sair' })).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(menu).toBeHidden();
  });

  test('Trocar senha leva ao formulário do servidor, com os três campos', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);

    await page.getByRole('button', { name: /Administrador/ }).first().click();
    await page.getByRole('menuitem', { name: 'Trocar senha' }).click();

    await expect(page).toHaveURL(/\/admin\/perfil\/senha$/);
    await expect(page.getByLabel('Senha atual')).toBeVisible();
    await expect(page.getByLabel('Nova senha', { exact: true })).toBeVisible();
    await expect(page.getByLabel('Confirme a nova senha')).toBeVisible();
  });

  test('do painel que ainda é React se chega a "Meu perfil", e a página do servidor não volta sozinha', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);

    // Das seções ainda em React: a sidebar do servidor leva para lá.
    await page.getByRole('link', { name: /^Alunos$/ }).click();
    await expect(page).toHaveURL(/\/admin\/alunos$/);

    await page.getByRole('link', { name: 'Meu perfil' }).click();
    await expect(page).toHaveURL(/\/admin\/perfil$/);
    await expect(page.locator('#root')).toHaveCount(0);

    // Esperar é o que separa "abriu" de "abriu e voltou": sem a espera o teste
    // passaria com o defeito presente.
    await page.waitForTimeout(3000);
    await expect(page).toHaveURL(/\/admin\/perfil$/);
    await expect(page.getByRole('heading', { level: 1, name: /^Meu perfil$/ })).toBeVisible();
  });

  test('celular: a sidebar começa fechada, abre pelo botão e fecha por fora', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 });
    await entrarComoAdmin(page);

    const sidebar = page.getByRole('navigation', { name: 'Navegação do painel administrativo' });
    await expect(sidebar).toBeHidden();

    await page.getByRole('button', { name: 'Abrir o menu' }).click();
    await expect(sidebar).toBeVisible();

    // Toca fora da sidebar (na camada semitransparente): ela fecha.
    await page.mouse.click(380, 400);
    await expect(sidebar).toBeHidden();

    expect(await rolaNaHorizontal(page)).toBe(false);
  });

  test('em 992px a sidebar já é fixa, e em 991px ainda é off-canvas', async ({ page }) => {
    await page.setViewportSize({ width: 992, height: 800 });
    await entrarComoAdmin(page);
    const sidebar = page.getByRole('navigation', { name: 'Navegação do painel administrativo' });
    await expect(sidebar).toBeVisible();

    await page.setViewportSize({ width: 991, height: 800 });
    await expect(sidebar).toBeHidden();
  });
});
