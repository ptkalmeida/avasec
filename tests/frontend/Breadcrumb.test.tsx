import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';
import { Breadcrumb } from '../../src/components/shared/Breadcrumb';
import { trilhaDaView } from '../../src/router/portalRoutes';

describe('Breadcrumb', () => {
  it('insere "Início" sempre em primeiro, sem o chamador passar', () => {
    /*
     * Era exatamente assim que os textos divergiam entre telas: cada chamador
     * escrevia o seu primeiro degrau. O componente passa a ser o único lugar.
     */
    render(<Breadcrumb items={[{ rotulo: 'Cursos' }]} onHome={() => {}} />);

    const itens = screen.getAllByRole('listitem').map((li) => li.textContent);
    expect(itens[0]).toContain('Início');
    expect(itens).toHaveLength(2);
  });

  it('o degrau ATUAL não é clicável', () => {
    // Um item atual clicável recarrega a própria tela e parece defeito.
    render(<Breadcrumb items={[{ rotulo: 'Notícias' }]} onHome={() => {}} />);

    expect(screen.queryByRole('button', { name: 'Notícias' })).toBeNull();
    expect(screen.getByText('Notícias')).toHaveAttribute('aria-current', 'page');
  });

  it('os degraus acima são botões que levam ao nível deles', async () => {
    const irParaInicio = vi.fn();
    const irParaCursos = vi.fn();

    render(
      <Breadcrumb
        items={[{ rotulo: 'Cursos', onClick: irParaCursos }, { rotulo: 'Curso de fotografia' }]}
        onHome={irParaInicio}
      />
    );

    await userEvent.click(screen.getByRole('button', { name: 'Início' }));
    expect(irParaInicio).toHaveBeenCalledTimes(1);

    await userEvent.click(screen.getByRole('button', { name: 'Cursos' }));
    expect(irParaCursos).toHaveBeenCalledTimes(1);
  });

  it('lista vazia NÃO desenha a barra', () => {
    /*
     * A página inicial não tem trilha, e uma barra com "Início" sozinho não
     * informa nada — só empurra o conteúdo 48px para baixo.
     */
    const { container } = render(<Breadcrumb items={[]} onHome={() => {}} />);

    expect(container.firstChild).toBeNull();
    expect(screen.queryByLabelText('Trilha de navegação')).toBeNull();
  });

  it('é anunciada como navegação, e o separador não é lido', () => {
    render(<Breadcrumb items={[{ rotulo: 'Cursos' }]} onHome={() => {}} />);

    expect(screen.getByLabelText('Trilha de navegação').tagName).toBe('NAV');
    // O `›` é decoração: a lista já dá a estrutura a quem usa leitor de tela.
    const separador = screen.getByText('›');
    expect(separador).toHaveAttribute('aria-hidden', 'true');
  });

  it('o primeiro degrau pode ter outro nome, para os painéis internos', () => {
    // No painel do aluno o topo não é "Início" do portal: é o painel dele.
    render(<Breadcrumb items={[{ rotulo: 'Meu curso' }]} onHome={() => {}} rotuloInicio="Painel de Estudos" />);

    expect(screen.getByRole('button', { name: 'Painel de Estudos' })).toBeTruthy();
  });
});

describe('trilha derivada da rota', () => {
  it('a página inicial não tem trilha', () => {
    expect(trilhaDaView('landing')).toEqual([]);
  });

  it('cada página institucional tem um degrau, o próprio, sem link', () => {
    /*
     * Nenhuma delas pende de outra: os agrupamentos "A Escola" e "Ajuda" são
     * rótulos de MENU, não telas — não existe `/a-escola` para clicar, e uma
     * trilha com item morto no meio é pior que uma trilha curta.
     */
    expect(trilhaDaView('cursos')).toEqual([{ rotulo: 'Cursos' }]);
    expect(trilhaDaView('noticias')).toEqual([{ rotulo: 'Notícias' }]);
    expect(trilhaDaView('orientacoes')).toEqual([{ rotulo: 'Orientações ao estudante' }]);
  });

  it('o degrau atual nunca vem com `view`', () => {
    // É o que o componente usa para decidir que ele não é clicável.
    for (const view of ['cursos', 'certificados', 'o-ava', 'duvidas'] as const) {
      const trilha = trilhaDaView(view);
      expect(trilha[trilha.length - 1].view).toBeUndefined();
    }
  });

  it('a área autenticada e o perfil ficam fora', () => {
    /*
     * Os painéis têm trilha própria, derivada da hierarquia de cada um. E o
     * perfil é alcançado de qualquer lugar: dar-lhe um pai fixo faria a trilha
     * afirmar um caminho que a pessoa não percorreu.
     */
    expect(trilhaDaView('active_app')).toEqual([]);
    expect(trilhaDaView('perfil')).toEqual([]);
  });

  it('os rótulos da trilha batem com os do menu', () => {
    // Duas palavras diferentes para a mesma tela fazem a pessoa duvidar se é a
    // mesma tela.
    expect(trilhaDaView('o-ava')[0].rotulo).toBe('O que é o AVA');
    expect(trilhaDaView('duvidas')[0].rotulo).toBe('Dúvidas frequentes');
  });
});
