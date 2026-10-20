import { test, expect } from '@playwright/test';
import { entrarComoAluno, rolaNaHorizontal } from './sessao';

/*
 * `/aluno/biblioteca` e `/aluno/duvidas` em Blade.
 *
 * O conteúdo (escada de visibilidade, item sem endereço) é conferido no
 * PHPUnit; aqui, que o menu leva às telas, servidas pelo servidor, e que elas
 * cabem em tela estreita.
 */
test.describe('Biblioteca e Ajuda do aluno — telas em Blade', () => {
  test('o menu lateral leva à biblioteca, servida pelo servidor', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);

    await page.getByRole('link', { name: /^Biblioteca$/ }).first().click();

    await expect(page).toHaveURL(/\/aluno\/biblioteca$/);
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: /^Biblioteca$/ })).toBeVisible();
  });

  test('o menu lateral leva à ajuda, e a pergunta abre a resposta', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);

    await page.getByRole('link', { name: /^Ajuda$/ }).first().click();

    await expect(page).toHaveURL(/\/aluno\/duvidas$/);
    await expect(page.locator('#root')).toHaveCount(0);

    const primeira = page.getByRole('region', { name: 'Dúvidas frequentes' }).locator('button[aria-expanded]').first();
    await expect(primeira).toHaveAttribute('aria-expanded', 'false');
    await primeira.click();
    await expect(primeira).toHaveAttribute('aria-expanded', 'true');
  });

  test('da ajuda se chega ao suporte', async ({ page }) => {
    await entrarComoAluno(page);
    await page.goto('/aluno/duvidas');

    await page.getByRole('link', { name: 'Falar com o gestor do curso' }).click();

    await expect(page).toHaveURL(/\/aluno\/suporte$/);
  });

  test('em tela estreita, o menu leva ao suporte, e não à tela de mensagens fechada', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 800 });
    await entrarComoAluno(page);
    await page.goto('/aluno/biblioteca');

    await page.getByRole('button', { name: /Abrir a navegação/ }).first().click();
    await page.getByRole('navigation', { name: /telas estreitas/ }).getByRole('link', { name: 'Suporte pedagógico' }).click();

    await expect(page).toHaveURL(/\/aluno\/suporte$/);
  });

  for (const caminho of ['/aluno/biblioteca', '/aluno/duvidas']) {
    for (const w of [320, 375, 768, 1440]) {
      test(`${caminho} em ${w}px: não rola na horizontal`, async ({ page }) => {
        await page.setViewportSize({ width: w, height: 900 });
        await entrarComoAluno(page);
        await page.goto(caminho);

        expect(await rolaNaHorizontal(page)).toBe(false);
      });
    }
  }
});
