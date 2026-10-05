import { describe, it, expect } from 'vitest';
import { origemDoPerfil, destinoDoVoltar, rotuloDoVoltar } from '../../src/utils/voltarParaOrigem';

/*
 * O defeito: o aluno clicava em "Certificados" no meio de um curso e o Voltar
 * levava para a escolha de curso — o começo de tudo. O mesmo valia para as
 * abas do painel (Biblioteca, Documentos...), que voltavam sempre ao "Painel".
 */

describe('origem do Perfil', () => {
  it('guarda o endereço completo, com a busca', () => {
    expect(origemDoPerfil('/aluno/curso/react/aula/a-1', '?janela=x')).toBe('/aluno/curso/react/aula/a-1?janela=x');
  });

  it('o próprio Perfil não é origem — clicar em Perfil estando nele não apaga a volta', () => {
    expect(origemDoPerfil('/perfil')).toBeNull();
  });

  it('endereço desconhecido não é origem', () => {
    expect(origemDoPerfil('/nao-existe')).toBeNull();
  });

  it('página pública é origem: quem abriu o Perfil vendo notícias volta para as notícias', () => {
    expect(origemDoPerfil('/noticias')).toBe('/noticias');
  });
});

describe('destino do Voltar', () => {
  const RAIZ = '/aluno';

  it('volta para o curso de onde saiu, e não para a raiz', () => {
    expect(destinoDoVoltar('/aluno/curso/react', 'student', RAIZ)).toBe('/aluno/curso/react');
  });

  it('sem origem (F5, link direto) vai para a raiz de sempre', () => {
    expect(destinoDoVoltar(null, 'student', RAIZ)).toBe(RAIZ);
  });

  it('origem do painel de OUTRO papel não vale — vai para a raiz do papel atual', () => {
    // Depois de uma troca de perfil, voltar ao painel antigo só daria redirecionamento.
    expect(destinoDoVoltar('/inst/curso/react', 'student', RAIZ)).toBe(RAIZ);
    expect(destinoDoVoltar('/aluno/curso/react', null, '/')).toBe('/');
  });

  it('página pública vale para qualquer um', () => {
    expect(destinoDoVoltar('/noticias', 'student', RAIZ)).toBe('/noticias');
    expect(destinoDoVoltar('/noticias', null, '/')).toBe('/noticias');
  });
});

describe('rótulo do Voltar', () => {
  it('diz para onde leva', () => {
    expect(rotuloDoVoltar('/aluno/curso/react')).toBe('Voltar ao curso');
    expect(rotuloDoVoltar('/aluno/curso/react/aula/a-1')).toBe('Voltar à aula');
    expect(rotuloDoVoltar('/aluno/curso/react/avaliacoes/q-1')).toBe('Voltar ao curso');
    expect(rotuloDoVoltar('/aluno/catalogo/react')).toBe('Voltar ao catálogo');
    expect(rotuloDoVoltar('/aluno/biblioteca')).toBe('Voltar à Biblioteca');
    expect(rotuloDoVoltar('/aluno/certificados')).toBe('Voltar aos Certificados');
    expect(rotuloDoVoltar('/noticias')).toBe('Voltar');
  });

  it('painel e ausência de origem usam o texto de quem chama', () => {
    expect(rotuloDoVoltar('/aluno')).toBe('Voltar ao Painel');
    expect(rotuloDoVoltar(null)).toBe('Voltar ao Painel');
    expect(rotuloDoVoltar('/aluno', 'Voltar ao Meu Painel de Estudos')).toBe('Voltar ao Meu Painel de Estudos');
    expect(rotuloDoVoltar(null, 'Voltar ao Meu Painel de Estudos')).toBe('Voltar ao Meu Painel de Estudos');
  });

  it('painel do instrutor e do admin é "Painel"', () => {
    expect(rotuloDoVoltar('/inst/curso/react/grade-curricular')).toBe('Voltar ao Painel');
    expect(rotuloDoVoltar('/admin')).toBe('Voltar ao Painel');
  });
});
