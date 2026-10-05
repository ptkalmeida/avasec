import { describe, it, expect } from 'vitest';
import {
  AbaAdmin,
  RAIZ_ADMIN,
  SEGMENTO_RELATORIO,
  SubAbaRelatorio,
  abaDaSecaoAdmin,
  caminhoAdmin,
  parseAdmin,
  secaoDaAbaAdmin,
} from '../../src/router/adminRoutes';

const TODAS_AS_ABAS: AbaAdmin[] = [
  'analytics', 'professors', 'courses', 'students', 'requests',
  'settings', 'exercicios', 'export_bi', 'templates', 'site_content',
];

describe('seções da barra lateral', () => {
  it('cada seção tem endereço, e nenhum se repete', () => {
    // Endereço repetido faria duas seções responderem ao mesmo link.
    const enderecos = TODAS_AS_ABAS.map((aba) => caminhoAdmin({ aba }));
    expect(new Set(enderecos).size).toBe(TODAS_AS_ABAS.length);
  });

  it('ida e volta em todas as seções', () => {
    for (const aba of TODAS_AS_ABAS) {
      expect(parseAdmin(caminhoAdmin({ aba })).aba).toBe(aba);
    }
  });

  it('o endereço usa o rótulo do menu, não o nome interno em inglês', () => {
    // Quem lê a URL precisa reconhecer o item que clicou.
    expect(caminhoAdmin({ aba: 'professors' })).toBe('/admin/equipe');
    expect(caminhoAdmin({ aba: 'export_bi' })).toBe('/admin/dados-gerenciais');
    expect(caminhoAdmin({ aba: 'site_content' })).toBe('/admin/paginas-do-site');
    expect(caminhoAdmin({ aba: 'requests' })).toBe('/admin/documentos');
  });

  it('o Dashboard é a entrada e não ganha segmento', () => {
    // `/admin/analytics` seria o segundo endereço da mesma tela.
    expect(caminhoAdmin({})).toBe(RAIZ_ADMIN);
    expect(caminhoAdmin({ aba: 'analytics' })).toBe(RAIZ_ADMIN);
    expect(secaoDaAbaAdmin('analytics')).toBe('');
    expect(parseAdmin('/admin').aba).toBe('analytics');
  });

  it('seção desconhecida cai no Dashboard', () => {
    expect(abaDaSecaoAdmin('nao-existe')).toBe('analytics');
    expect(parseAdmin('/admin/inventado').aba).toBe('analytics');
  });
});

describe('a colisão entre relatório de Alunos e a lista de Alunos', () => {
  it('são telas diferentes e têm endereços diferentes', () => {
    /*
     * O motivo do segmento reservado: a sub-aba de relatório "Alunos" e a seção
     * de lista "Alunos" existem as duas. Sem separá-las, `/admin/alunos`
     * significaria uma ou outra dependendo da ordem em que fossem lidas.
     */
    const relatorio = caminhoAdmin({ aba: 'analytics', subAba: 'alunos' });
    const lista = caminhoAdmin({ aba: 'students' });

    expect(relatorio).toBe('/admin/relatorio/alunos');
    expect(lista).toBe('/admin/alunos');
    expect(relatorio).not.toBe(lista);

    expect(parseAdmin(relatorio)).toMatchObject({ aba: 'analytics', subAba: 'alunos' });
    expect(parseAdmin(lista)).toMatchObject({ aba: 'students', subAba: 'consolidado' });
  });

  it('o mesmo vale para Cursos e Equipe Pedagógica, que também existem nos dois lugares', () => {
    expect(caminhoAdmin({ aba: 'analytics', subAba: 'cursos' })).not.toBe(caminhoAdmin({ aba: 'courses' }));
    expect(caminhoAdmin({ aba: 'analytics', subAba: 'professores' })).not.toBe(caminhoAdmin({ aba: 'professors' }));
    expect(parseAdmin('/admin/cursos').aba).toBe('courses');
    expect(parseAdmin('/admin/relatorio/cursos')).toMatchObject({ aba: 'analytics', subAba: 'cursos' });
  });
});

describe('sub-abas de relatório', () => {
  it('a padrão fica fora do endereço', () => {
    expect(caminhoAdmin({ aba: 'analytics', subAba: 'consolidado' })).toBe(RAIZ_ADMIN);
    expect(parseAdmin('/admin').subAba).toBe('consolidado');
  });

  it('ida e volta nas demais', () => {
    for (const sub of ['alunos', 'professores', 'cursos', 'inscricoes'] as SubAbaRelatorio[]) {
      const caminho = caminhoAdmin({ aba: 'analytics', subAba: sub });
      expect(caminho).toBe(`/admin/${SEGMENTO_RELATORIO}/${sub}`);
      expect(parseAdmin(caminho).subAba).toBe(sub);
    }
  });

  it('sub-aba inválida ou ausente cai no consolidado', () => {
    expect(parseAdmin('/admin/relatorio').subAba).toBe('consolidado');
    expect(parseAdmin('/admin/relatorio/inventada').subAba).toBe('consolidado');
  });

  it('sub-aba não vale fora do Dashboard', () => {
    // Guardá-la em outra seção seria estado sem tela para mostrar.
    expect(caminhoAdmin({ aba: 'courses', subAba: 'alunos' })).toBe('/admin/cursos');
  });
});

describe('ficha do aluno', () => {
  it('a ficha tem endereço próprio, para ser mandada por link', () => {
    expect(caminhoAdmin({ aba: 'students', alunoId: 'aluno-1' })).toBe('/admin/alunos/aluno-1');
    expect(parseAdmin('/admin/alunos/aluno-1')).toMatchObject({ aba: 'students', alunoId: 'aluno-1' });
    expect(parseAdmin('/admin/alunos').alunoId).toBeNull();
  });

  it('id de aluno só existe abaixo da seção Alunos', () => {
    expect(caminhoAdmin({ aba: 'courses', alunoId: 'aluno-1' })).toBe('/admin/cursos');
    expect(parseAdmin('/admin/cursos/aluno-1').alunoId).toBeNull();
  });

  it('id com caractere especial sobrevive à ida e volta', () => {
    const id = 'aluno/1 com espaço';
    const caminho = caminhoAdmin({ aba: 'students', alunoId: id });
    expect(caminho).toContain('%2F');
    expect(parseAdmin(caminho).alunoId).toBe(id);
  });
});

describe('janela e caminhos de fora', () => {
  it('a janela entra e sai do endereço', () => {
    expect(caminhoAdmin({ aba: 'students', janela: 'novo-aluno' }))
      .toBe('/admin/alunos?janela=novo-aluno');
    expect(parseAdmin('/admin/alunos', '?janela=novo-aluno').janela).toBe('novo-aluno');
    expect(caminhoAdmin({ janela: '' })).toBe(RAIZ_ADMIN);
    expect(parseAdmin('/admin').janela).toBeNull();
  });

  it('não confunde prefixo parecido', () => {
    expect(parseAdmin('/administracao/equipe').aba).toBe('analytics');
    expect(parseAdmin('/inst/curso/course-1').aba).toBe('analytics');
  });
});
