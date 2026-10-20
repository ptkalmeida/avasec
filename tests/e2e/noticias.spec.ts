import { test, expect } from '@playwright/test';
import { rolaNaHorizontal } from './sessao';

/*
 * `/noticias` em Blade — fecha o grupo "A Escola" do menu público.
 *
 * O conteúdo é conferido contra o serviço no PHPUnit
 * (`PaginasPublicasBladeTest`); aqui fica o que só o navegador responde: o
 * realce que anda com as setas (Alpine), a busca chegando pela URL e a tela não
 * rolando na horizontal.
 */
test.describe('Notícias — tela em Blade', () => {
  test('responde no mesmo endereço, sem passar pelo React', async ({ page }) => {
    const resposta = await page.goto('/noticias');

    expect(resposta?.status()).toBe(200);
    await expect(page.getByRole('heading', { level: 1, name: /Not[íi]cias/i })).toBeVisible();
    await expect(page.locator('#root')).toHaveCount(0);
  });

  test('a entrada "A Escola" do menu acende nesta página', async ({ page }) => {
    await page.goto('/noticias');

    // Quem acende numa página de dentro de um grupo é o GRUPO, e a marcação não
    // é só cor: `aria-current` diz o mesmo a quem usa leitor de tela.
    await expect(page.locator('[aria-current]').first()).toContainText(/A Escola/i);
  });

  test('as setas movem o destaque sem recarregar a página', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/noticias');

    const cartoes = page.locator('article');
    const total = await cartoes.count();

    if (total < 2) {
      test.skip(true, 'É preciso mais de uma notícia publicada para mover o destaque.');
    }

    // O destaque começa no primeiro cartão.
    await expect(cartoes.nth(0)).toHaveClass(/ring-ava-acao/);

    await page.getByRole('button', { name: /pr[óo]xima not[íi]cia/i }).click();
    await expect(cartoes.nth(1)).toHaveClass(/ring-ava-acao/);
    await expect(cartoes.nth(0)).not.toHaveClass(/ring-ava-acao/);

    await page.getByRole('button', { name: /not[íi]cia anterior/i }).click();
    await expect(cartoes.nth(0)).toHaveClass(/ring-ava-acao/);
  });

  test('a busca do cabeçalho leva ao recorte, e o recorte tem endereço', async ({ page }) => {
    await page.goto('/noticias');

    const primeiro = page.locator('article h2').first();
    const titulo = (await primeiro.textContent())?.trim() ?? '';
    expect(titulo.length).toBeGreaterThan(5);

    await page.goto(`/noticias?busca=${encodeURIComponent(titulo)}`);

    await expect(page.locator('article')).toHaveCount(1);
    await expect(page.locator('article h2').first()).toHaveText(titulo);
  });

  test('busca sem resultado explica e oferece a saída', async ({ page }) => {
    await page.goto('/noticias?busca=zzzznaoexiste');

    await expect(page.getByText(/Sem resultados/i)).toBeVisible();

    await page.getByRole('link', { name: /Limpar filtro de busca/i }).click();

    await expect(page).toHaveURL(/\/noticias$/);
    expect(await page.locator('article').count()).toBeGreaterThan(0);
  });

  for (const w of [320, 375, 768, 1440]) {
    test(`${w}px: não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await page.goto('/noticias');

      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});
