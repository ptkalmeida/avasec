import { test, expect } from '@playwright/test';
import { entrarComoAluno, rolaNaHorizontal } from './sessao';

/**
 * A seção só aparece depois que a MATRÍCULA chega do servidor.
 *
 * Enquanto não chega, `studentEnrollments[userId]` é `undefined`, e o painel
 * desenha "0 cursos ativos" — "ainda não chegou" e "não tem matrícula" são
 * indistinguíveis na leitura, como o próprio painel documenta. Uma captura com
 * `waitForTimeout(2500)` pegou exatamente esse instante e a imagem parecia perda
 * de dado. Por isso aqui se espera o título, nunca um cronômetro.
 */
const esperarSecao = async (page: import('@playwright/test').Page) =>
  expect(page.getByRole('heading', { name: /^Meus cursos$|^Meu curso$/ })).toBeVisible();

const LARGURAS = [320, 375, 768, 1024, 1440];

test.describe('Meus cursos no painel do aluno', () => {
  for (const w of LARGURAS) {
    test(`${w}px: a seção monta e a página não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await entrarComoAluno(page);
      await esperarSecao(page);

      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }

  test('separa o que está em andamento do que foi concluído', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);
    await esperarSecao(page);

    await expect(page.getByRole('heading', { name: 'Em andamento' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Concluídos' })).toBeVisible();

    // As contagens dos filtros vêm do mesmo dado dos cartões: se divergirem, é bug.
    const todos = await page.getByRole('tab', { name: /^Todos \(\d+\)$/ }).innerText();
    const andamento = await page.getByRole('tab', { name: /^Em andamento \(\d+\)$/ }).innerText();
    const concluidos = await page.getByRole('tab', { name: /^Concluídos \(\d+\)$/ }).innerText();

    const n = (t: string) => Number(t.match(/\((\d+)\)/)![1]);
    expect(n(todos)).toBe(n(andamento) + n(concluidos));
  });

  test('o filtro de situação esconde o grupo que não foi pedido', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);
    await esperarSecao(page);

    await page.getByRole('tab', { name: /^Concluídos/ }).click();
    await expect(page.getByRole('heading', { name: 'Em andamento' })).toBeHidden();
    await expect(page.getByRole('heading', { name: 'Concluídos' })).toBeVisible();
  });

  test('o progresso e a frequência aparecem juntos, e são coisas diferentes', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);
    await esperarSecao(page);

    const primeiro = page.locator('article').first();
    await expect(primeiro.getByText(/Seu progresso/i)).toBeVisible();
    await expect(primeiro.getByText(/\d+ de \d+ aulas concluídas/)).toBeVisible();
    // Frequência conta aulas E encontros ao vivo; é ela que decide o certificado.
    await expect(primeiro.getByText(/Frequência atual:/)).toBeVisible();
    await expect(primeiro.getByText(/mínimo \d+%/)).toBeVisible();
  });

  test('continuar o curso leva à sala de aula', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);
    await esperarSecao(page);

    await page.getByRole('button', { name: /continuar curso/i }).first().click();
    await expect(page.getByRole('button', { name: /sair do curso/i })).toBeVisible();
  });
});
