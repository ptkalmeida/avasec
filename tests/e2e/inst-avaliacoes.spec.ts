import { test, expect, type Page } from '@playwright/test';
import { entrarComoInstrutor, rolaNaHorizontal } from './sessao';

/*
 * Avaliações do instrutor em Blade — a lista e o formulário.
 *
 * NENHUM teste aqui grava uma prova: o curso de demonstração é compartilhado, e
 * a prova que um teste criasse seria exigida para o certificado de todos os
 * alunos dele. A gravação, o escopo e a retirada são conferidos no PHPUnit, em
 * transação. Aqui: o JavaScript do formulário (adicionar e remover questões e
 * alternativas), e o caminho de ERRO do servidor, que não grava nada.
 */
const CURSO = '/inst/curso/portfolio-de-comprovacao-da-atuacao-cultural';

async function abrirFormularioNovo(page: Page): Promise<void> {
  await entrarComoInstrutor(page);
  await page.goto(`${CURSO}/avaliacoes/nova`);
  await expect(page.getByRole('heading', { level: 1, name: 'Nova avaliação' })).toBeVisible();
}

test.describe('Avaliações do instrutor — telas em Blade', () => {
  test('a lista é servida pelo servidor, e o menu leva até ela', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);
    await page.goto(`${CURSO}`);

    await page.getByRole('navigation', { name: 'Navegação da área do instrutor' })
      .getByRole('link', { name: /^Avaliações/ }).click();

    await expect(page).toHaveURL(/\/avaliacoes$/);
    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: 'Avaliações' })).toBeVisible();
  });

  test('o formulário abre com uma questão e quatro alternativas', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await abrirFormularioNovo(page);

    await expect(page.locator('fieldset')).toHaveCount(1);
    await expect(page.getByLabel(/^Texto da alternativa/)).toHaveCount(4);
    // Com uma questão só, não há como remover: a prova precisa de uma.
    await expect(page.getByRole('button', { name: /^Remover/ })).toHaveCount(0);
  });

  test('acrescentar e remover questões', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await abrirFormularioNovo(page);

    await page.getByRole('button', { name: '+ Acrescentar questão' }).click();
    await expect(page.locator('fieldset')).toHaveCount(2);
    await expect(page.getByRole('button', { name: /^Remover/ })).toHaveCount(2);

    await page.getByRole('button', { name: /^Remover/ }).first().click();
    await expect(page.locator('fieldset')).toHaveCount(1);
  });

  test('alternativas: o limite é de 2 a 6', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await abrirFormularioNovo(page);
    const acrescentar = page.getByRole('button', { name: '+ Acrescentar alternativa' });

    await acrescentar.click();
    await acrescentar.click();
    await expect(page.getByLabel(/^Texto da alternativa/)).toHaveCount(6);
    await expect(acrescentar).toBeHidden();

    for (let i = 0; i < 4; i += 1) {
      await page.getByRole('button', { name: /^Excluir a alternativa/ }).first().click();
    }
    await expect(page.getByLabel(/^Texto da alternativa/)).toHaveCount(2);
    // Com duas, nenhuma pode ser excluída.
    await expect(page.getByRole('button', { name: /^Excluir a alternativa/ })).toHaveCount(0);
  });

  /*
   * O gabarito acompanha a alternativa. Excluir uma ANTES da correta a
   * deslocaria: era a regra do editor React, e vale aqui. Sem ela, o professor
   * apagaria a alternativa A e a "correta" passaria a ser outra, sem aviso.
   */
  test('excluir uma alternativa antes da correta mantém a correta', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await abrirFormularioNovo(page);

    const campos = page.getByLabel(/^Texto da alternativa/);
    await campos.nth(0).fill('Primeira');
    await campos.nth(1).fill('Segunda');
    await campos.nth(2).fill('Terceira');
    await page.getByLabel('Alternativa C é a correta').check();

    await page.getByRole('button', { name: 'Excluir a alternativa A' }).click();

    // "Terceira" agora é a B, e continua sendo a marcada.
    await expect(campos.nth(1)).toHaveValue('Terceira');
    await expect(page.getByLabel('Alternativa B é a correta')).toBeChecked();
  });

  test('o servidor recusa gabarito impossível, devolve o que foi digitado e não grava', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await abrirFormularioNovo(page);

    await page.getByLabel('Título da avaliação').fill('Prova Que Nao Deve Ser Gravada');
    await page.getByLabel('Enunciado').fill('Pergunta com duas alternativas iguais?');
    const campos = page.getByLabel(/^Texto da alternativa/);
    await campos.nth(0).fill('Igual');
    await campos.nth(1).fill('igual');
    await campos.nth(2).fill('Outra');

    await page.getByRole('button', { name: 'Criar avaliação' }).click();

    await expect(page.getByRole('alert')).toContainText('Questão 1: Há alternativas repetidas');
    // O que a pessoa digitou volta, para ela corrigir em vez de refazer.
    await expect(page.getByLabel('Título da avaliação')).toHaveValue('Prova Que Nao Deve Ser Gravada');
    await expect(page.getByLabel('Enunciado')).toHaveValue('Pergunta com duas alternativas iguais?');

    // E nada foi gravado: a lista continua só com as duas provas de antes.
    await page.goto(`${CURSO}/avaliacoes`);
    await expect(page.getByText('Prova Que Nao Deve Ser Gravada')).toHaveCount(0);
  });

  test('retirar pede confirmação na página, e Manter desiste', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);
    await page.goto(`${CURSO}/avaliacoes`);

    await page.getByRole('button', { name: /^Retirar/ }).first().click();
    await expect(page.getByText('Nada é apagado.').first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Retirar a avaliação' }).first()).toBeVisible();

    await page.getByRole('button', { name: 'Manter' }).first().click();
    await expect(page.getByRole('button', { name: 'Retirar a avaliação' }).first()).toBeHidden();
  });

  test('editar traz as questões reais da prova, com o gabarito marcado', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);
    await page.goto(`${CURSO}/avaliacoes`);

    await page.getByRole('link', { name: /^Editar/ }).first().click();

    await expect(page.getByRole('heading', { level: 1, name: 'Editar avaliação' })).toBeVisible();
    await expect(page.locator('fieldset').first()).toBeVisible();
    // Cada questão tem exatamente uma alternativa marcada.
    const marcadas = page.locator('fieldset').first().locator('input[type=radio]:checked');
    await expect(marcadas).toHaveCount(1);
  });

  test('o endereço antigo de exercícios é do servidor e explica', async ({ page }) => {
    await entrarComoInstrutor(page);
    await page.goto(`${CURSO}/avaliacoes/exercicios`);

    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByText('Esta seção não está disponível.')).toBeVisible();
  });

  for (const w of [320, 375, 768, 1440]) {
    test(`${w}px: lista e formulário não rolam na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await entrarComoInstrutor(page);

      await page.goto(`${CURSO}/avaliacoes`);
      expect(await rolaNaHorizontal(page)).toBe(false);

      await page.goto(`${CURSO}/avaliacoes/nova`);
      await expect(page.getByRole('heading', { level: 1, name: 'Nova avaliação' })).toBeVisible();
      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});
