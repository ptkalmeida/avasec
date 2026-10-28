import { test, expect } from '@playwright/test';
import { rolaNaHorizontal } from './sessao';

/*
 * `/cursos` é a primeira tela da fase M2 e a primeira do redesenho das cinco
 * telas (plano 18). A conferência de conteúdo e das ausências fica no PHPUnit
 * (`CatalogoPublicoBladeTest`); aqui o assunto é o que só o navegador responde:
 * o cabeçalho que passou a existir, a busca que depende de JavaScript e o
 * comportamento em tela estreita.
 */
test.describe('Cursos em destaque — tela em Blade', () => {
  test('responde no mesmo endereço, sem passar pelo React', async ({ page }) => {
    const resposta = await page.goto('/cursos');

    expect(resposta?.status()).toBe(200);
    await expect(page.getByRole('heading', { level: 1, name: 'Cursos em destaque' })).toBeVisible();
    await expect(page.locator('#root')).toHaveCount(0);
  });

  test('mostra os cursos ativos da plataforma (até quatro) e o caminho para ver os demais', async ({ page }) => {
    await page.goto('/cursos');

    // Pela lista NOMEADA, e não por `listitem` solto: o rodapé é feito de
    // listas também, e o seletor largo contava os links dele junto.
    const vitrine = page.getByRole('list', { name: 'Cursos em destaque' });
    const quantos = await vitrine.getByRole('listitem').count();
    expect(quantos).toBeGreaterThan(0);
    expect(quantos).toBeLessThanOrEqual(4);
    await expect(page.getByRole('link', { name: /Veja mais cursos/ })).toBeVisible();
  });

  test('o cabeçalho acende "Cursos" sem depender só de cor', async ({ page }) => {
    await page.goto('/cursos');

    // `aria-current` é o que o leitor de tela anuncia; a cor é o acompanhamento,
    // não o sinal. A skill proíbe marcar o item ativo só pela cor da fonte.
    const ativo = page.locator('nav[aria-label="Navegação principal"] [aria-current="page"]');
    await expect(ativo).toHaveText('Cursos');
  });

  test('o menu "A Escola" abre no clique e fecha no Escape', async ({ page }) => {
    await page.goto('/cursos');

    const gatilho = page.getByRole('button', { name: 'A Escola' });
    await expect(gatilho).toHaveAttribute('aria-expanded', 'false');

    await gatilho.click();
    await expect(gatilho).toHaveAttribute('aria-expanded', 'true');
    await expect(page.getByRole('link', { name: /O que é o AVA/ }).first()).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(gatilho).toHaveAttribute('aria-expanded', 'false');
  });

  test('a busca do cabeçalho responde e devolve curso da vitrine', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/cursos');

    // O curso vem da própria tela, e não de um nome fixo: os cursos são os da plataforma e
    // podem ser retirados do ar a qualquer momento (o banco de dev é compartilhado).
    const vitrine = page.getByRole('list', { name: 'Cursos em destaque' });
    const titulos = await vitrine.getByRole('heading', { level: 2 }).allTextContents();
    test.skip(titulos.length === 0, 'Não há curso ativo na plataforma para a busca encontrar.');
    const titulo = titulos[0].trim();
    const termo = titulo.slice(0, 12);

    // Livewire: a consulta sai 300ms depois da última tecla.
    await page.getByLabel('Buscar cursos e notícias').fill(termo);

    await expect(page.getByRole('listbox')).toBeVisible();
    await expect(page.getByRole('listbox').getByText(titulo, { exact: true })).toBeVisible();
  });

  test('o atalho de teclado leva direto ao conteúdo', async ({ page }) => {
    await page.goto('/cursos');

    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Pular para o conteúdo' })).toBeFocused();
  });

  for (const w of [320, 375, 768, 1440]) {
    test(`${w}px: não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await page.goto('/cursos');

      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});
