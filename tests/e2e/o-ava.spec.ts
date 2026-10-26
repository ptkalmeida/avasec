import { test, expect } from '@playwright/test';
import { rolaNaHorizontal } from './sessao';

/*
 * `/o-ava` é a primeira tela servida por Blade (fase M1 do plano 16).
 *
 * O que estes testes protegem não é a aparência, é a INVARIANTE da migração: a
 * URL não muda e o conteúdo não muda. Quem tinha o link salvo continua chegando
 * no mesmo lugar, e lê o mesmo texto — só o que monta o HTML é diferente.
 */
test.describe('O que é o AVA — tela em Blade', () => {
  test('responde no mesmo endereço, sem passar pelo React', async ({ page }) => {
    const resposta = await page.goto('/o-ava');

    expect(resposta?.status()).toBe(200);
    await expect(page.getByRole('heading', { level: 1, name: /O que é o AVA/i })).toBeVisible();

    // O casco do React traz <div id="root">; a tela em Blade, não. É assim que
    // se prova que a rota saiu do fallback.
    await expect(page.locator('#root')).toHaveCount(0);
  });

  test('traz os três cartões vindos do mesmo serviço de conteúdo', async ({ page }) => {
    await page.goto('/o-ava');

    const cartoes = page.locator('h4');
    await expect(cartoes).toHaveCount(3);
    await expect(cartoes.first()).not.toBeEmpty();
  });

  test('o tema está ligado e o CSS institucional carregou', async ({ page }) => {
    await page.goto('/o-ava');

    // Sem `tema-ava` no <html>, as regras da variante `ava:` ficam inertes.
    await expect(page.locator('html')).toHaveClass(/tema-ava/);

    /*
     * O peso 800 vem da regra `.tema-ava .font-titulo` do `src/index.css`, e de
     * mais lugar nenhum. Se ele está aplicado, o arquivo de tokens entrou no
     * build do Blade E a classe de tema está no <html> — que é exatamente o que
     * esta tela precisa provar.
     *
     * A primeira versão deste teste procurava "Playfair" na fonte do título e
     * falhava. Era o teste que estava errado: sob `.tema-ava`, `--font-titulo` é
     * redefinido para Inter de propósito (o portal em azul usa Inter; a Playfair
     * é do tema do admin). O código estava certo desde o início.
     */
    const titulo = page.getByRole('heading', { level: 1 });
    await expect(titulo).toHaveCSS('font-weight', '800');

    // E a cor institucional, que só existe se o @theme foi gerado.
    await expect(titulo).toHaveCSS('color', 'rgb(4, 24, 92)');
  });

  for (const w of [320, 375, 768, 1440]) {
    test(`${w}px: não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await page.goto('/o-ava');

      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});
