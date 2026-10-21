import { test, expect } from '@playwright/test';
import { entrarComoInstrutor, rolaNaHorizontal } from './sessao';

/*
 * `/inst/curso/{ref}/aulas/{id}` e `/aulas/nova` — a página de uma aula, em Blade.
 *
 * Só leitura: o curso e as aulas de demonstração são compartilhados, então nada
 * aqui grava. As escritas (identificação, vídeo, aula nova) são conferidas no
 * PHPUnit, dentro de uma transação que desfaz tudo.
 */
const CURSO = '/inst/curso/portfolio-de-comprovacao-da-atuacao-cultural';
// "1.3 - Por que ter um portfólio?": a aula de demonstração que tem vídeo.
const AULA_COM_VIDEO = `${CURSO}/aulas/lesson-course-1789497468067-1789497895765`;
// "1.1 - O que é um Portfólio de Comprovação Cultural?": sem vídeo.
const AULA_SEM_VIDEO = `${CURSO}/aulas/lesson-course-1789497468067-1789497813942`;

test.describe('Página da aula do instrutor — Blade', () => {
  test('mostra as quatro zonas, servidas pelo servidor', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await entrarComoInstrutor(page);
    await page.goto(AULA_SEM_VIDEO);

    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: /O que é um Portfólio de Comprovação Cultural/ })).toBeVisible();
    for (const zona of ['Identificação da aula', 'Vídeo principal da aula', 'Material didático', /^Material de apoio/]) {
      await expect(page.getByRole('heading', { level: 2, name: zona })).toBeVisible();
    }
    await expect(page.getByText('Aula sem vídeo', { exact: true })).toBeVisible();
  });

  test('a aula com vídeo mostra o player do jeito que o aluno vê', async ({ page }) => {
    await entrarComoInstrutor(page);
    await page.goto(AULA_COM_VIDEO);

    await expect(page.locator('iframe[src*="youtube-nocookie.com/embed/"]').first()).toBeVisible();
    await expect(page.getByText('Aula sem vídeo', { exact: true })).toHaveCount(0);
  });

  test('o lápis abre a zona pelo endereço e Fechar a fecha', async ({ page }) => {
    await entrarComoInstrutor(page);
    await page.goto(AULA_SEM_VIDEO);

    await expect(page.getByRole('button', { name: 'Salvar identificação' })).toHaveCount(0);

    await page.getByRole('link', { name: /^Editar identificação da aula$/ }).click();
    await expect(page).toHaveURL(/\?editar=identificacao/);
    await expect(page.getByLabel('Título da aula')).toHaveValue(/O que é um Portfólio/);
    await expect(page.getByRole('button', { name: 'Salvar identificação' })).toBeVisible();

    await page.getByRole('link', { name: /^Fechar identificação da aula$/ }).click();
    await expect(page.getByRole('button', { name: 'Salvar identificação' })).toHaveCount(0);
  });

  test('a zona do vídeo abre com o link atual', async ({ page }) => {
    await entrarComoInstrutor(page);
    await page.goto(`${AULA_COM_VIDEO}?editar=video`);

    await expect(page.getByLabel(/Link do vídeo no YouTube/)).toHaveValue(/youtube\.com\/watch\?v=onSAajmSONo/);
    await expect(page.getByRole('button', { name: 'Salvar vídeo' })).toBeVisible();
  });

  test('a zona de anexos abre com o formulário de vínculo', async ({ page }) => {
    await entrarComoInstrutor(page);
    await page.goto(`${AULA_COM_VIDEO}?editar=anexos`);

    await expect(page.getByRole('button', { name: 'Vincular documento' })).toBeVisible();
    await expect(page.getByLabel('Endereço do conteúdo')).toBeVisible();
    await expect(page.getByLabel('Arquivo do seu computador')).toBeVisible();
  });

  test('o material abre com os controles de cada trecho e o menu Adicionar', async ({ page }) => {
    await entrarComoInstrutor(page);
    await page.goto(`${AULA_COM_VIDEO}?editar=material`);

    await expect(page.getByText('Esta é a tela do aluno')).toBeVisible();
    await expect(page.getByRole('link', { name: /^Editar parágrafo 2$/ }).or(page.getByRole('link', { name: /^Editar .+ 1$/ })).first()).toBeVisible();
    await expect(page.getByRole('button', { name: /^Remover / }).first()).toBeVisible();

    // O menu abre pelo endereço, sem JavaScript, e oferece os tipos — menos código.
    await page.getByRole('link', { name: /^\+ Adicionar/ }).first().click();
    await expect(page).toHaveURL(/adicionar=-1/);
    await expect(page.getByText('Adicionar aqui')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Parágrafo', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Imagem', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: /código/i })).toHaveCount(0);
  });

  test('o formulário de um trecho abre pelo endereço e pede o texto', async ({ page }) => {
    await entrarComoInstrutor(page);
    await page.goto(`${AULA_COM_VIDEO}?editar=material&bloco=0`);

    await expect(page.getByRole('button', { name: 'Aplicar' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Cancelar' })).toBeVisible();
  });

  test('o formulário de imagem nova exige descrição e arquivo', async ({ page }) => {
    await entrarComoInstrutor(page);
    await page.goto(`${AULA_COM_VIDEO}?editar=material&novo=imagem&apos=0`);

    await expect(page.getByLabel('Descrição da imagem (obrigatória)')).toBeVisible();
    await expect(page.getByLabel('Arquivo da imagem')).toBeVisible();
  });

  test('o formulário da aula nova abre com a carga padrão', async ({ page }) => {
    await entrarComoInstrutor(page);
    await page.goto(`${CURSO}/aulas/nova`);

    await expect(page.locator('#root')).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: 'Nova aula' })).toBeVisible();
    await expect(page.getByLabel('Carga')).toHaveValue('15 min');
    await expect(page.getByRole('button', { name: 'Adicionar aula' })).toBeVisible();
  });

  test('aula que não existe volta para a grade', async ({ page }) => {
    await entrarComoInstrutor(page);
    await page.goto(`${CURSO}/aulas/lesson-que-nao-existe`);

    await expect(page).toHaveURL(/\/grade-curricular$/);
    await expect(page.getByText('Essa aula não existe mais neste curso.')).toBeVisible();
  });

  for (const w of [320, 375, 768, 1440]) {
    test(`${w}px: a página da aula e a aula nova não rolam na horizontal`, async ({ page }) => {
      await page.setViewportSize({ width: w, height: 900 });
      await entrarComoInstrutor(page);

      for (const url of [AULA_COM_VIDEO, `${AULA_COM_VIDEO}?editar=identificacao`, `${AULA_COM_VIDEO}?editar=video`, `${AULA_COM_VIDEO}?editar=anexos`, `${AULA_COM_VIDEO}?editar=material`, `${AULA_COM_VIDEO}?editar=material&bloco=1`, `${AULA_COM_VIDEO}?editar=material&novo=imagem&apos=0`, `${CURSO}/aulas/nova`]) {
        await page.goto(url);
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

        expect(await rolaNaHorizontal(page), url).toBe(false);
      }
    });
  }
});
