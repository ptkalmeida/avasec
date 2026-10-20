import { test, expect } from '@playwright/test';
import { entrarComoInstrutor, rolaNaHorizontal } from './sessao';

/*
 * `/inst` em Blade — a escolha de curso.
 *
 * Posse de curso e os estados (vigência, publicação) são conferidos no
 * PHPUnit. Aqui: que a tela é servida pelo servidor, que dela se chega ao
 * curso, e que ela cabe nas larguras.
 */
test.describe('Escolha de curso do instrutor — tela em Blade', () => {
  test('é servida pelo servidor e lista os cursos', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);

    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('link', { name: /Aulas/ }).first()).toBeVisible();
  });

  /*
   * A fronteira: as seções de dentro do curso ainda são React. Clicar num
   * curso sai do Blade e entra no SPA — e tem de abrir o painel daquele curso,
   * não a tela de escolha do React.
   */
  test('do Blade se chega ao painel do curso, que ainda é React', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);

    await page.getByRole('link', { name: /Aulas/ }).first().click();

    await expect(page).toHaveURL(/\/inst\/curso\/[^/]+$/);
    await expect(page.locator('#root')).toHaveCount(1);
  });

  /*
   * O defeito que a escolha em Blade criou, preso aqui.
   *
   * Chegar ao painel React passou a ser um carregamento de página inteiro, e o
   * efeito que devolve "curso que não é seu" para `/inst` rodava ANTES de a
   * lista de cursos chegar: clicar num curso voltava para a lista, e nenhum
   * curso abria. Esperar quatro segundos é o que separa "abriu" de "abriu e
   * voltou" — sem a espera, o teste passa com o defeito presente.
   */
  test('abrir um curso não volta para a lista enquanto o painel carrega', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);

    await page.getByRole('link', { name: /Aulas/ }).first().click();
    const enderecoDoCurso = page.url();

    await page.waitForTimeout(4000);

    expect(page.url()).toBe(enderecoDoCurso);
  });

  test('o menu não oferece seção de curso antes da escolha', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);

    const menu = page.getByRole('navigation', { name: 'Navegação da área do instrutor' });
    await expect(menu.getByRole('link', { name: 'Meus cursos' })).toBeVisible();
    await expect(menu.getByRole('link', { name: 'Grade curricular' })).toHaveCount(0);
  });

  test('sair da conta encerra a sessão', async ({ page }) => {
    await entrarComoInstrutor(page);

    await page.getByRole('button', { name: /^Sair$/ }).click();

    await page.goto('/inst');
    await expect(page).toHaveURL(/\/entrar/);
  });

  for (const w of [320, 375, 768, 1440]) {
    test(`${w}px: não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await entrarComoInstrutor(page);

      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});
