import { describe, it, expect } from 'vitest';
import {
  PORTAL_PATHS,
  PortalView,
  pathFromView,
  viewFromPath,
  normalizarCaminho,
  caminhoConhecido,
} from '../../src/router/portalRoutes';

describe('mapa de telas e endereços', () => {
  it('toda tela tem endereço, e nenhum endereço se repete', () => {
    // Endereço repetido faria duas telas diferentes responderem ao mesmo link, e
    // `viewFromPath` devolveria sempre a primeira — a outra ficaria inalcançável.
    const caminhos = Object.values(PORTAL_PATHS);
    expect(caminhos).toHaveLength(11);
    expect(new Set(caminhos).size).toBe(11);
  });

  it('ida e volta: tela -> endereço -> mesma tela', () => {
    for (const view of Object.keys(PORTAL_PATHS) as PortalView[]) {
      expect(viewFromPath(pathFromView(view))).toBe(view);
    }
  });

  it('o endereço repete o nome do item de menu', () => {
    // Quem manda o link consegue dizer o que abre sem abrir.
    expect(pathFromView('landing')).toBe('/');
    expect(pathFromView('o-ava')).toBe('/o-ava');
    expect(pathFromView('certificados')).toBe('/certificados');
    expect(pathFromView('calendario')).toBe('/calendario');
  });
});

describe('leitura do endereço', () => {
  it('aceita barra final e maiúscula, que a pessoa digita', () => {
    expect(viewFromPath('/cursos/')).toBe('cursos');
    expect(viewFromPath('/Cursos')).toBe('cursos');
    expect(viewFromPath('/CURSOS//')).toBe('cursos');
    expect(normalizarCaminho('')).toBe('/');
    expect(normalizarCaminho('/')).toBe('/');
  });

  it('endereço desconhecido devolve null, e não a landing', () => {
    /*
     * Cair na landing esconderia link quebrado — inclusive link nosso, escrito
     * errado. Quem chama decide o que fazer (o App redireciona para a raiz).
     */
    expect(viewFromPath('/nao-existe')).toBeNull();
    expect(viewFromPath('/o-avaa')).toBeNull();
    expect(viewFromPath('/cursos/extra')).toBeNull();
    expect(caminhoConhecido('/nao-existe')).toBe(false);
  });

  it('a área autenticada abraça o que vier abaixo dela', () => {
    // As fases seguintes penduram as seções dos painéis aqui; elas não podem
    // ser lidas como endereço inválido e cair na raiz.
    expect(viewFromPath('/app')).toBe('active_app');
    expect(viewFromPath('/inst')).toBe('active_app');
    expect(viewFromPath('/inst/curso/course-1/grade-curricular')).toBe('active_app');
    expect(viewFromPath('/aluno/qualquer/coisa')).toBe('active_app');
    expect(viewFromPath('/admin')).toBe('active_app');
  });

  it('não confunde prefixo parecido com a área autenticada', () => {
    // `/institucional` começa com as letras de `/inst` e NÃO é o painel.
    expect(viewFromPath('/institucional')).toBeNull();
    expect(viewFromPath('/application')).toBeNull();
  });
});
