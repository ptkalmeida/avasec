import { test, expect } from '@playwright/test';
import { entrarComoAluno } from './sessao';

/*
 * A fronteira entre as duas tecnologias, dentro da área do aluno.
 *
 * Durante a migração, parte de `/aluno` é Blade e parte ainda é React. O React
 * navega DO LADO DO CLIENTE: um `navigate('/aluno')` dentro do SPA não pede
 * nada ao servidor — o React Router desenha a versão antiga do painel ali
 * mesmo. O aluno saía de uma seção em React pelo menu e via o painel de antes do
 * redesenho, no mesmo endereço em que, recarregando, veria o novo.
 *
 * O que este arquivo exige: quando o destino de uma navegação do React é uma
 * tela que o servidor atende, a página é pedida ao servidor.
 */
test.describe('Fronteira React → Blade na área do aluno', () => {
  test('sair de uma seção em React para o painel carrega o painel em Blade', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);

    // O painel do instrutor ainda é React: é o que sobrou de ponto de partida
    // depois de a área do aluno inteira passar para Blade. (Era a Biblioteca,
    // e depois Eventos.)
    await page.goto('/inst');
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
