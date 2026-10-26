import { test, expect } from '@playwright/test';
import { entrarComoAdmin, rolaNaHorizontal } from './sessao';

/*
 * "Alunos" do administrador, servido pelo Laravel (M5, fatia 5).
 *
 * Matricular, redefinir senha, matrícula múltipla e retirar da plataforma são
 * conferidos no PHPUnit, dentro de uma transação: o banco de desenvolvimento é
 * compartilhado, e um e2e que criasse conta ou trocasse senha deixaria lixo (ou
 * trancaria as outras suítes). Aqui se confere o que só o navegador mostra.
 */
test.describe('Alunos do administrador — tela em Blade', () => {
  test('o item da sidebar leva à lista do servidor, marcado como atual', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);

    const sidebar = page.getByRole('navigation', { name: 'Navegação do painel administrativo' });
    await sidebar.getByRole('link', { name: /^Alunos$/ }).click();

    await expect(page).toHaveURL(/\/admin\/alunos$/);
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: 'Alunos' })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /^Alunos$/ })).toHaveAttribute('aria-current', 'page');
  });

  test('o formulário de matricular tem os campos do painel antigo, com o CPF', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);
    await page.goto('/admin/alunos');

    await expect(page.getByLabel('Nome completo')).toBeVisible();
    await expect(page.getByLabel('E-mail acadêmico')).toBeVisible();
    await expect(page.getByLabel(/^CPF/)).toBeVisible();
    await expect(page.getByLabel('Curso de matrícula')).toBeVisible();
    await expect(page.getByLabel(/Senha inicial/)).toBeVisible();
    await expect(page.getByLabel('Município')).toBeVisible();
    await expect(page.getByLabel('UF')).toBeVisible();
    await expect(page.getByLabel('Área de interesse')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Matricular aluno' })).toBeVisible();
  });

  test('o cadastro com campo inválido volta com a explicação, sem criar nada', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);
    await page.goto('/admin/alunos');

    // Só um nome (sem sobrenome) e nenhum CPF válido: o servidor recusa, e nada é gravado.
    await page.getByLabel('Nome completo').fill('Clara');
    await page.getByLabel('E-mail acadêmico').fill('clara@example.com');
    await page.getByLabel(/^CPF/).fill('111.111.111-11');
    await page.getByLabel('Curso de matrícula').selectOption({ index: 1 });
    await page.getByRole('button', { name: 'Matricular aluno' }).click();

    await expect(page).toHaveURL(/\/admin\/alunos$/);
    await expect(page.getByRole('alert')).toContainText('Não foi possível matricular');
    await expect(page.getByRole('alert')).toContainText('sobrenome');
    // O que a pessoa digitou continua no campo.
    await expect(page.getByLabel('Nome completo')).toHaveValue('Clara');
  });

  test('a busca e os filtros ficam no endereço', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);
    await page.goto('/admin/alunos');

    await page.getByLabel('Buscar').fill('aluno-que-nao-existe-xyz');
    await page.getByLabel('Matrícula', { exact: true }).selectOption('sem');
    await page.getByRole('button', { name: 'Filtrar' }).click();

    await expect(page).toHaveURL(/busca=aluno-que-nao-existe-xyz/);
    await expect(page).toHaveURL(/matricula=sem/);
    await expect(page.getByText('Nenhum aluno com esses filtros.')).toBeVisible();

    await page.getByRole('link', { name: 'Limpar filtros' }).click();
    await expect(page).toHaveURL(/\/admin\/alunos$/);
  });

  test('da lista se chega à ficha, que mostra as ações e volta para a lista', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);
    await page.goto('/admin/alunos');

    await page.getByRole('link', { name: 'Ficha' }).first().click();

    await expect(page).toHaveURL(/\/admin\/alunos\/[^/]+$/);
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 2, name: 'Dados da conta' })).toBeVisible();
    await expect(page.getByRole('heading', { level: 2, name: 'Matrículas e progresso' })).toBeVisible();
    await expect(page.getByLabel('Redefinir senha')).toBeVisible();

    // "Retirar da plataforma" pede uma segunda confirmação.
    await expect(page.getByRole('button', { name: 'Sim, retirar da plataforma' })).toBeHidden();
    await page.getByText('Retirar da plataforma', { exact: true }).click();
    await expect(page.getByRole('button', { name: 'Sim, retirar da plataforma' })).toBeVisible();

    await page.getByRole('link', { name: /Voltar para Alunos/ }).click();
    await expect(page).toHaveURL(/\/admin\/alunos$/);
  });

  test('ficha de aluno que não existe responde 404', async ({ page }) => {
    await entrarComoAdmin(page);

    const resposta = await page.goto('/admin/alunos/nao-existe-esse-id');

    expect(resposta?.status()).toBe(404);
  });

  for (const largura of [320, 375, 768, 1440]) {
    test(`${largura}px: lista e ficha não rolam na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: largura, height: 900 });
      await entrarComoAdmin(page);

      await page.goto('/admin/alunos');
      await expect(page.getByRole('heading', { level: 2, name: /^Alunos \(/ })).toBeVisible();
      expect(await rolaNaHorizontal(page), 'lista').toBe(false);

      await page.getByRole('link', { name: 'Ficha' }).first().click();
      await expect(page.getByRole('heading', { level: 2, name: 'Dados da conta' })).toBeVisible();
      expect(await rolaNaHorizontal(page), 'ficha').toBe(false);
    });
  }
});
