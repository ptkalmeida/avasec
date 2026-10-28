import { test, expect } from '@playwright/test';
import { entrarComoAluno, rolaNaHorizontal } from './sessao';

/*
 * Painel e navegação do curso em Blade — telas 3 e 4 do redesenho (plano 18).
 *
 * SUBSTITUI `meus-cursos.spec.ts`, e a substituição é o ponto: aquele arquivo
 * media a seção "Meus cursos" do painel React, com abas de situação (Todos / Em
 * andamento / Concluídos) e a frequência no cartão. Os três foram removidos
 * desta tela por decisão da skill `redesenhar-ava-lms`, aprovada em 19/10/2026.
 *
 * Um teste que exigisse aqueles controles passaria a travar a entrega aprovada.
 * Um teste apagado sem substituto deixaria a tela sem rede. Então o que aquele
 * arquivo protegia e continua valendo foi trazido para cá — a tela montar em
 * toda largura, a contagem de aulas aparecer, e "Continuar curso" levar ao
 * curso — e o que ele protegia e deixou de valer virou o seu contrário: a
 * verificação de que os controles removidos NÃO voltaram.
 *
 * A frequência continua sendo calculada e continua decidindo o certificado. O
 * que mudou é onde ela aparece, e isso está registrado no plano 18.
 */

const LARGURAS = [320, 375, 768, 1024, 1440];

test.describe('Painel do aluno em Blade', () => {
  for (const w of LARGURAS) {
    test(`${w}px: a tela monta e não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await entrarComoAluno(page);

      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }

  test('a saudação identifica por texto, sem foto de perfil', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);

    await expect(page.getByRole('heading', { level: 1, name: /^Olá, / })).toBeVisible();
    // A skill proíbe foto de usuário: nenhuma imagem no cabeçalho da área.
    await expect(page.locator('header img')).toHaveCount(0);
  });

  test('o curso em andamento diz quantas aulas foram concluídas', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);

    await expect(page.getByText(/\d+ de \d+ aulas concluídas/).first()).toBeVisible();
  });

  test('os controles removidos não voltaram', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);

    for (const texto of ['Presença média', 'Meu progresso', 'Início da Jornada', 'Disciplinas acadêmicas disponíveis']) {
      await expect(page.getByText(texto, { exact: false })).toHaveCount(0);
    }

    // As abas de situação saíram da PÁGINA; "Cursos concluídos" vive no menu lateral, como
    // submenu, e só aparece quando há algum concluído (ADR 026: sem submenu vazio).
    await expect(page.getByRole('tab')).toHaveCount(0);
    const concluidos = page.getByRole('button', { name: 'Cursos concluídos' });
    expect(await concluidos.count()).toBeLessThanOrEqual(1);
  });

  test('o menu lateral recolhe, expande e lembra a escolha', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);

    const recolher = page.getByRole('button', { name: /Recolher o menu|Expandir o menu/ });
    await expect(page.getByRole('link', { name: 'Início', exact: true })).toBeVisible();

    await recolher.click();
    await expect(recolher).toHaveAttribute('aria-expanded', 'false');

    // A preferência é de conforto e vive no navegador: sobrevive ao recarregar.
    await page.reload();
    await expect(page.getByRole('button', { name: /Expandir o menu/ })).toHaveAttribute('aria-expanded', 'false');
  });

  test('continuar o curso leva à tela do curso, e ela não repete a lista de aulas', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);

    await page.getByRole('link', { name: /^Continuar curso$/ }).first().click();

    await expect(page.getByRole('link', { name: /^Iniciar aula$/ })).toBeVisible();
    await expect(page.getByRole('link', { name: /^Trocar de Curso$/ })).toBeVisible();

    // "Aulas do curso" é o título da coluna da direita — e aparece uma vez só.
    await expect(page.getByText('Aulas do curso', { exact: true })).toHaveCount(1);
  });

  test('a tela do curso não traz o que a skill removeu', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);
    await page.getByRole('link', { name: /^Continuar curso$/ }).first().click();

    for (const texto of ['Instrutor responsável', 'Online', 'SUA FREQUÊNCIA', 'Frequência cumprida']) {
      await expect(page.getByText(texto, { exact: false })).toHaveCount(0);
    }
  });
});
