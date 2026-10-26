import { test, expect } from '@playwright/test';
import { entrarComoInstrutor, rolaNaHorizontal } from './sessao';

/*
 * Prévia ao vivo do trecho e histórico de versões da aula (ADR 021).
 *
 * Só leitura: a prévia não grava por definição, e o histórico só é LISTADO aqui.
 * Salvar e restaurar são conferidos no PHPUnit, dentro de uma transação.
 */
const CURSO = '/inst/curso/portfolio-de-comprovacao-da-atuacao-cultural';
const AULA = `${CURSO}/aulas/lesson-course-1789497468067-1789497813942`;

test.describe('Prévia ao vivo e histórico da aula — Blade', () => {
  test('a prévia acompanha o que se digita, sem gravar', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);
    await page.goto(`${AULA}?editar=material&bloco=1`);
    await page.waitForLoadState('networkidle');

    const previa = page.getByText('Prévia — como o aluno vai ver').locator('..');
    await expect(previa).toBeVisible();

    const campo = page.locator('form textarea[name="texto"]').first();
    await campo.fill('Frase de prévia com **destaque** feita só para o teste.');

    await expect(previa).toContainText('Frase de prévia com');
    await expect(previa.locator('strong', { hasText: 'destaque' })).toBeVisible();

    // Nada foi gravado: a página, recarregada, não tem a frase.
    await page.goto(AULA);
    await expect(page.getByText('Frase de prévia com')).toHaveCount(0);
  });

  test('o histórico aparece no fim da página e não rola na horizontal', async ({ page }) => {
    for (const largura of [320, 768, 1440]) {
      await page.setViewportSize({ width: largura, height: 900 });
      await entrarComoInstrutor(page);
      await page.goto(AULA);

      await expect(page.getByRole('heading', { level: 2, name: 'Histórico de versões' })).toBeVisible();
      expect(await rolaNaHorizontal(page), `${largura}px`).toBe(false);
    }
  });
});
