import { test, expect } from '@playwright/test';
import { entrarComoInstrutor, rolaNaHorizontal } from './sessao';

/*
 * A caixa de mensagens do gestor.
 *
 * Nenhum teste aqui ENVIA mensagem: o aluno e o gestor de demonstração são
 * compartilhados, e mensagem não se apaga (ADR 12) — um e2e que escrevesse
 * encheria a caixa de todo mundo a cada execução. O envio, a resposta e o
 * escopo são conferidos no PHPUnit, dentro de uma transação. Aqui: que a tela
 * é do servidor, que a conversa abre e que ela cabe nas larguras.
 */
test.describe('Mensagens do gestor — tela em Blade', () => {
  test('o menu leva à caixa, servida pelo servidor', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);

    await page.getByRole('navigation', { name: 'Navegação da área do instrutor' })
      .getByRole('link', { name: /^Mensagens/ }).click();

    await expect(page).toHaveURL(/\/inst\/mensagens$/);
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: /^Mensagens$/ })).toBeVisible();
  });

  test('escolher um aluno abre a conversa com a etiqueta da aula', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);
    await page.goto('/inst/mensagens');

    const conversa = page.locator('a[href*="/inst/mensagens?aluno="]').first();
    test.skip((await conversa.count()) === 0, 'Nenhum aluno escreveu ao gestor de demonstração.');
    await conversa.click();

    await expect(page.getByRole('heading', { level: 2, name: /^Conversa com / })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Responder' })).toBeVisible();

    // A marca crua do assunto não vaza para a tela.
    expect(await page.content()).not.toContain('[Aula:');
  });

  for (const w of [320, 375, 768, 1440]) {
    test(`${w}px: não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await entrarComoInstrutor(page);
      await page.goto('/inst/mensagens');

      expect(await rolaNaHorizontal(page)).toBe(false);

      const conversa = page.locator('a[href*="/inst/mensagens?aluno="]').first();
      if ((await conversa.count()) > 0) {
        await conversa.click();
        expect(await rolaNaHorizontal(page)).toBe(false);
      }
    });
  }
});
