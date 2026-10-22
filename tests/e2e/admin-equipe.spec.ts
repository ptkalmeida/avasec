import { test, expect } from '@playwright/test';
import { entrarComoAdmin, rolaNaHorizontal } from './sessao';

/*
 * "Equipe Pedagógica" do administrador, servida pelo Laravel (M5, fatia 3).
 * Só leitura: a lista, as trilhas de cada gestor e a busca por nome.
 */
test.describe('Equipe Pedagógica do administrador — tela em Blade', () => {
  test('o item da sidebar leva à tela do servidor, marcado como atual', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);

    const sidebar = page.getByRole('navigation', { name: 'Navegação do painel administrativo' });
    await sidebar.getByRole('link', { name: /Equipe Pedagógica/ }).click();

    await expect(page).toHaveURL(/\/admin\/equipe$/);
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: 'Equipe Pedagógica' })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /Equipe Pedagógica/ })).toHaveAttribute('aria-current', 'page');
  });

  test('a lista mostra o gestor e as trilhas dele, e a busca filtra', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);
    await page.goto('/admin/equipe');

    await expect(page.getByText(/Trilhas sob gestão: \d+/).first()).toBeVisible();

    await page.getByLabel('Buscar gestor por nome').fill('nome-que-nao-existe-xyz');
    await page.getByRole('button', { name: 'Buscar' }).click();

    await expect(page).toHaveURL(/busca=nome-que-nao-existe-xyz/);
    await expect(page.getByText('Nenhum gestor com esse nome.')).toBeVisible();

    await page.getByRole('link', { name: 'Limpar' }).click();
    await expect(page).toHaveURL(/\/admin\/equipe$/);
    await expect(page.getByText(/Trilhas sob gestão: \d+/).first()).toBeVisible();
  });

  for (const largura of [320, 375, 768, 1440]) {
    test(`${largura}px: não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: largura, height: 900 });
      await entrarComoAdmin(page);
      await page.goto('/admin/equipe');
      await expect(page.getByRole('heading', { level: 2, name: /^Gestores ativos/ })).toBeVisible();
      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});
