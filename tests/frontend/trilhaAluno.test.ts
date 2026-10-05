import { describe, it, expect } from 'vitest';
import { trilhaDoAluno } from '../../src/utils/trilhaAluno';
import { DestinoAluno, parseAluno } from '../../src/router/studentRoutes';

const SLUG = 'desenvolvimento-full-stack-react-nodejs-e-apis-modernas';
const TITULOS = { curso: 'Desenvolvimento Full-Stack', aula: 'NodeJS Express: APIs e Rotas REST' };

/** Lê um endereço real do painel, para a trilha ser testada pelo que a URL diz. */
const de = (caminho: string): DestinoAluno => parseAluno(caminho);

describe('a hierarquia que estava só no botão Voltar', () => {
  it('a entrada do painel não tem trilha', () => {
    // Sem degrau não se desenha a barra; "Painel de Estudos" sozinho não informa.
    expect(trilhaDoAluno(de('/aluno'))).toEqual([]);
  });

  it('seção do painel é um degrau, o atual, sem link', () => {
    expect(trilhaDoAluno(de('/aluno/certificados'))).toEqual([{ rotulo: 'Certificados' }]);
    expect(trilhaDoAluno(de('/aluno/biblioteca'))).toEqual([{ rotulo: 'Biblioteca Digital' }]);
  });

  it('curso aberto: um degrau com o NOME do curso, não o slug', () => {
    /*
     * O endereço traz o slug (ADR 13). Mostrar `desenvolvimento-full-stack-...`
     * na trilha seria trocar um identificador por outro — o ponto da trilha é
     * dizer o nome.
     */
    const trilha = trilhaDoAluno(de(`/aluno/curso/${SLUG}`), TITULOS);

    expect(trilha).toEqual([{ rotulo: 'Desenvolvimento Full-Stack' }]);
    expect(JSON.stringify(trilha)).not.toContain(SLUG);
  });

  it('aula aberta: curso clicável, aula como degrau atual', () => {
    const trilha = trilhaDoAluno(de(`/aluno/curso/${SLUG}/aula/l1`), TITULOS);

    expect(trilha).toEqual([
      { rotulo: 'Desenvolvimento Full-Stack', destino: { tela: 'curso' } },
      { rotulo: 'NodeJS Express: APIs e Rotas REST' },
    ]);
  });

  it('a prova em andamento é o degrau mais profundo, e "Avaliações" volta a ser clicável', () => {
    /*
     * É o mesmo degrau que o `handleBack` desfazia primeiro: de dentro da prova
     * se volta para a lista de avaliações, não para o curso.
     */
    const lista = trilhaDoAluno(de(`/aluno/curso/${SLUG}/avaliacoes`), TITULOS);
    expect(lista[lista.length - 1]).toEqual({ rotulo: 'Avaliações' });

    const emProva = trilhaDoAluno(de(`/aluno/curso/${SLUG}/avaliacoes/quiz-1`), TITULOS);
    expect(emProva).toEqual([
      { rotulo: 'Desenvolvimento Full-Stack', destino: { tela: 'curso' } },
      { rotulo: 'Avaliações', destino: { tela: 'avaliacoes' } },
      { rotulo: 'Prova em andamento' },
    ]);
  });

  it('exercícios e sala ao vivo também são degraus de dentro do curso', () => {
    expect(trilhaDoAluno(de(`/aluno/curso/${SLUG}/exercicios`), TITULOS)[1])
      .toEqual({ rotulo: 'Exercícios' });
    expect(trilhaDoAluno(de(`/aluno/curso/${SLUG}/ao-vivo/s1`), TITULOS)[1])
      .toEqual({ rotulo: 'Encontro ao vivo' });
  });

  it('o ÚLTIMO degrau nunca tem destino', () => {
    // É o que o componente usa para não deixar o degrau atual clicável.
    for (const caminho of [
      '/aluno/certificados',
      `/aluno/curso/${SLUG}`,
      `/aluno/curso/${SLUG}/aula/l1`,
      `/aluno/curso/${SLUG}/avaliacoes`,
      `/aluno/curso/${SLUG}/avaliacoes/quiz-1`,
      `/aluno/curso/${SLUG}/exercicios`,
      '/aluno/catalogo/course-1',
    ]) {
      const trilha = trilhaDoAluno(de(caminho), TITULOS);
      expect(trilha[trilha.length - 1].destino, caminho).toBeUndefined();
    }
  });
});

describe('quando os dados ainda não chegaram', () => {
  it('curso sem título não produz degrau vazio nem slug cru', () => {
    /*
     * O catálogo e a matrícula carregam em momentos diferentes — já causou um
     * redirecionamento intermitente neste painel. Aqui o degrau diz "Curso" e
     * muda para o nome quando os dados chegam.
     */
    expect(trilhaDoAluno(de(`/aluno/curso/${SLUG}`), {})).toEqual([{ rotulo: 'Curso' }]);
    expect(trilhaDoAluno(de(`/aluno/curso/${SLUG}`), { curso: '   ' })).toEqual([{ rotulo: 'Curso' }]);
  });

  it('aula sem título diz "Aula"', () => {
    const trilha = trilhaDoAluno(de(`/aluno/curso/${SLUG}/aula/l9`), { curso: 'Curso X' });

    expect(trilha[1]).toEqual({ rotulo: 'Aula' });
  });
});

describe('vitrine do catálogo', () => {
  it('não entra na hierarquia de um curso do aluno', () => {
    // O aluno pode não pertencer ao curso da vitrine; pendurá-la sob "Curso"
    // afirmaria um vínculo que não existe.
    expect(trilhaDoAluno(de('/aluno/catalogo/course-1'), TITULOS))
      .toEqual([{ rotulo: 'Catálogo de cursos' }]);
  });
});
