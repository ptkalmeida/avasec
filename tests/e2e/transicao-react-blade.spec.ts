import { test, expect } from '@playwright/test';
import { entrarComoAluno } from './sessao';

/*
 * A fronteira entre as duas tecnologias, dentro da área do aluno.
 *
 * Durante a migração, parte de `/aluno` é Blade e parte ainda é React. O React
 * navega DO LADO DO CLIENTE: um `navigate('/aluno')` dentro do SPA não pede
 * nada ao servidor — o React Router desenha a versão antiga do painel ali
 * mesmo. O aluno saía da Biblioteca pelo menu e via o painel de antes do
 * redesenho, no mesmo endereço em que, recarregando, veria o novo.
 *
 * O que este arquivo exige: quando o destino de uma navegação do React é uma
 * tela que o servidor atende, a página é pedida ao servidor.
 */
test.describe('Fronteira React → Blade na área do aluno', () => {
  test('sair de uma seção em React para o painel carrega o painel em Blade', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);

    // Biblioteca ainda é React: o ponto de partida tem o casco do SPA.
    await page.goto('/aluno/biblioteca');
    await expect(page.locator('#root')).toHaveCount(1);

    // O item "Painel" do menu do React navega do lado do cliente.
    await page.getByRole('button', { name: /^Painel$/ }).first().click();

    await expect(page).toHaveURL(/\/aluno$/);
    // O painel que aparece é o do servidor: sem casco do React, e com a
    // saudação que só a versão Blade tem.
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: /^Olá, / })).toBeVisible();
  });
});
