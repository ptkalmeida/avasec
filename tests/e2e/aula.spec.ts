import { test, expect } from '@playwright/test';
import { abrirPrimeiraAula, entrarComoAluno, rolaNaHorizontal } from './sessao';

/*
 * O que estes testes cobrem é o que SÓ se vê com a tela montada: em que largura um
 * elemento aparece, se o texto cabe, se a página rola de lado. Os 722 testes do
 * Vitest provam a lógica dos componentes em memória e não enxergam nada disso —
 * `MateriaisDeApoio` passa nos dois lugares, e só a largura decide qual é o certo.
 *
 * As medições aqui foram feitas à mão em 13/10/2026 antes de virarem teste. Este
 * arquivo as congela.
 */

/** `lg` do Tailwind. Abaixo não há barra lateral; a partir daí, há. */
const LG = 1024;

const LARGURAS = [
  { nome: 'celular estreito', w: 320, h: 900 },
  { nome: 'celular comum', w: 375, h: 900 },
  { nome: 'tablet', w: 768, h: 1100 },
  { nome: 'desktop no limite', w: LG, h: 900 },
  { nome: 'desktop largo', w: 1440, h: 900 },
];

test.describe('Sala de aula do aluno', () => {
  for (const { nome, w, h } of LARGURAS) {
    test(`${nome} (${w}px): não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: h });
      await entrarComoAluno(page);
      await abrirPrimeiraAula(page);

      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }

  for (const { nome, w, h } of LARGURAS) {
    test(`${nome} (${w}px): suporte é aba abaixo de lg e cartão a partir de lg`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: h });
      await entrarComoAluno(page);
      await abrirPrimeiraAula(page);

      const aba = page.getByRole('button', { name: /suporte pedagógico/i });
      const cartao = page.locator('aside').getByRole('heading', { name: /suporte pedagógico/i });

      if (w >= LG) {
        await expect(cartao).toBeVisible();
        await expect(aba).toBeHidden();
      } else {
        await expect(aba).toBeVisible();
        await expect(cartao).toBeHidden();
      }
    });
  }

  test('a tira de abas cabe na largura disponível em 375px', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 900 });
    await entrarComoAluno(page);
    await abrirPrimeiraAula(page);

    /*
     * Esta é a regressão que custou um defeito real. A tira pedia 534 px e tinha
     * 299; ela não é rolável, então o transbordo escapava para o cartão PAI, cujo
     * `overflow-hidden` recorta mas ainda rola por foco. O painel de conteúdo, irmão
     * da tira dentro desse pai, era arrastado junto — o texto saía cortado pela
     * esquerda. Bastava tabular até a aba para cair nisso.
     */
    const medida = await page.evaluate(() => {
      const aba = [...document.querySelectorAll('button')].find((b) =>
        (b.textContent ?? '').includes('Suporte Pedag'),
      );
      if (!aba?.parentElement?.parentElement) return null;

      const tira = aba.parentElement;
      const pai = tira.parentElement;
      aba.focus();

      return {
        tira: { scrollW: tira.scrollWidth, clientW: tira.clientWidth, scrollLeft: tira.scrollLeft },
        pai: { scrollW: pai.scrollWidth, clientW: pai.clientWidth, scrollLeft: pai.scrollLeft },
      };
    });

    expect(medida, 'a aba de suporte deveria existir em 375px').not.toBeNull();
    expect(medida!.tira.scrollW).toBeLessThanOrEqual(medida!.tira.clientW);
    expect(medida!.pai.scrollW).toBeLessThanOrEqual(medida!.pai.clientW);
    // Sem transbordo, o foco não tem como arrastar o painel.
    expect(medida!.pai.scrollLeft).toBe(0);
  });

  test('o cartão da lateral não afirma presença nem prazo de resposta', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);
    await abrirPrimeiraAula(page);

    const lateral = page.locator('aside');
    await expect(lateral.getByRole('heading', { name: /suporte pedagógico/i })).toBeVisible();

    // O selo vinha do `localStorage` do navegador do próprio aluno, verde por
    // padrão; o prazo estava escrito no código. Decisão de 13/10: ficam de fora.
    await expect(lateral.getByText(/\bONLINE\b|\bOFFLINE\b/)).toHaveCount(0);
    await expect(lateral.getByText(/tempo de resposta/i)).toHaveCount(0);
  });

  test('o progresso do curso aparece na lateral e fala por número, não só por cor', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);
    await abrirPrimeiraAula(page);

    const lateral = page.locator('aside');
    await expect(lateral.getByText(/seu progresso no curso/i)).toBeVisible();
    // D §2.13: progresso que só existe como cor não é legível por quem não a distingue.
    await expect(lateral.getByText(/\d+%/).first()).toBeVisible();
    await expect(lateral.getByText(/\d+ de \d+ aulas concluídas/)).toBeVisible();
  });
});
