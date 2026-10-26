import { test, expect } from '@playwright/test';
import { rolaNaHorizontal } from './sessao';

/*
 * `/certificados` em Blade — critérios de emissão e autenticador público.
 *
 * É a tela que uma pessoa de FORA da escola usa para decidir se um documento
 * vale. As três respostas (válido, revogado, inexistente) são conferidas no
 * PHPUnit (`CertificadosPublicosBladeTest`), que pode criar e revogar
 * certificado dentro de uma transação. Aqui fica o que depende do navegador: o
 * formulário produzindo um endereço, o teclado alcançando tudo, e a tela
 * cabendo em telefone — uma secretaria confere pelo celular.
 */
test.describe('Certificados — tela em Blade', () => {
  test('responde no mesmo endereço, sem passar pelo React', async ({ page }) => {
    const resposta = await page.goto('/certificados');

    expect(resposta?.status()).toBe(200);
    await expect(page.getByRole('heading', { level: 1, name: /Certificados/i })).toBeVisible();
    await expect(page.locator('#root')).toHaveCount(0);
  });

  test('sem consulta, a tela não afirma nada sobre documento nenhum', async ({ page }) => {
    await page.goto('/certificados');

    await expect(page.getByText(/Certificado v[áa]lido/i)).toHaveCount(0);
    await expect(page.getByText(/C[óo]digo n[ãa]o encontrado/i)).toHaveCount(0);
  });

  test('a consulta vira endereço, e o resultado volta ao recarregar', async ({ page }) => {
    await page.goto('/certificados');

    await page.getByLabel(/C[óo]digo de valida[çc][ãa]o/i).fill('AVA-NAO-EXISTE-0000');
    await page.getByRole('button', { name: /Verificar/i }).click();

    // O GET põe o termo na URL: o resultado é endereçável, que é o mesmo
    // comportamento do QR impresso no certificado.
    await expect(page).toHaveURL(/verify=AVA-NAO-EXISTE-0000/);
    await expect(page.getByText(/C[óo]digo n[ãa]o encontrado/i)).toBeVisible();

    await page.reload();
    await expect(page.getByText(/C[óo]digo n[ãa]o encontrado/i)).toBeVisible();
    await expect(page.getByLabel(/C[óo]digo de valida[çc][ãa]o/i)).toHaveValue('AVA-NAO-EXISTE-0000');
  });

  test('o QR impresso continua chegando ao autenticador', async ({ page }) => {
    /*
     * O QR do PDF aponta para `/?verify=<hash>`. Quando a raiz passou a ser
     * Blade, o efeito do React que levava esse parâmetro adiante deixou de
     * existir e o QR passou a abrir a home sem responder nada. Há certificado
     * impresso em circulação: este caminho não pode quebrar de novo.
     */
    await page.goto('/?verify=AVA-NAO-EXISTE-0000');

    await expect(page).toHaveURL(/\/certificados\?verify=AVA-NAO-EXISTE-0000/);
    await expect(page.getByText(/C[óo]digo n[ãa]o encontrado/i)).toBeVisible();
  });

  test('dá para consultar só com o teclado', async ({ page }) => {
    await page.goto('/certificados');

    const campo = page.getByLabel(/C[óo]digo de valida[çc][ãa]o/i);
    await campo.focus();
    await campo.fill('AVA-NAO-EXISTE-0000');
    // Enter dentro do campo envia o formulário: é o que quem digita espera, e um
    // formulário que só responde ao clique exclui quem navega por teclado.
    await campo.press('Enter');

    await expect(page).toHaveURL(/verify=AVA-NAO-EXISTE-0000/);
  });

  for (const w of [320, 375, 768, 1440]) {
    test(`${w}px: não rola na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await page.goto('/certificados?verify=AVA-NAO-EXISTE-0000');

      expect(await rolaNaHorizontal(page)).toBe(false);
    });
  }
});
