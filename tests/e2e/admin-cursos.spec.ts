import { test, expect } from '@playwright/test';
import { entrarComoAdmin, rolaNaHorizontal } from './sessao';

/*
 * "Cursos & Trilhas" do administrador, servido pelo Laravel (M5, fatia 4).
 *
 * As escritas (prover, trocar professor, vigência, capa, retirar do ar) são
 * conferidas no PHPUnit, dentro de uma transação: o banco de desenvolvimento é
 * compartilhado, e um e2e que criasse ou retirasse curso deixaria lixo a cada
 * execução. Aqui se confere o que só o navegador mostra.
 */
test.describe('Cursos & Trilhas do administrador — tela em Blade', () => {
  test('o item da sidebar leva à tela do servidor, marcado como atual', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);

    const sidebar = page.getByRole('navigation', { name: 'Navegação do painel administrativo' });
    await sidebar.getByRole('link', { name: /Cursos & Trilhas/ }).click();

    await expect(page).toHaveURL(/\/admin\/cursos$/);
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: 'Cursos & Trilhas' })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /Cursos & Trilhas/ })).toHaveAttribute('aria-current', 'page');
  });

  test('o formulário de prover tem os campos do painel antigo', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);
    await page.goto('/admin/cursos');

    await expect(page.getByLabel('Título do curso')).toBeVisible();
    await expect(page.getByLabel('Área acadêmica / categoria')).toBeVisible();
    await expect(page.getByLabel('Professor responsável')).toBeVisible();
    await expect(page.getByLabel(/Ementa/)).toBeVisible();
    await expect(page.getByLabel(/Vigência de exibição/)).toBeVisible();
    await expect(page.getByLabel('Tipo de curso')).toBeVisible();
    await expect(page.getByLabel('Ativar chat do curso')).toBeChecked();
    await expect(page.getByRole('button', { name: 'Disponibilizar curso no catálogo' })).toBeVisible();
  });

  test('cada curso abre a lista de alunos e o bloco de gerenciar', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);
    await page.goto('/admin/cursos');

    const primeiro = page.locator('article').first();
    await expect(primeiro.getByText(/Alunos ativos \(\d+\)/)).toBeVisible();

    await primeiro.getByText('Gerenciar este curso').click();
    await expect(primeiro.getByRole('button', { name: 'Trocar professor' })).toBeVisible();
    await expect(primeiro.getByRole('button', { name: 'Salvar tipo e chat' })).toBeVisible();
    await expect(primeiro.getByRole('button', { name: /Salvar vigência|Reativar curso/ })).toBeVisible();
    await expect(primeiro.getByRole('button', { name: 'Enviar capa' })).toBeVisible();

    // "Retirar do ar" pede uma segunda confirmação: o botão que age só aparece depois.
    await expect(primeiro.getByRole('button', { name: 'Sim, retirar do ar' })).toBeHidden();
    await primeiro.getByText('Retirar do ar', { exact: true }).click();
    await expect(primeiro.getByRole('button', { name: 'Sim, retirar do ar' })).toBeVisible();
  });

  test('a busca filtra, e sem resultado diz que nada foi encontrado', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);
    await page.goto('/admin/cursos');

    await page.getByLabel('Pesquisar curso ou professor').fill('curso-que-nao-existe-xyz');
    await page.getByRole('button', { name: 'Buscar' }).click();

    await expect(page).toHaveURL(/busca=curso-que-nao-existe-xyz/);
    await expect(page.getByText('Nenhum curso encontrado para essa busca.')).toBeVisible();
  });

  for (const largura of [320, 375, 768, 1440]) {
    test(`${largura}px: não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: largura, height: 900 });
      await entrarComoAdmin(page);
      await page.goto('/admin/cursos');
      await expect(page.getByRole('heading', { level: 2, name: /^Cursos ativos no catálogo/ })).toBeVisible();
      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});
