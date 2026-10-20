import { test, expect } from '@playwright/test';
import { entrarComoInstrutor } from './sessao';

/*
 * A fronteira entre as duas tecnologias, dentro de uma área em travessia.
 *
 * O React navega DO LADO DO CLIENTE: um `navigate('/inst')` dentro do SPA não
 * pede nada ao servidor, e o React Router desenha ali a sua própria tela —
 * a versão antiga, no mesmo endereço em que, recarregando, se veria a nova.
 *
 * O que este arquivo exige: quando o destino de uma navegação do React é uma
 * tela que o servidor atende, a página é pedida ao servidor.
 *
 * O exemplo já foi a Biblioteca do aluno, depois Eventos, e agora é o painel
 * do instrutor — a cada fatia migrada o caso anterior deixa de existir, que é
 * o sinal de que a travessia está andando. Quando o React sair (M7), este
 * arquivo sai com ele.
 */
test.describe('Fronteira React → Blade', () => {
  test('sair de uma seção em React para a escolha de curso carrega a tela do servidor', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);

    // O painel de um curso ainda é React: o ponto de partida tem o casco do SPA.
    await page.getByRole('link', { name: /Aulas/ }).first().click();
    await expect(page).toHaveURL(/\/inst\/curso\/[^/]+$/);
    await expect(page.locator('#root')).toHaveCount(1);

    // A trilha do painel React navega para `/inst` do lado do cliente.
    await page.getByRole('button', { name: 'Painel do Instrutor' }).click();

    await expect(page).toHaveURL(/\/inst$/);
    // A tela que aparece é a do servidor: sem casco do React.
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: /^Meus cursos$/ })).toBeVisible();
  });
});
