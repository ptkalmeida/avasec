import { test, expect } from '@playwright/test';
import { entrarComoInstrutor, rolaNaHorizontal } from './sessao';

/*
 * `/inst` e `/inst/curso/{ref}` em Blade — a escolha de curso e a Gestão do
 * Curso.
 *
 * Posse de curso e os estados (vigência, publicação) são conferidos no
 * PHPUnit. Aqui: que as telas são servidas pelo servidor, que de uma se chega
 * à outra, e que cabem nas larguras.
 *
 * A grade curricular, as avaliações e os alunos ainda são React, e a fronteira
 * com eles é o que mais quebrou nesta migração.
 */
test.describe('Escolha de curso e Gestão do Curso — telas em Blade', () => {
  test('a escolha é servida pelo servidor e lista os cursos', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);

    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('link', { name: /Aulas/ }).first()).toBeVisible();
  });

  test('da escolha se chega à Gestão do Curso, também servida pelo servidor', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);

    await page.getByRole('link', { name: /Aulas/ }).first().click();

    await expect(page).toHaveURL(/\/inst\/curso\/[^/]+$/);
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 2, name: 'Panorama' })).toBeVisible();
  });

  /*
   * A fronteira que sobrou: a grade curricular ainda é React. Sair do Blade
   * para ela é um carregamento de página inteiro, e tem de abrir o painel do
   * curso — e não a tela de escolha.
   */
  test('da Gestão do Curso se chega à grade, que ainda é React', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);
    await page.getByRole('link', { name: /Aulas/ }).first().click();

    await page.getByRole('link', { name: /^Grade curricular/ }).first().click();

    await expect(page).toHaveURL(/\/grade-curricular$/);
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
  test('abrir a grade não volta para a lista enquanto o painel carrega', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);
    await page.getByRole('link', { name: /Aulas/ }).first().click();
    await page.getByRole('link', { name: /^Grade curricular/ }).first().click();
    const enderecoDaGrade = page.url();

    await page.waitForTimeout(4000);

    expect(page.url()).toBe(enderecoDaGrade);
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

  /*
   * Nenhum teste aqui SALVA propriedades ou vigência: o curso de demonstração
   * é compartilhado, e uma renovação mudaria a regra de oferta para todos os
   * outros testes. A gravação é conferida no PHPUnit, em transação. Aqui, que
   * os formulários existem e abrem.
   */
  test('os formulários de vigência e de propriedades abrem', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);
    await page.getByRole('link', { name: /Aulas/ }).first().click();

    await page.getByText('Propriedades do curso').click();
    await expect(page.getByLabel('Título')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Salvar propriedades' })).toBeVisible();

    await page.getByText('Vigência de exibição').first().click();
    await expect(page.getByRole('button', { name: 'Salvar vigência' })).toBeVisible();
  });

  for (const w of [320, 375, 768, 1440]) {
    test(`${w}px: escolha e Gestão do Curso não rolam na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await entrarComoInstrutor(page);

      expect(await rolaNaHorizontal(page)).toBe(false);

      await page.getByRole('link', { name: /Aulas/ }).first().click();
      await expect(page.getByRole('heading', { level: 2, name: 'Panorama' })).toBeVisible();

      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});
