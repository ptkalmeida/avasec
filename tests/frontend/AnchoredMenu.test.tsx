import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';
import { AnchoredMenu } from '../../src/components/shared/AnchoredMenu';

/**
 * O defeito que originou este componente: o menu de ações da tabela de alunos
 * era `position: absolute` dentro de um `overflow-x-auto`. Como o CSS transforma
 * o eixo `visible` em `auto` quando o outro eixo recorta, o contêiner cortava o
 * menu na vertical e quem clicava nos três pontos não conseguia ler as opções.
 */

/** Simula um botão em determinada posição da janela. */
function ancoraEm(rect: Partial<DOMRect>): HTMLElement {
  const botao = document.createElement('button');
  document.body.appendChild(botao);
  botao.getBoundingClientRect = () =>
    ({ top: 0, left: 0, right: 0, bottom: 0, width: 0, height: 0, x: 0, y: 0, ...rect }) as DOMRect;
  return botao;
}

/** jsdom devolve offsetHeight 0; o componente decide cima/baixo por ela. */
function comAltura(altura: number): void {
  Object.defineProperty(HTMLElement.prototype, 'offsetHeight', {
    configurable: true,
    get() {
      return this.getAttribute('data-painel') === 'sim' ? altura : 0;
    },
  });
}

describe('AnchoredMenu', () => {
  beforeEach(() => {
    window.innerHeight = 800;
    window.innerWidth = 1440;
  });

  it('renderiza fora da árvore do contêiner que recortava o menu', () => {
    const ancora = ancoraEm({ top: 300, bottom: 320, right: 900 });

    render(
      <div data-testid="tabela" style={{ overflowX: 'auto' }}>
        <AnchoredMenu anchor={ancora} onClose={() => {}}>
          <button>Perfil &amp; Parâmetros</button>
        </AnchoredMenu>
      </div>
    );

    const item = screen.getByRole('button', { name: /perfil & parâmetros/i });
    // O que garante a visibilidade: o painel não descende do contêiner que recorta.
    expect(screen.getByTestId('tabela').contains(item)).toBe(false);
    expect(document.body.contains(item)).toBe(true);
  });

  it('posiciona o painel logo abaixo do botão quando há espaço', () => {
    const ancora = ancoraEm({ top: 100, bottom: 120, right: 900 });

    const { container } = render(
      <AnchoredMenu anchor={ancora} onClose={() => {}}>
        <button>Excluir Aluno</button>
      </AnchoredMenu>
    );
    void container;

    const painel = screen.getByRole('button', { name: /excluir aluno/i }).parentElement!;
    expect(painel.style.position).toBe('');
    expect(painel.className).toContain('fixed');
    expect(painel.style.top).toBe('124px');
    // Alinhado à direita do botão: 900 - 192 de largura.
    expect(painel.style.left).toBe('708px');
  });

  it('abre para cima quando o botão está no rodapé da janela', () => {
    comAltura(300);
    const ancora = ancoraEm({ top: 700, bottom: 740, right: 900 });

    const { rerender } = render(
      <AnchoredMenu anchor={ancora} onClose={() => {}}>
        <button data-painel="nao">Excluir Aluno</button>
      </AnchoredMenu>
    );
    // O painel é o pai do item; marca-o para o stub de offsetHeight.
    const painel = screen.getByRole('button', { name: /excluir aluno/i }).parentElement!;
    painel.setAttribute('data-painel', 'sim');
    fireEvent.scroll(window);
    rerender(
      <AnchoredMenu anchor={ancora} onClose={() => {}}>
        <button data-painel="nao">Excluir Aluno</button>
      </AnchoredMenu>
    );

    // 740 + 4 + 300 = 1044 passaria de 800: precisa subir para 700 - 4 - 300.
    expect(painel.style.top).toBe('396px');
    comAltura(0);
  });

  it('fecha ao clicar fora e ao pressionar Escape', async () => {
    const onClose = vi.fn();
    const ancora = ancoraEm({ top: 100, bottom: 120, right: 900 });

    render(
      <AnchoredMenu anchor={ancora} onClose={onClose}>
        <button>Redefinir Senha</button>
      </AnchoredMenu>
    );

    await userEvent.keyboard('{Escape}');
    expect(onClose).toHaveBeenCalledTimes(1);

    // A camada de clique-fora é irmã do painel dentro do portal.
    const painel = screen.getByRole('button', { name: /redefinir senha/i }).parentElement!;
    fireEvent.click(painel.previousElementSibling!);
    expect(onClose).toHaveBeenCalledTimes(2);
  });

  it('não renderiza nada sem âncora, para não piscar num canto da tela', () => {
    render(
      <AnchoredMenu anchor={null} onClose={() => {}}>
        <button>Enviar Notificação</button>
      </AnchoredMenu>
    );

    expect(screen.queryByRole('button', { name: /enviar notificação/i })).toBeNull();
  });
});
