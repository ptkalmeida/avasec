import { test, expect } from '@playwright/test';
import { rolaNaHorizontal } from './sessao';

/*
 * `/duvidas` é a primeira tela com interatividade em Livewire (fase M1).
 *
 * O acordeão aqui NÃO é estado de navegador: cada clique é uma requisição ao
 * servidor, que devolve a marcação recomposta. Por isso os testes esperam o
 * resultado aparecer, em vez de assumir que ele é síncrono — um `expect`
 * imediato após o clique passaria por acaso numa máquina rápida e falharia na
 * do próximo.
 */
test.describe('Dúvidas Frequentes — acordeão em Livewire', () => {
  test('responde no mesmo endereço, sem passar pelo React', async ({ page }) => {
    const resposta = await page.goto('/duvidas');

    expect(resposta?.status()).toBe(200);
    await expect(page.getByRole('heading', { level: 1, name: /Dúvidas Frequentes/i })).toBeVisible();
    await expect(page.locator('#root')).toHaveCount(0);
  });

  test('começa com todas fechadas', async ({ page }) => {
    await page.goto('/duvidas');

    const perguntas = page.getByRole('button', { expanded: false });
    await expect(perguntas).toHaveCount(3);
    await expect(page.getByRole('button', { expanded: true })).toHaveCount(0);
  });

  test('abrir uma pergunta mostra a resposta', async ({ page }) => {
    await page.goto('/duvidas');

    const primeira = page.getByRole('button').first();
    await primeira.click();

    await expect(primeira).toHaveAttribute('aria-expanded', 'true');
    // `aria-controls` aponta para o painel; se a resposta não veio, não existe.
    const idPainel = await primeira.getAttribute('aria-controls');
    await expect(page.locator(`#${idPainel}`)).toBeVisible();
  });

  test('abrir outra fecha a anterior — só uma por vez', async ({ page }) => {
    await page.goto('/duvidas');

    const botoes = page.getByRole('button');
    await botoes.nth(0).click();
    await expect(botoes.nth(0)).toHaveAttribute('aria-expanded', 'true');

    await botoes.nth(1).click();
    await expect(botoes.nth(1)).toHaveAttribute('aria-expanded', 'true');
    await expect(botoes.nth(0)).toHaveAttribute('aria-expanded', 'false');
  });

  test('clicar na aberta fecha', async ({ page }) => {
    await page.goto('/duvidas');

    const primeira = page.getByRole('button').first();
    await primeira.click();
    await expect(primeira).toHaveAttribute('aria-expanded', 'true');

    await primeira.click();
    await expect(primeira).toHaveAttribute('aria-expanded', 'false');
  });

  for (const w of [320, 375, 768, 1440]) {
    test(`${w}px: não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await page.goto('/duvidas');

      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});
