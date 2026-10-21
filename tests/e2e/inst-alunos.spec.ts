import { test, expect } from '@playwright/test';
import { entrarComoInstrutor, rolaNaHorizontal } from './sessao';

/*
 * `/inst/curso/{ref}/alunos` e os endereços antigos do instrutor.
 *
 * Nenhum teste aqui aprova ou reprova matrícula: o aluno e o curso de
 * demonstração são compartilhados. A decisão é conferida no PHPUnit.
 */
const CURSO = '/inst/curso/portfolio-de-comprovacao-da-atuacao-cultural';

test.describe('Alunos do instrutor — tela em Blade', () => {
  test('o menu leva à turma, servida pelo servidor', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);
    await page.goto(CURSO);

    await page.getByRole('navigation', { name: 'Navegação da área do instrutor' })
      .getByRole('link', { name: /^Alunos/ }).click();

    await expect(page).toHaveURL(/\/alunos$/);
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: 'Alunos' })).toBeVisible();
    await expect(page.getByRole('heading', { level: 2, name: 'Turma' })).toBeVisible();
  });

  test('com a aprovação automática, a tela diz por que não há fila', async ({ page }) => {
    await entrarComoInstrutor(page);
    await page.goto(`${CURSO}/alunos`);

    await expect(page.getByText('aprovadas automaticamente')).toBeVisible();
    await expect(page.getByText('Pedidos de matrícula')).toHaveCount(0);
  });

  test('a turma mostra o que o banco sabe, e nada do que o painel antigo inventava', async ({ page }) => {
    await entrarComoInstrutor(page);
    await page.goto(`${CURSO}/alunos`);

    const html = await page.content();
    expect(html).not.toContain('Presença');
    expect(html).not.toContain('@escola.dev.br');
  });

  /*
   * Os endereços que o painel antigo tinha e que não existem mais como seção.
   * Antes caíam no React, que os tratava como "Gestão do Curso" e desenhava o
   * painel ANTIGO — e dali se navegava, por dentro do SPA, às telas velhas.
   */
  test('a caixa de mensagens por curso leva à caixa única', async ({ page }) => {
    await entrarComoInstrutor(page);
    await page.goto(`${CURSO}/mensagens`);

    await expect(page).toHaveURL(/\/inst\/mensagens$/);
    await expect(page.locator('#root')).toHaveCount(0);
  });

  test('seção desconhecida do curso volta para a Gestão do Curso, do servidor', async ({ page }) => {
    await entrarComoInstrutor(page);
    await page.goto(`${CURSO}/secao-que-nao-existe`);

    await expect(page).toHaveURL(new RegExp(`${CURSO}$`));
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 2, name: 'Panorama' })).toBeVisible();
  });

  test('endereço desconhecido do instrutor volta para a escolha de curso', async ({ page }) => {
    await entrarComoInstrutor(page);
    await page.goto('/inst/qualquer-coisa');

    await expect(page).toHaveURL(/\/inst$/);
    await expect(page.locator('#root')).toHaveCount(0);
  });

  for (const w of [320, 375, 768, 1440]) {
    test(`${w}px: a turma não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await entrarComoInstrutor(page);
      await page.goto(`${CURSO}/alunos`);
      await expect(page.getByRole('heading', { level: 1, name: 'Alunos' })).toBeVisible();

      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});
