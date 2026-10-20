import { test, expect } from '@playwright/test';
import { entrarComoAluno, rolaNaHorizontal } from './sessao';

/*
 * Avaliações do aluno em Blade.
 *
 * Nenhum teste aqui ENVIA uma prova respondida: o aluno de demonstração é
 * compartilhado, e cada envio grava uma tentativa que não se apaga (ADR 12).
 * Nota, correção e o que o resultado mostra são conferidos no PHPUnit, dentro
 * de uma transação. Aqui: o caminho até a prova, que a página não carrega o
 * gabarito, e que enviar sem responder é recusado sem gravar nada.
 */
test.describe('Avaliações do aluno — telas em Blade', () => {
  test('do curso se chega à lista, servida pelo servidor', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);

    await page.getByRole('link', { name: /^Continuar curso$/ }).first().click();
    await page.getByRole('link', { name: /Avaliações do curso/ }).click();

    await expect(page).toHaveURL(/\/aluno\/curso\/[^/]+\/avaliacoes$/);
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: /^Avaliações$/ })).toBeVisible();
  });

  test('a prova abre sem gabarito, e enviar em branco é recusado', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);
    await page.getByRole('link', { name: /^Continuar curso$/ }).first().click();
    await page.getByRole('link', { name: /Avaliações do curso/ }).click();

    const abrir = page.getByRole('link', { name: /^(Começar|Refazer) / }).first();
    test.skip((await abrir.count()) === 0, 'O curso do aluno de demonstração não tem avaliação.');
    await abrir.click();

    await expect(page).toHaveURL(/\/avaliacoes\/[^/]+$/);
    await expect(page.locator('fieldset').first()).toBeVisible();

    const html = await page.content();
    expect(html).not.toContain('correctOptionIndex');
    expect(html).not.toMatch(/alternativa correta/i);

    await page.getByRole('button', { name: 'Enviar respostas' }).click();

    await expect(page.getByRole('alert')).toContainText(/Falta(m)? responder/);
  });

  for (const w of [320, 375, 768, 1440]) {
    test(`${w}px: lista e prova não rolam na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await entrarComoAluno(page);
      await page.goto('/aluno');
      await page.getByRole('link', { name: /^Continuar curso$/ }).first().click();
      await page.getByRole('link', { name: /Avaliações do curso/ }).click();

      expect(await rolaNaHorizontal(page)).toBe(false);

      const abrir = page.getByRole('link', { name: /^(Começar|Refazer) / }).first();
      if ((await abrir.count()) > 0) {
        await abrir.click();
        expect(await rolaNaHorizontal(page)).toBe(false);
      }
    });
  }
});
