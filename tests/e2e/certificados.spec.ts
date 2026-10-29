import { test, expect } from '@playwright/test';

/*
 * A página pública `/certificados` e o autenticador por QR estão INATIVADOS desde
 * 29/10/2026 (ADR 027), pela feature flag `verificacaoPublicaCertificados`.
 *
 * Este arquivo SUBSTITUI o anterior, que testava a página (a consulta virando endereço,
 * o teclado, a largura). Aquilo continua coberto no PHPUnit, com a flag ligada
 * (`CertificadosPublicosBladeTest`), para o dia em que a página voltar. Aqui fica o que só
 * o navegador responde sobre o estado desligado, que é o padrão.
 */
test.describe('Certificados públicos — inativados', () => {
  test('a página responde 404 e mostra a página de erro normal', async ({ page }) => {
    const resposta = await page.goto('/certificados');

    expect(resposta?.status()).toBe(404);
    await expect(page.getByText(/Certificado v[áa]lido/i)).toHaveCount(0);
  });

  test('o QR de um certificado já impresso abre a home, e não um 404', async ({ page }) => {
    const resposta = await page.goto('/?verify=ABC123');

    expect(resposta?.status()).toBe(200);
    expect(new URL(page.url()).pathname).toBe('/');
  });

  test('o cabeçalho e o rodapé não oferecem a página, e o resto do menu segue', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');

    // `href` e não o nome: cobre também o menu de telas estreitas, que fica escondido.
    await expect(page.locator('a[href="/certificados"]')).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Cursos', exact: true }).first()).toBeVisible();
  });
});
