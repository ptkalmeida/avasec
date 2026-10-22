import { test, expect } from '@playwright/test';
import { entrarComoAdmin, rolaNaHorizontal } from './sessao';

/*
 * "Dados Gerenciais" do administrador, servidos pelo Laravel (M5, fatia 7).
 *
 * O conteúdo das planilhas é conferido no PHPUnit e, uma vez, linha a linha
 * contra o gerador antigo (TypeScript) sobre os dados reais. Aqui só um download
 * de verdade: cada um é auditado e passa pelo limitador de exportação (10 por
 * hora), então o e2e baixa UMA base, e não as cinco.
 */
test.describe('Dados Gerenciais do administrador — tela em Blade', () => {
  test('o item da sidebar leva à tela, com as cinco bases e a prévia', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);

    const sidebar = page.getByRole('navigation', { name: 'Navegação do painel administrativo' });
    await sidebar.getByRole('link', { name: /^Dados Gerenciais$/ }).click();

    await expect(page).toHaveURL(/\/admin\/dados-gerenciais$/);
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: 'Dados Gerenciais' })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /^Dados Gerenciais$/ })).toHaveAttribute('aria-current', 'page');

    const bases = page.getByRole('navigation', { name: 'Bases de dados' });
    await expect(bases.getByRole('link')).toHaveCount(5);
    await expect(page.getByRole('button', { name: /Baixar todas as 5 bases/ })).toBeVisible();
    await expect(page.getByText(/Visualização prévia/)).toBeVisible();
  });

  test('cada base tem endereço próprio e mostra as colunas dela', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);
    await page.goto('/admin/dados-gerenciais');

    const bases = page.getByRole('navigation', { name: 'Bases de dados' });
    for (const [nome, endereco, coluna] of [
      ['2. Base de Cursos', /base=cursos$/, 'percentual_minimo'],
      ['3. Base de Matrículas', /base=matriculas$/, 'status_matricula'],
      ['4. Progresso por Aula', /base=progresso$/, 'status_aula'],
      ['5. Base de Certificados', /base=certificados$/, 'codigo_validacao'],
      ['1. Base de Alunos', /base=alunos$/, 'status_conta'],
    ] as const) {
      await bases.getByRole('link', { name: nome }).click();
      await expect(page).toHaveURL(endereco);
      await expect(bases.getByRole('link', { name: nome })).toHaveAttribute('aria-current', 'page');
      await expect(page.getByRole('columnheader', { name: coluna })).toBeVisible();
    }
  });

  test('exportar uma base baixa o CSV com o nome de sempre', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);
    await page.goto('/admin/dados-gerenciais?base=cursos');

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('link', { name: /Exportar esta base/ }).click(),
    ]);

    expect(download.suggestedFilename()).toBe('base_cursos.csv');
    // Ficou na mesma tela: baixar não troca a página.
    await expect(page).toHaveURL(/base=cursos$/);
  });

  for (const largura of [320, 375, 768, 1440]) {
    test(`${largura}px: a tela e a tabela não rolam a página na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: largura, height: 900 });
      await entrarComoAdmin(page);
      await page.goto('/admin/dados-gerenciais?base=certificados');
      await expect(page.getByText(/Visualização prévia/)).toBeVisible();
      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});
