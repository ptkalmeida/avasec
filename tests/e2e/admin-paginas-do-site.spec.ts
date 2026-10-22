import { test, expect } from '@playwright/test';
import { entrarComoAdmin, rolaNaHorizontal } from './sessao';

/*
 * "Páginas do Site" do administrador, servidas pelo Laravel (M5, fatia 6b).
 *
 * Publicar é conferido no PHPUnit, dentro de uma transação: o que se grava aqui
 * aparece para qualquer visitante, e um e2e que publicasse deixaria o portal
 * diferente a cada execução. Acrescentar/remover/mover item só mexem no
 * rascunho — esses o navegador pode exercitar com segurança.
 */
test.describe('Páginas do Site do administrador — tela em Blade', () => {
  test('o item da sidebar leva ao editor, com as sete páginas', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);

    const sidebar = page.getByRole('navigation', { name: 'Navegação do painel administrativo' });
    await sidebar.getByRole('link', { name: /Páginas do Site/ }).click();

    await expect(page).toHaveURL(/\/admin\/paginas-do-site$/);
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: 'Páginas do Site' })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /Páginas do Site/ })).toHaveAttribute('aria-current', 'page');

    const paginas = page.getByRole('navigation', { name: 'Páginas do site' });
    await expect(paginas.getByRole('link')).toHaveCount(7);
    await expect(page.getByRole('button', { name: 'Salvar e publicar' }).last()).toBeVisible();
  });

  test('trocar de página muda os campos de acordo com o schema dela', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);
    await page.goto('/admin/paginas-do-site');

    await page.getByRole('navigation', { name: 'Páginas do site' }).getByRole('link', { name: /Dúvidas Frequentes/ }).click();

    await expect(page).toHaveURL(/pagina=duvidas$/);
    await expect(page.getByLabel('Pergunta').first()).toBeVisible();
    await expect(page.getByLabel('Resposta').first()).toBeVisible();
    await expect(page.getByRole('link', { name: /Ver esta página no site/ })).toHaveAttribute('href', '/duvidas');
  });

  test('adicionar item acrescenta uma linha SEM publicar, e abrir de novo a descarta', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);
    await page.goto('/admin/paginas-do-site?pagina=duvidas');

    const antes = await page.getByLabel('Pergunta').count();
    await page.getByRole('button', { name: 'Adicionar' }).click();

    await expect(page.getByLabel('Pergunta')).toHaveCount(antes + 1);

    await page.goto('/admin/paginas-do-site?pagina=duvidas');
    await expect(page.getByLabel('Pergunta')).toHaveCount(antes);
  });

  test('mover e remover item funcionam no rascunho e preservam o que foi digitado', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);
    await page.goto('/admin/paginas-do-site?pagina=duvidas');

    await page.getByRole('button', { name: 'Adicionar' }).click();
    const ultima = page.getByLabel('Pergunta').last();
    await ultima.fill('Pergunta do rascunho');

    await page.getByRole('button', { name: /Mover o item \d+ para cima/ }).last().click();
    await expect(page.locator('input[value="Pergunta do rascunho"]')).toHaveCount(1);

    await page.getByRole('button', { name: /Remover o item \d+/ }).first().click();
    await expect(page.locator('input[value="Pergunta do rascunho"]')).toHaveCount(1);
  });

  for (const largura of [320, 375, 768, 1440]) {
    test(`${largura}px: não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: largura, height: 900 });
      await entrarComoAdmin(page);
      await page.goto('/admin/paginas-do-site');
      await expect(page.getByRole('heading', { level: 3, name: 'Topo da página' })).toBeVisible();
      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});
