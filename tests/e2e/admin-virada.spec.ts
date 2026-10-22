import { test, expect } from '@playwright/test';
import { entrarComoAdmin } from './sessao';

/*
 * A virada do painel administrativo (M5, fatia 8): `/admin` inteiro é do servidor.
 *
 * Este arquivo era `transicao-react-blade.spec.ts`: o último caso de travessia
 * que sobrava era sair do painel do administrador, ainda em React, para o portal.
 * Com o painel inteiro em Blade não há mais fronteira React → Blade em área
 * nenhuma, e o que importa passou a ser o contrário: NENHUM endereço de `/admin`
 * pode servir o casco do React — ali moram o painel antigo e a tela de exercícios
 * que aparecia por baixo do aviso de "seção desligada".
 */
test.describe('Virada do painel do administrador — /admin é do servidor', () => {
  test('nenhum endereço de /admin serve o React, conhecido ou não', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);

    for (const endereco of [
      '/admin',
      '/admin/relatorio/alunos',
      '/admin/equipe',
      '/admin/alunos',
      '/admin/cursos',
      '/admin/dados-gerenciais',
      '/admin/templates',
      '/admin/paginas-do-site',
      '/admin/configuracoes',
      '/admin/perfil',
      // Desligadas por flag: eram as telas que o React desenhava por baixo do aviso.
      '/admin/exercicios',
      '/admin/documentos',
      // Endereços que nunca existiram.
      '/admin/qualquer-coisa',
      '/admin/alunos/nao-existe/ficha-antiga',
    ]) {
      const resposta = await page.goto(endereco);
      expect(resposta?.status(), endereco).toBeLessThan(400);
      await expect(page.locator('#root'), endereco).toHaveCount(0);
    }
  });

  test('a seção desligada diz que não está disponível e não mostra a tela antiga', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);

    await page.goto('/admin/exercicios');

    await expect(page.getByRole('heading', { level: 1, name: 'Exercícios Práticos' })).toBeVisible();
    await expect(page.getByText('Esta seção não está disponível nesta versão da plataforma.')).toBeVisible();
    await expect(page.getByText('Lançar Nova Atividade')).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Excluir' })).toHaveCount(0);

    await page.getByRole('link', { name: 'Voltar ao Dashboard' }).click();
    await expect(page).toHaveURL(/\/admin$/);
  });

  test('o menu não oferece as seções desligadas', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);
    await page.goto('/admin');

    const menu = page.getByRole('navigation', { name: 'Navegação do painel administrativo' });
    await expect(menu.getByRole('link')).toHaveCount(8);
    await expect(menu.getByRole('link', { name: /Exercícios Práticos/ })).toHaveCount(0);
    await expect(menu.getByRole('link', { name: /Solicitações Acadêmicas/ })).toHaveCount(0);
  });

  test('"Ir ao portal" leva à raiz, que também é do servidor', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAdmin(page);
    await page.goto('/admin');

    await page.getByRole('link', { name: /Ir ao portal/ }).click();

    await expect(page).toHaveURL(/\/$/);
    await expect(page.locator('#root')).toHaveCount(0);
  });
});
