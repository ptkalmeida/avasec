import { test, expect } from '@playwright/test';
import { rolaNaHorizontal } from './sessao';

/*
 * `/o-projeto` é a terceira tela servida por Blade (fase M1 do plano 16).
 *
 * O que estes testes protegem não é a aparência, é a INVARIANTE da migração: a
 * URL não muda e o conteúdo não muda. Quem tinha o link salvo continua chegando
 * no mesmo lugar — só o que monta o HTML é diferente.
 *
 * A conferência de que o texto exibido é o do serviço de conteúdo fica no
 * PHPUnit (`PaginasPublicasBladeTest`), que pode perguntar ao serviço. Aqui o
 * assunto é o que só o navegador responde: layout, quebra e imagem.
 */
test.describe('O Projeto Pedagógico — tela em Blade', () => {
  test('responde no mesmo endereço, sem passar pelo React', async ({ page }) => {
    const resposta = await page.goto('/o-projeto');

    expect(resposta?.status()).toBe(200);
    await expect(page.getByRole('heading', { level: 1, name: /O Projeto Pedag/i })).toBeVisible();

    // O casco do React traz <div id="root">; a tela em Blade, não. É assim que
    // se prova que a rota saiu do fallback.
    await expect(page.locator('#root')).toHaveCount(0);
  });

  test('os pilares são numerados pela ordem em que aparecem', async ({ page }) => {
    await page.goto('/o-projeto');

    const pilares = page.locator('strong.text-rotulo');
    const total = await pilares.count();
    expect(total).toBeGreaterThan(0);

    // O número vem da posição, nunca do conteúdo editável: o primeiro cartão é
    // o 1 mesmo que o admin renomeie todos os pilares.
    for (let i = 0; i < total; i++) {
      await expect(page.getByText(String(i + 1), { exact: true }).first()).toBeVisible();
    }
  });

  test('a imagem carrega de verdade e não vaza a origem', async ({ page }) => {
    await page.goto('/o-projeto');

    const imagem = page.getByAltText('Escola Estadual da Cultura RJ');
    await expect(imagem).toBeVisible();
    await expect(imagem).toHaveAttribute('referrerpolicy', 'no-referrer');

    /*
     * `naturalWidth` zero significa que o endereço respondeu, mas não com uma
     * imagem — caixa vazia na tela e nenhum erro em lugar nenhum. O endereço é
     * editável pelo admin e aponta para fora, então é justamente o que pode
     * quebrar sem ninguém perceber.
     */
    await expect
      .poll(() => imagem.evaluate((el: HTMLImageElement) => el.naturalWidth), { timeout: 15_000 })
      .toBeGreaterThan(0);
  });

  test('o tema está ligado e o CSS institucional carregou', async ({ page }) => {
    await page.goto('/o-projeto');

    // Sem `tema-ava` no <html>, as regras da variante `ava:` ficam inertes.
    await expect(page.locator('html')).toHaveClass(/tema-ava/);

    // Peso 800 e a cor institucional vêm da regra `.tema-ava .font-titulo` do
    // `src/index.css`, e de mais lugar nenhum.
    const titulo = page.getByRole('heading', { level: 1 });
    await expect(titulo).toHaveCSS('font-weight', '800');
    await expect(titulo).toHaveCSS('color', 'rgb(4, 24, 92)');
  });

  for (const w of [320, 375, 768, 1440]) {
    test(`${w}px: não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await page.goto('/o-projeto');

      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});
