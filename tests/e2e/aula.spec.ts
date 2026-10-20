import { test, expect } from '@playwright/test';
import { abrirPrimeiraAula, entrarComoAluno, rolaNaHorizontal } from './sessao';

/*
 * Página da aula em Blade — tela 5 do redesenho (plano 18).
 *
 * SUBSTITUI a versão anterior deste arquivo, que media a sala de aula do React.
 * A troca não é cosmética: o cartão de Suporte Pedagógico saiu da aula por
 * decisão da skill (`redesenhar-ava-lms`, aprovada em 19/10), e com ele a
 * pergunta "o suporte é aba abaixo de lg e cartão a partir de lg?" deixou de
 * ter resposta — o suporte agora é item do menu lateral e leva ao canal de
 * mensagens.
 *
 * O que aquele arquivo protegia e CONTINUA valendo veio junto: a tela não rola
 * na horizontal em nenhuma largura, a tira de abas cabe em 375px, e nada na
 * página afirma presença nem prazo de resposta — as duas afirmações que não
 * tinham dado por trás e saíram em 13/10.
 *
 * O que só o navegador responde fica aqui; o conteúdo e as ausências estão no
 * PHPUnit (`PaginaDaAulaTest`).
 */

const LARGURAS = [
  { nome: 'celular estreito', w: 320, h: 900 },
  { nome: 'celular comum', w: 375, h: 900 },
  { nome: 'tablet', w: 768, h: 1100 },
  { nome: 'desktop no limite', w: 1024, h: 900 },
  { nome: 'desktop largo', w: 1440, h: 900 },
];

test.describe('Página da aula em Blade', () => {
  for (const { nome, w, h } of LARGURAS) {
    test(`${nome} (${w}px): não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: h });
      await entrarComoAluno(page);
      await abrirPrimeiraAula(page);

      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }

  test('o título da aula vem acima do vídeo, com o nome do curso por cima', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);
    await abrirPrimeiraAula(page);

    // Layout B: o título é o primeiro elemento do conteúdo, antes da mídia.
    const titulo = page.getByRole('heading', { level: 1 });
    await expect(titulo).toBeVisible();

    const caixaTitulo = await titulo.boundingBox();
    const midia = page.locator('iframe, video').first();

    if (await midia.count()) {
      const caixaMidia = await midia.boundingBox();
      expect(caixaTitulo!.y).toBeLessThan(caixaMidia!.y);
    }
  });

  test('a tira de abas cabe na largura disponível em 375px', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 900 });
    await entrarComoAluno(page);
    await abrirPrimeiraAula(page);

    const tira = page.getByRole('tablist');
    const medida = await tira.evaluate((el) => ({
      scrollW: el.scrollWidth,
      clientW: el.clientWidth,
      scrollLeft: el.scrollLeft,
    }));

    expect(medida.scrollW).toBeLessThanOrEqual(medida.clientW);
    expect(medida.scrollLeft).toBe(0);
  });

  test('trocar de aba mostra as anotações sem ida ao servidor', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);
    await abrirPrimeiraAula(page);

    const anotacoes = page.getByRole('tab', { name: /anotações privadas/i });
    await anotacoes.click();

    await expect(anotacoes).toHaveAttribute('aria-selected', 'true');
    await expect(page.getByLabel(/suas anotações desta aula/i)).toBeVisible();
    // A promessa de privacidade está escrita na tela, não subentendida.
    await expect(page.getByText(/apenas neste navegador/i)).toBeVisible();
  });

  test('as anotações sobrevivem ao recarregar, no mesmo navegador', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);
    await abrirPrimeiraAula(page);

    const texto = 'anotação de teste ' + Date.now();

    await page.getByRole('tab', { name: /anotações privadas/i }).click();
    await page.getByLabel(/suas anotações desta aula/i).fill(texto);
    await page.getByRole('button', { name: /salvar anotações/i }).click();
    await expect(page.getByText(/anotações salvas/i)).toBeVisible();

    await page.reload();
    await page.getByRole('tab', { name: /anotações privadas/i }).click();
    await expect(page.getByLabel(/suas anotações desta aula/i)).toHaveValue(texto);

    // Deixa o navegador como estava: a anotação é do aluno de teste, não do teste.
    await page.getByLabel(/suas anotações desta aula/i).fill('');
    await page.getByRole('button', { name: /salvar anotações/i }).click();
  });

  test('a página não afirma presença nem prazo de resposta', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);
    await abrirPrimeiraAula(page);

    /*
     * O selo "ONLINE" vinha do `localStorage` do navegador do próprio aluno —
     * verde para todo mundo, sempre — e o prazo de resposta estava escrito no
     * código. Quem lê os dois acredita que há alguém do outro lado agora, e o
     * sistema não sabe disso. Saíram em 13/10 e não voltam.
     */
    await expect(page.getByText(/\bONLINE\b|\bOFFLINE\b/)).toHaveCount(0);
    await expect(page.getByText(/tempo de resposta/i)).toHaveCount(0);
  });

  test('o suporte continua alcançável, agora pelo menu lateral', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);
    await abrirPrimeiraAula(page);

    const suporte = page.getByRole('link', { name: /suporte pedagógico/i });
    await expect(suporte).toBeVisible();
    await expect(suporte).toHaveAttribute('href', '/aluno/mensagens');
  });

  test('o progresso fala por número, e não só por cor', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);
    await abrirPrimeiraAula(page);

    // D §2.13: progresso que só existe como cor não é legível por quem não a distingue.
    await expect(page.getByText(/\d+ de \d+ aulas concluídas/).first()).toBeVisible();
  });

  test('a lista de aulas fica no menu, e não se repete no conteúdo', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoAluno(page);
    await abrirPrimeiraAula(page);

    await expect(page.getByText('Aulas do curso', { exact: true })).toHaveCount(1);
  });
});
