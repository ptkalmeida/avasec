import { test, expect } from '@playwright/test';
import { entrarComoAdmin, rolaNaHorizontal } from './sessao';

/*
 * Dashboard e Relatórios do administrador, servidos pelo Laravel (M5, fatia 2).
 *
 * Só leitura: nada aqui altera dado. Os números são conferidos no PHPUnit,
 * contra o banco; aqui se confere que as abas navegam entre endereços reais e
 * que nenhuma largura faz a página rolar na horizontal.
 */
test.describe('Dashboard do administrador — tela em Blade', () => {
  test('/admin abre a Visão Geral no servidor, com os quatro indicadores', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);

    await page.goto('/admin');

    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: /Dashboard & Relatórios/ })).toBeVisible();
    await expect(page.getByText('Alunos cadastrados')).toBeVisible();
    await expect(page.getByText('Cursos publicados')).toBeVisible();
    await expect(page.getByText('Matrículas ativas')).toBeVisible();
    await expect(page.getByText('Certificados emitidos')).toBeVisible();
  });

  test('cada aba tem endereço próprio, e a atual está marcada', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);
    await page.goto('/admin');

    const abas = page.getByRole('navigation', { name: 'Relatórios' });
    await expect(abas.getByRole('link', { name: 'Visão Geral' })).toHaveAttribute('aria-current', 'page');

    for (const [nome, caminho] of [
      ['Alunos', /\/admin\/relatorio\/alunos$/],
      ['Equipe Pedagógica', /\/admin\/relatorio\/professores$/],
      ['Cursos', /\/admin\/relatorio\/cursos$/],
      ['Matrículas', /\/admin\/relatorio\/inscricoes$/],
    ] as const) {
      await abas.getByRole('link', { name: nome }).click();
      await expect(page).toHaveURL(caminho);
      await expect(page.locator('#root')).toHaveCount(0);
      await expect(abas.getByRole('link', { name: nome })).toHaveAttribute('aria-current', 'page');
    }

    await abas.getByRole('link', { name: 'Visão Geral' }).click();
    await expect(page).toHaveURL(/\/admin$/);
  });

  test('o item Dashboard da sidebar fica marcado, e há botão de imprimir', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);
    await page.goto('/admin/relatorio/cursos');

    const sidebar = page.getByRole('navigation', { name: 'Navegação do painel administrativo' });
    await expect(sidebar.getByRole('link', { name: /Dashboard & Relatórios/ })).toHaveAttribute('aria-current', 'page');
    await expect(page.getByRole('button', { name: 'Imprimir relatório' })).toBeVisible();
  });

  test('de uma seção que ainda é React, "Dashboard & Relatórios" carrega a tela do servidor', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);
    await page.goto('/admin/alunos');
    await expect(page.locator('#root')).toHaveCount(1);

    await page.getByRole('button', { name: /Dashboard & Relatórios/ }).first().click();

    await expect(page).toHaveURL(/\/admin$/);
    // Sem casco do React: o SPA entregou a navegação ao servidor.
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 2, name: /^Relatório:/ })).toBeVisible();
  });

  test('a F5 em uma aba volta para a MESMA aba', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);
    await page.goto('/admin/relatorio/alunos');

    await page.reload();
    await page.waitForTimeout(1500);

    await expect(page).toHaveURL(/\/admin\/relatorio\/alunos$/);
    await expect(page.getByRole('heading', { level: 2, name: /Relatório: Alunos/ })).toBeVisible();
  });

  for (const largura of [320, 375, 768, 1440]) {
    test(`${largura}px: nenhuma aba rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: largura, height: 900 });
      await entrarComoAdmin(page);

      for (const caminho of ['/admin', '/admin/relatorio/alunos', '/admin/relatorio/professores', '/admin/relatorio/cursos', '/admin/relatorio/inscricoes']) {
        await page.goto(caminho);
        await expect(page.getByRole('heading', { level: 2, name: /^Relatório:/ })).toBeVisible();
        expect(await rolaNaHorizontal(page), caminho).toBe(false);
      }
    });
  }
});
