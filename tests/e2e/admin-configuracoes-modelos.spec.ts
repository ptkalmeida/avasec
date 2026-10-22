import { test, expect } from '@playwright/test';
import { entrarComoAdmin, rolaNaHorizontal } from './sessao';

/*
 * "Configurações" e "Modelos de Certificado e Histórico" do administrador,
 * servidos pelo Laravel (M5, fatia 6).
 *
 * Gravar é conferido no PHPUnit, dentro de uma transação: são tabelas GLOBAIS
 * (o que o aluno vê muda para todos), e um e2e que as gravasse deixaria o banco
 * de desenvolvimento diferente a cada execução. Aqui se confere o que só o
 * navegador mostra.
 */
test.describe('Configurações e Modelos do administrador — telas em Blade', () => {
  test('Configurações: os interruptores e o botão que grava de verdade', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);

    const sidebar = page.getByRole('navigation', { name: 'Navegação do painel administrativo' });
    await sidebar.getByRole('link', { name: /^Configurações$/ }).click();

    await expect(page).toHaveURL(/\/admin\/configuracoes$/);
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: 'Configurações' })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: /^Configurações$/ })).toHaveAttribute('aria-current', 'page');

    await expect(page.getByLabel(/Habilitar mensagens diretas/)).toBeVisible();
    await expect(page.getByLabel(/Habilitar chat global/)).toBeVisible();
    await expect(page.getByLabel(/Inscrições abertas/)).toBeVisible();
    await expect(page.getByLabel(/Emissão automática de certificados/)).toBeVisible();
    await expect(page.getByLabel(/Gravação automática/)).toBeVisible();
    await expect(page.getByLabel(/Duração do arquivamento/)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Salvar configurações' })).toBeVisible();

    // O controle de presença que não gravava nada não voltou.
    await expect(page.locator('input[type="range"]')).toHaveCount(0);
  });

  test('Modelos: alterna entre certificado e histórico pelo endereço', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);

    const sidebar = page.getByRole('navigation', { name: 'Navegação do painel administrativo' });
    await sidebar.getByRole('link', { name: /Modelos de Certificado e Histórico/ }).click();

    await expect(page).toHaveURL(/\/admin\/templates$/);
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByLabel('Nome da instituição')).toBeVisible();
    await expect(page.getByLabel('Texto de rodapé')).toBeVisible();
    await expect(page.getByRole('link', { name: /Visualizar documento atual/ })).toBeVisible();

    await page.getByRole('navigation', { name: 'Tipo de documento' }).getByRole('link', { name: 'Histórico Escolar' }).click();
    await expect(page).toHaveURL(/tipo=historico/);
    await expect(page.getByText(/ainda não tem emissão de PDF própria/)).toBeVisible();
    await expect(page.getByRole('link', { name: /Visualizar documento atual/ })).toHaveCount(0);
  });

  test('Modelos: adicionar assinatura acrescenta uma linha SEM gravar, e F5 a descarta', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);
    await page.goto('/admin/templates');

    const antes = await page.getByPlaceholder('Nome').count();
    await page.getByRole('button', { name: 'Adicionar assinatura' }).click();

    await expect(page.getByPlaceholder('Nome')).toHaveCount(antes + 1);

    // Nada foi gravado: abrir o endereço de novo mostra só o que está no banco.
    await page.goto('/admin/templates');
    await expect(page.getByPlaceholder('Nome')).toHaveCount(antes);
  });

  for (const largura of [320, 375, 768, 1440]) {
    test(`${largura}px: as duas telas não rolam na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: largura, height: 900 });
      await entrarComoAdmin(page);

      await page.goto('/admin/configuracoes');
      await expect(page.getByRole('button', { name: 'Salvar configurações' })).toBeVisible();
      expect(await rolaNaHorizontal(page), 'configurações').toBe(false);

      await page.goto('/admin/templates');
      await expect(page.getByRole('button', { name: 'Salvar modelo' })).toBeVisible();
      expect(await rolaNaHorizontal(page), 'modelos').toBe(false);
    });
  }
});
