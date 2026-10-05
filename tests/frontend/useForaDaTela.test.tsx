import React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, act, cleanup } from '@testing-library/react';
import { useForaDaTela } from '../../src/hooks/useForaDaTela';

/*
 * O cabeçalho fixo da aula repetia o título grande que estava logo abaixo — a
 * aula abria com dois títulos. Agora ele só mostra o título quando o título
 * grande sai da tela.
 */

type Aviso = (entradas: Partial<IntersectionObserverEntry>[]) => void;

function Cabecalho({ chave }: { chave?: string }) {
  const [ref, fora] = useForaDaTela<HTMLDivElement>(chave, 72);
  return (
    <>
      <header>{fora ? <p>título no cabeçalho</p> : null}</header>
      <div ref={ref}>título grande</div>
    </>
  );
}

const original = globalThis.IntersectionObserver;

afterEach(() => {
  cleanup();
  globalThis.IntersectionObserver = original;
});

/** Instala um IntersectionObserver falso e devolve como disparar o aviso. */
function observadorFalso() {
  let avisar: Aviso = () => {};
  const opcoes: IntersectionObserverInit[] = [];
  const desconectar = vi.fn();
  globalThis.IntersectionObserver = class {
    constructor(cb: Aviso, op: IntersectionObserverInit) {
      avisar = cb;
      opcoes.push(op);
    }
    observe() {}
    disconnect() { desconectar(); }
  } as unknown as typeof IntersectionObserver;

  return { avisar: (e: Partial<IntersectionObserverEntry>[]) => avisar(e), opcoes, desconectar };
}

describe('useForaDaTela', () => {
  it('com o título grande na tela, o cabeçalho não repete o título', () => {
    observadorFalso();
    render(<Cabecalho chave="aula-1" />);

    expect(screen.queryByText('título no cabeçalho')).toBeNull();
  });

  it('quando o título grande sai da tela, o cabeçalho mostra o título; ao voltar, esconde', () => {
    const { avisar } = observadorFalso();
    render(<Cabecalho chave="aula-1" />);

    act(() => avisar([{ isIntersecting: false }]));
    expect(screen.getByText('título no cabeçalho')).toBeInTheDocument();

    act(() => avisar([{ isIntersecting: true }]));
    expect(screen.queryByText('título no cabeçalho')).toBeNull();
  });

  it('desconta a altura do cabeçalho fixo, que cobre o alto da tela', () => {
    const { opcoes } = observadorFalso();
    render(<Cabecalho chave="aula-1" />);

    expect(opcoes[0].rootMargin).toBe('-72px 0px 0px 0px');
  });

  it('trocar de aula recomeça com o título grande visível', () => {
    const { avisar, desconectar } = observadorFalso();
    const { rerender } = render(<Cabecalho chave="aula-1" />);
    act(() => avisar([{ isIntersecting: false }]));

    rerender(<Cabecalho chave="aula-2" />);

    expect(desconectar).toHaveBeenCalled();
    expect(screen.queryByText('título no cabeçalho')).toBeNull();
  });

  it('sem IntersectionObserver, mostra um título só (o grande)', () => {
    // Simula navegador sem a API.
    globalThis.IntersectionObserver = undefined as unknown as typeof IntersectionObserver;
    render(<Cabecalho chave="aula-1" />);

    expect(screen.queryByText('título no cabeçalho')).toBeNull();
  });
});
