import { test, expect } from '@playwright/test';
import { emitirToken } from './sessao';

/*
 * A fronteira entre as duas tecnologias, dentro de uma área em travessia.
 *
 * O React navega DO LADO DO CLIENTE: um `navigate('/calendario')` dentro do SPA
 * não pede nada ao servidor, e o React Router desenha ali a sua própria tela —
 * a versão antiga, no mesmo endereço em que, recarregando, se veria a nova.
 *
 * O que este arquivo exige: quando o destino de uma navegação do React é uma
 * tela que o servidor atende, a página é pedida ao servidor.
 *
 * O exemplo já foi a Biblioteca do aluno, depois Eventos, depois o painel do
 * instrutor — a cada fatia migrada o caso anterior deixa de existir, que é o
 * sinal de que a travessia está andando. Hoje o único painel que ainda é React (nas seções que faltam)
 * é o do administrador (fase M5), e dele se sai para o portal, que é do
 * servidor. Quando o React sair (M7), este arquivo sai com ele.
 */
test.describe('Fronteira React → Blade', () => {
  test('sair do painel do administrador (React) para o portal carrega a tela do servidor', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    const base = process.env.AVASEC_URL ?? 'http://localhost:5173';
    await page.context().addCookies([
      { name: 'ava_session', value: emitirToken('admin@avasec.local'), url: base, httpOnly: true },
    ]);

    // O ponto de partida é o casco do SPA.
    await page.goto('/admin/exercicios');
    await expect(page).toHaveURL(/\/admin\/exercicios$/);
    await expect(page.locator('#root')).toHaveCount(1);

    // O menu do portal, dentro do painel React, navega do lado do cliente.
    await page.getByRole('button', { name: 'Calendário', exact: true }).click();

    await expect(page).toHaveURL(/\/calendario$/);
    // A tela que aparece é a do servidor: sem casco do React.
    await expect(page.locator('#root')).toHaveCount(0);
  });
});
