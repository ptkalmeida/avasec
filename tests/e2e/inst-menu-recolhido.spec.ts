import { test, expect } from '@playwright/test';
import { entrarComoInstrutor } from './sessao';

/**
 * Menu recolhido do gestor: o símbolo da escola e o botão de recolher cabem em 48px úteis só se
 * ficarem empilhados. Lado a lado eles se sobrepunham (o mesmo defeito já corrigido no menu do aluno).
 */
test.describe('Menu lateral do gestor, recolhido', () => {
  test('o símbolo e o botão de expandir não se sobrepõem', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);
    await page.waitForLoadState('networkidle');

    await page.getByRole('button', { name: 'Recolher o menu' }).click();
    await expect(page.getByRole('button', { name: 'Expandir o menu' })).toBeVisible();

    const simbolo = await page.getByRole('link', { name: /Escola Estadual/ }).boundingBox();
    const botao = await page.getByRole('button', { name: 'Expandir o menu' }).boundingBox();
    expect(simbolo).not.toBeNull();
    expect(botao).not.toBeNull();

    // Sem interseção: um termina antes de o outro começar (na vertical, já que empilham).
    const semSobrepor = simbolo!.y + simbolo!.height <= botao!.y + 1 || botao!.y + botao!.height <= simbolo!.y + 1;
    expect(semSobrepor).toBe(true);
  });
});
