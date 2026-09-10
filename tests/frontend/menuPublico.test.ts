import { describe, it, expect } from 'vitest';
import { MENU_PUBLICO, destinosDoMenu, entradaAtiva } from '../../src/config/menuPublico';
import { PORTAL_PATHS, PortalView } from '../../src/router/portalRoutes';

describe('estrutura do menu', () => {
  it('tem quatro entradas', () => {
    // Eram nove, de 11px, indistinguíveis entre si.
    expect(MENU_PUBLICO).toHaveLength(4);
    expect(MENU_PUBLICO.map((e) => e.rotulo)).toEqual(['Cursos', 'Certificados', 'A Escola', 'Ajuda']);
  });

  it('"Início" NÃO é entrada do menu', () => {
    // O logotipo já leva à página inicial e já anuncia isso. Duas portas para a
    // mesma tela gastam uma vaga do menu sem acrescentar destino.
    expect(destinosDoMenu()).not.toContain('landing');
  });

  it('todo destino do menu é uma rota que existe', () => {
    /*
     * A guarda contra o defeito mais fácil de cometer aqui: escrever um destino
     * que o roteador não conhece. O menu levaria a lugar nenhum, e só se
     * descobre clicando.
     */
    for (const view of destinosDoMenu()) {
      expect(PORTAL_PATHS[view], `destino ${view} não tem rota`).toBeTypeOf('string');
    }
  });

  it('nenhum destino aparece em duas entradas', () => {
    const vistos = destinosDoMenu();
    expect(new Set(vistos).size).toBe(vistos.length);
  });

  it('as nove páginas do portal continuam alcançáveis pelo menu', () => {
    /*
     * Reduzir de nove entradas para quatro não pode esconder página. As sete
     * institucionais têm de continuar a um ou dois cliques; `landing` fica no
     * logotipo e `perfil`/`active_app` não são do portal público.
     */
    const institucionais: PortalView[] = [
      'o-ava', 'o-projeto', 'cursos', 'certificados', 'calendario', 'noticias', 'duvidas', 'orientacoes',
    ];

    for (const view of institucionais) {
      expect(destinosDoMenu(), `${view} ficou fora do menu`).toContain(view);
    }
  });

  it('cada item de grupo explica o destino', () => {
    // A descrição é o que substitui a leitura do menu inteiro.
    for (const entrada of MENU_PUBLICO) {
      if (entrada.tipo !== 'grupo') continue;
      for (const item of entrada.itens) {
        expect(item.descricao.trim().length, `${item.rotulo} sem descrição`).toBeGreaterThan(10);
      }
    }
  });
});

describe('indicação da página atual', () => {
  it('link direto acende a si mesmo', () => {
    expect(entradaAtiva('cursos')).toBe('Cursos');
    expect(entradaAtiva('certificados')).toBe('Certificados');
  });

  it('página de dentro de um grupo acende o GRUPO, não a página', () => {
    /*
     * A regra que erra em silêncio: em "Notícias" quem acende é "A Escola",
     * porque "Notícias" não é entrada do menu. Se isto quebrar, o ponto
     * simplesmente não aparece e ninguém percebe — daí o teste.
     */
    expect(entradaAtiva('noticias')).toBe('A Escola');
    expect(entradaAtiva('o-ava')).toBe('A Escola');
    expect(entradaAtiva('o-projeto')).toBe('A Escola');
    expect(entradaAtiva('duvidas')).toBe('Ajuda');
    expect(entradaAtiva('orientacoes')).toBe('Ajuda');
    expect(entradaAtiva('calendario')).toBe('Ajuda');
  });

  it('não acende nada onde não há entrada correspondente', () => {
    // Acender uma entrada arbitrária afirmaria uma localização falsa.
    expect(entradaAtiva('landing')).toBeNull();
    expect(entradaAtiva('perfil')).toBeNull();
    expect(entradaAtiva('active_app')).toBeNull();
  });

  it('toda página institucional acende ALGUMA entrada', () => {
    const institucionais: PortalView[] = [
      'o-ava', 'o-projeto', 'cursos', 'certificados', 'calendario', 'noticias', 'duvidas', 'orientacoes',
    ];

    for (const view of institucionais) {
      expect(entradaAtiva(view), `${view} não acende nada`).not.toBeNull();
    }
  });
});
