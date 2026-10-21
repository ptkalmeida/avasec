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
 * A grade curricular também é do servidor. O EDITOR de aula, as avaliações e
 * os alunos ainda são React, e a fronteira com eles é o que mais quebrou nesta
 * migração.
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

  test('da Gestão do Curso se chega à grade, também servida pelo servidor', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);
    await page.getByRole('link', { name: /Aulas/ }).first().click();

    await page.getByRole('link', { name: /^Grade curricular/ }).first().click();

    await expect(page).toHaveURL(/\/grade-curricular$/);
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: 'Grade curricular' })).toBeVisible();
  });

  /*
   * A fronteira que sobrou: o EDITOR de aula é React, com endereço próprio.
   *
   * Dois defeitos nasceram aqui e ficam presos:
   *  1. o efeito que devolve "curso que não é seu" para `/inst` rodava ANTES de
   *     a lista de cursos chegar — clicar no editor voltava para a lista;
   *  2. se a aula aberta vivesse DENTRO de `/grade-curricular`, o servidor a
   *     levaria de volta ao Blade e o editor ficaria inalcançável.
   * Esperar quatro segundos é o que separa "abriu" de "abriu e voltou": sem a
   * espera, o teste passa com o defeito presente.
   */
  test('da grade se chega ao editor da aula, que é React e não volta sozinho', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);
    await page.getByRole('link', { name: /Aulas/ }).first().click();
    await page.getByRole('link', { name: /^Grade curricular/ }).first().click();

    await page.getByRole('link', { name: /^Editar conteúdo/ }).first().click();
    await expect(page).toHaveURL(/\/aula\/[^/]+$/);
    const enderecoDoEditor = page.url();

    await page.waitForTimeout(4000);

    expect(page.url()).toBe(enderecoDoEditor);
    await expect(page.locator('#root')).toHaveCount(1);
    await expect(page.getByText('Voltar ao currículo')).toBeVisible();
  });

  test('do editor, voltar ao currículo leva à grade do servidor', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);
    await page.getByRole('link', { name: /Aulas/ }).first().click();
    await page.getByRole('link', { name: /^Grade curricular/ }).first().click();
    await page.getByRole('link', { name: /^Editar conteúdo/ }).first().click();
    await page.getByText('Voltar ao currículo').click();

    await expect(page).toHaveURL(/\/grade-curricular$/);
    await expect(page.locator('#root')).toHaveCount(0);
  });

  test('a F5 no editor volta para a MESMA aula, e não para a lista', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);
    await page.getByRole('link', { name: /Aulas/ }).first().click();
    await page.getByRole('link', { name: /^Grade curricular/ }).first().click();
    await page.getByRole('link', { name: /^Editar conteúdo/ }).first().click();
    const endereco = page.url();

    await page.reload();
    await page.waitForTimeout(3000);

    expect(page.url()).toBe(endereco);
    await expect(page.getByText('Voltar ao currículo')).toBeVisible();
  });

  /*
   * Nenhum teste aqui MOVE ou RETIRA aula: o curso de demonstração é
   * compartilhado, e mudar a ordem dele mudaria a aula que os outros testes
   * abrem primeiro. A gravação é conferida no PHPUnit, em transação. Aqui: que
   * a confirmação de retirada abre, diz o que acontece e pode ser recusada.
   */
  test('retirar pede confirmação na página, e Manter desiste', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);
    await page.getByRole('link', { name: /Aulas/ }).first().click();
    await page.getByRole('link', { name: /^Grade curricular/ }).first().click();

    await page.getByRole('button', { name: /^Retirar da grade/ }).first().click();
    await expect(page.getByText('Nada é apagado').first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Retirar a aula' }).first()).toBeVisible();

    await page.getByRole('button', { name: 'Manter' }).first().click();
    await expect(page.getByRole('button', { name: 'Retirar a aula' }).first()).toBeHidden();
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
    test(`${w}px: escolha, Gestão do Curso e grade não rolam na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await entrarComoInstrutor(page);

      expect(await rolaNaHorizontal(page)).toBe(false);

      await page.getByRole('link', { name: /Aulas/ }).first().click();
      await expect(page.getByRole('heading', { level: 2, name: 'Panorama' })).toBeVisible();

      expect(await rolaNaHorizontal(page)).toBe(false);

      await page.getByRole('link', { name: /^Grade curricular/ }).first().click();
      await expect(page.getByRole('heading', { level: 1, name: 'Grade curricular' })).toBeVisible();

      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});
