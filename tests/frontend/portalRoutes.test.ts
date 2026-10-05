import { describe, it, expect } from 'vitest';
import {
  PORTAL_PATHS,
  PortalView,
  pathFromView,
  viewFromPath,
  normalizarCaminho,
  caminhoConhecido,
  ehCaminhoAutenticado,
  caminhoBateComPapel,
  raizDoPapel,
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

describe('a raiz autenticada de cada papel', () => {
  it('cada papel tem a sua, e nenhuma se repete', () => {
    /*
     * Quem escolhe o painel é o PAPEL: o App despacha Student/Instructor/Admin
     * por `activeUser.role`, não pelo caminho. Sem uma raiz canônica por papel,
     * um aluno em `/admin` veria o painel do aluno sob um endereço dizendo
     * "admin" — nada vazaria, mas o endereço mentiria, e endereço é justamente o
     * que a pessoa manda para outra.
     */
    expect(raizDoPapel('student')).toBe('/aluno');
    expect(raizDoPapel('instructor')).toBe('/inst');
    expect(raizDoPapel('admin')).toBe('/admin');
    expect(new Set(['student', 'instructor', 'admin'].map((p) =>
      raizDoPapel(p as 'student'))).size).toBe(3);
  });

  it('o endereço bate com o papel só dentro da própria raiz', () => {
    expect(caminhoBateComPapel('/aluno', 'student')).toBe(true);
    expect(caminhoBateComPapel('/aluno/curso/course-1/avaliacoes', 'student')).toBe(true);
    expect(caminhoBateComPapel('/admin', 'student')).toBe(false);
    expect(caminhoBateComPapel('/inst/curso/course-1', 'student')).toBe(false);
    expect(caminhoBateComPapel('/inst/curso/course-1', 'instructor')).toBe(true);
    expect(caminhoBateComPapel('/admin/alunos/aluno-1', 'admin')).toBe(true);
  });

  it('`/app` não bate com papel nenhum: é o caminho provisório que sai de cena', () => {
    // Ele continua sendo reconhecido como área autenticada, para o redirect
    // conseguir traduzi-lo — mas nunca é o destino final de ninguém.
    expect(ehCaminhoAutenticado('/app')).toBe(true);
    for (const papel of ['student', 'instructor', 'admin'] as const) {
      expect(caminhoBateComPapel('/app', papel)).toBe(false);
    }
  });

  it('área autenticada não é decidida por prefixo cru', () => {
    // `/institucional` começa com as letras de `/inst` e é página pública.
    expect(ehCaminhoAutenticado('/institucional')).toBe(false);
    expect(ehCaminhoAutenticado('/application')).toBe(false);
    expect(ehCaminhoAutenticado('/cursos')).toBe(false);
    expect(ehCaminhoAutenticado('/')).toBe(false);
    expect(caminhoBateComPapel('/alunos', 'student')).toBe(false);
  });
});
