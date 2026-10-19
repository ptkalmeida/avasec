import { test, expect } from '@playwright/test';
import { rolaNaHorizontal } from './sessao';

/*
 * `/orientacoes` é a quarta tela servida por Blade (fase M1 do plano 16).
 *
 * Mesma invariante das anteriores: a URL não muda e o conteúdo não muda. A
 * conferência do texto contra o serviço de conteúdo fica no PHPUnit
 * (`PaginasPublicasBladeTest`); aqui o assunto é o que só o navegador responde.
 */
test.describe('Orientações Gerais — tela em Blade', () => {
  test('responde no mesmo endereço, sem passar pelo React', async ({ page }) => {
    const resposta = await page.goto('/orientacoes');

    expect(resposta?.status()).toBe(200);
    await expect(page.getByRole('heading', { level: 1, name: /Orienta/i })).toBeVisible();

    await expect(page.locator('#root')).toHaveCount(0);
  });

  test('cada diretriz traz o seu algarismo romano, na ordem', async ({ page }) => {
    await page.goto('/orientacoes');

    const cartoes = page.locator('h4');
    const total = await cartoes.count();
    expect(total).toBeGreaterThan(0);

    // A numeração deriva da posição, não do conteúdo: o admin edita o título da
    // diretriz, nunca o algarismo.
    const romanos = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    for (let i = 0; i < total; i++) {
      await expect(page.getByText(`Diretriz ${romanos[i]}`, { exact: true })).toBeVisible();
    }
  });

  test('a moldura está centralizada e os cartões leem à esquerda', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/orientacoes');

    /*
     * Esta tela é a única das migradas que combina as duas coisas, e foi assim
     * no React de propósito: título e abertura ao centro, texto dos cartões
     * alinhado à esquerda para ser legível em bloco. Centralizar o cartão junto
     * seria a regressão fácil de cometer ao traduzir a marcação.
     */
    await expect(page.getByRole('heading', { level: 1 })).toHaveCSS('text-align', 'center');
    await expect(page.locator('h4').first()).toHaveCSS('text-align', 'left');
  });

  test('o tema está ligado e o CSS institucional carregou', async ({ page }) => {
    await page.goto('/orientacoes');

    await expect(page.locator('html')).toHaveClass(/tema-ava/);

    const titulo = page.getByRole('heading', { level: 1 });
    await expect(titulo).toHaveCSS('font-weight', '800');
    await expect(titulo).toHaveCSS('color', 'rgb(4, 24, 92)');
  });

  for (const w of [320, 375, 768, 1440]) {
    test(`${w}px: não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await page.goto('/orientacoes');

      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});
