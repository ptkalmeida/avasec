import { test, expect } from '@playwright/test';
import { entrarComoAluno, rolaNaHorizontal } from './sessao';

/*
 * `/aluno/certificados` em Blade.
 *
 * O conteúdo (só os próprios certificados, o PDF recusado para outra pessoa, o
 * que falta para o próximo) é conferido no PHPUnit, que cria e revoga
 * certificado dentro de transação. Aqui fica o que depende do navegador:
 * chegar pelo menu lateral e a tela caber no telefone.
 */
test.describe('Certificados do aluno — tela em Blade', () => {
  test('o item do menu lateral leva à tela, servida pelo servidor', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);

    await page.getByRole('link', { name: /^Certificados$/ }).first().click();

    await expect(page).toHaveURL(/\/aluno\/certificados$/);
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: /^Certificados$/ })).toBeVisible();
    // A antiga aba "Validação" vive onde a função já existe.
    await expect(page.getByRole('link', { name: /autenticador público/i })).toHaveAttribute('href', '/certificados');
  });

  test('o progresso de quem está a caminho vem escrito, e não só na barra', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);
    await page.goto('/aluno/certificados');

    const aCaminho = page.getByRole('heading', { level: 2, name: /^A caminho$/ });
    if (await aCaminho.count() === 0) {
      test.skip(true, 'O aluno de demonstração não tem curso a caminho.');
    }

    await expect(page.getByText(/\d+% de \d+% exigidos/).first()).toBeVisible();
    await expect(page.getByText(/^O que falta$/).first()).toBeVisible();
  });

  for (const w of [320, 375, 768, 1440]) {
    test(`${w}px: não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await entrarComoAluno(page);
      await page.goto('/aluno/certificados');

      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});
