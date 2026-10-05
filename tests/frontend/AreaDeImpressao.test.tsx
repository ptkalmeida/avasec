import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import {
  AreaDeImpressao,
  DocumentoImprimivel,
  CLASSE_AREA_IMPRESSAO,
} from '../../src/components/shared/AreaDeImpressao';

/**
 * O jsdom não pagina impressão: estes testes travam a ESTRUTURA que faz a
 * paginação funcionar. A prova final é a pré-visualização de impressão do
 * navegador (ver .ai/planejamento/09).
 */
const cssDaImpressao = () => document.querySelector(`.${CLASSE_AREA_IMPRESSAO} style`)?.textContent ?? '';

describe('AreaDeImpressao', () => {
  it('põe o documento como filho direto do body, fora da árvore do app', () => {
    const { container } = render(<AreaDeImpressao><p>Histórico</p></AreaDeImpressao>);
    const area = document.querySelector(`.${CLASSE_AREA_IMPRESSAO}`);

    expect(area?.parentElement).toBe(document.body);
    expect(container.contains(area)).toBe(false);
    expect(area?.textContent).toContain('Histórico');
  });

  // O defeito relatado: visibility esconde sem tirar do layout, e a tela de fundo
  // ditava o número de páginas.
  it('tira o resto da página do layout com display none, não com visibility', () => {
    render(<AreaDeImpressao><p>x</p></AreaDeImpressao>);
    const css = cssDaImpressao();

    expect(css).toMatch(/body > \*:not\(\.area-impressao\)\s*\{\s*display:\s*none/);
    expect(css).not.toMatch(/visibility:\s*hidden/);
  });

  // O outro defeito: elemento fixed é repetido pelo navegador em cada página.
  it('nada na impressão é position fixed', () => {
    render(<AreaDeImpressao><p>x</p></AreaDeImpressao>);

    expect(cssDaImpressao()).not.toMatch(/position:\s*fixed/);
    expect(cssDaImpressao()).toMatch(/position:\s*static/);
  });

  it('o documento impresso nunca é recortado por altura máxima ou rolagem', () => {
    render(<AreaDeImpressao><p>x</p></AreaDeImpressao>);
    const css = cssDaImpressao();

    expect(css).toMatch(/max-height:\s*none/);
    expect(css).toMatch(/overflow:\s*visible/);
  });

  it('repete o cabeçalho da tabela e evita partir linha e título entre páginas', () => {
    render(<AreaDeImpressao><p>x</p></AreaDeImpressao>);
    const css = cssDaImpressao();

    expect(css).toMatch(/thead\s*\{\s*display:\s*table-header-group/);
    expect(css).toMatch(/break-inside:\s*avoid/);
    expect(css).toMatch(/break-after:\s*avoid/);
  });

  it('fica invisível na tela, só aparece na impressão', () => {
    render(<AreaDeImpressao><p>x</p></AreaDeImpressao>);

    expect(cssDaImpressao()).toMatch(/^\s*\.area-impressao\s*\{\s*display:\s*none;\s*\}/);
  });

  it('fechar o modal remove a cópia de impressão do body', () => {
    const { unmount } = render(<AreaDeImpressao><p>x</p></AreaDeImpressao>);

    unmount();

    expect(document.querySelector(`.${CLASSE_AREA_IMPRESSAO}`)).toBeNull();
  });
});

describe('DocumentoImprimivel', () => {
  it('mostra o documento na tela e cria a cópia de impressão a partir do mesmo JSX', () => {
    const { container } = render(<DocumentoImprimivel><h1>Histórico Escolar</h1></DocumentoImprimivel>);

    // Uma cópia no lugar de sempre (dentro do modal)...
    expect(container.querySelector('h1')?.textContent).toBe('Histórico Escolar');
    // ...e outra no body, para a impressão.
    expect(document.querySelector(`.${CLASSE_AREA_IMPRESSAO} h1`)?.textContent).toBe('Histórico Escolar');
    expect(screen.getAllByText('Histórico Escolar')).toHaveLength(2);
  });
});
