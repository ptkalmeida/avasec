import { test, expect } from '@playwright/test';
import { rolaNaHorizontal } from './sessao';

/*
 * `/calendario` em Blade — a última página pública a sair do React.
 *
 * A agenda vem dos encontros realmente marcados, então o que esta tela mostra
 * depende do banco do momento. As regras (janela, ordem, visibilidade, link de
 * sala que não sai) estão no PHPUnit e no teste de unidade do porte; aqui fica
 * o que só o navegador responde, e os dois estados são tratados: com agenda e
 * sem agenda. Um teste que exigisse cartão na tela falharia num dia em que
 * ninguém marcou nada — e não haveria defeito nenhum.
 */
test.describe('Calendário — tela em Blade', () => {
  test('responde no mesmo endereço, sem passar pelo React', async ({ page }) => {
    const resposta = await page.goto('/calendario');

    expect(resposta?.status()).toBe(200);
    await expect(page.getByRole('heading', { level: 1, name: /Calend[áa]rio/i })).toBeVisible();
    await expect(page.locator('#root')).toHaveCount(0);
  });

  test('ou lista encontros marcados, ou explica a ausência — nunca uma grade muda', async ({ page }) => {
    await page.goto('/calendario');

    const cartoes = page.locator('main li');
    const total = await cartoes.count();

    if (total === 0) {
      await expect(page.getByText(/Nenhum encontro agendado para os pr[óo]ximos 30 dias/i)).toBeVisible();
      await expect(page.getByText(/apenas encontros realmente marcados/i)).toBeVisible();

      return;
    }

    // Com agenda: o tipo é escrito, e não apenas colorido na faixa lateral.
    await expect(cartoes.first().getByText(/Aula ao vivo|Webinar aberto/)).toBeVisible();
    await expect(page.getByText(/encontros? nos pr[óo]ximos 30 dias/i)).toBeVisible();
  });

  test('o visitante é convidado a entrar, e não a uma sala', async ({ page }) => {
    await page.goto('/calendario');

    const participar = page.getByRole('link', { name: /^Participar$/ });

    if (await participar.count() === 0) {
      test.skip(true, 'Sem encontro na janela: não há convite a conferir agora.');
    }

    await expect(participar.first()).toHaveAttribute('href', '/entrar');
  });

  test('a entrada "Ajuda" do menu acende nesta página', async ({ page }) => {
    await page.goto('/calendario');

    await expect(page.locator('[aria-current]').first()).toContainText(/Ajuda/i);
  });

  for (const w of [320, 375, 768, 1440]) {
    test(`${w}px: não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await page.goto('/calendario');

      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});
