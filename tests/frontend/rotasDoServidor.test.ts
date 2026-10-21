import { describe, it, expect } from 'vitest';
import { paraRegex, servidoPeloServidor } from '../../src/router/rotasDoServidor';

/*
 * A regra que decide quando o SPA entrega uma navegação ao servidor — e a
 * mesma que o proxy do Vite usa. Se as duas divergissem, o SPA mandaria ao
 * servidor um endereço que o proxy devolveria ao SPA, e a página recarregaria
 * para sempre. Por isso os casos aqui são os do proxy.
 */
const REGRAS = {
  prefixos: ['/api', '/cursos', '/certificados'],
  exatos: ['/', '/aluno', '/aluno/curso/{cursoRef}', '/aluno/certificados'],
};

describe('servidoPeloServidor', () => {
  it('prefixo casa o caminho e tudo abaixo dele', () => {
    expect(servidoPeloServidor('/api/courses', REGRAS)).toBe(true);
    expect(servidoPeloServidor('/cursos', REGRAS)).toBe(true);
  });

  it('exato casa só o próprio caminho, e não arrasta os filhos', () => {
    // Nas REGRAS de exemplo, `/aluno` é exato: o que não está nelas é React.
    // É assim que uma árvore se migra por partes.
    expect(servidoPeloServidor('/aluno/biblioteca', REGRAS)).toBe(false);
    expect(servidoPeloServidor('/aluno/mensagens', REGRAS)).toBe(false);
  });

  /*
   * O prefixo casa na FRONTEIRA do segmento, como faz o RoteadorMpaTest. Com
   * `startsWith` puro, `/cursos-antigos` seria entregue ao servidor, que o
   * devolveria ao SPA: a página recarregaria para sempre.
   */
  it('prefixo não casa um nome que apenas começa igual', () => {
    expect(servidoPeloServidor('/cursos-antigos', REGRAS)).toBe(false);
    expect(servidoPeloServidor('/apiario', REGRAS)).toBe(false);
    expect(servidoPeloServidor('/cursos/design', REGRAS)).toBe(true);
  });

  it('família (prefixo terminado em -) casa por início, sem exigir barra', () => {
    const familia = { prefixos: ['/livewire-'] };

    expect(servidoPeloServidor('/livewire-abc123/update', familia)).toBe(true);
    expect(servidoPeloServidor('/livewire', familia)).toBe(false);
  });

  it('parâmetro entre chaves é um segmento, e só um', () => {
    expect(servidoPeloServidor('/aluno/curso/design-digital', REGRAS)).toBe(true);
    expect(servidoPeloServidor('/aluno/curso/design-digital/aula/1', REGRAS)).toBe(false);
  });

  it('a raiz é exata: não engole a árvore toda', () => {
    expect(servidoPeloServidor('/', REGRAS)).toBe(true);
    expect(servidoPeloServidor('/inst', REGRAS)).toBe(false);
    expect(servidoPeloServidor('/admin', REGRAS)).toBe(false);
  });

  it('lê o contrato de verdade quando não recebe regras', () => {
    /*
     * A área do aluno INTEIRA é do servidor desde 20/10 — inclusive o endereço
     * desconhecido, que o Laravel devolve ao painel. Enquanto um só endereço
     * de `/aluno` sobrasse para o SPA, bastava chegar nele para navegar, por
     * dentro do React, até as versões antigas das telas já migradas.
     */
    expect(servidoPeloServidor('/aluno')).toBe(true);
    expect(servidoPeloServidor('/aluno/certificados')).toBe(true);
    expect(servidoPeloServidor('/aluno/inventado')).toBe(true);
    expect(servidoPeloServidor('/aluno/curso/x/avaliacoes/y')).toBe(true);
    /*
     * A área do instrutor está no meio da travessia (M4): a escolha de curso
     * já é Blade, e as seções de dentro do curso ainda são React. É por isso
     * que `/inst` entra no contrato como EXATO — como prefixo, arrastaria
     * `/inst/curso/...` junto.
     */
    expect(servidoPeloServidor('/inst')).toBe(true);
    expect(servidoPeloServidor('/inst/mensagens')).toBe(true);
    // A Gestão do Curso é Blade; a grade, as avaliações e os alunos ainda não.
    expect(servidoPeloServidor('/inst/curso/curso-de-fotografia')).toBe(true);
    expect(servidoPeloServidor('/inst/curso/curso-de-fotografia/grade-curricular')).toBe(false);
    expect(servidoPeloServidor('/inst/curso/curso-de-fotografia/avaliacoes')).toBe(false);
    // O admin ainda é React por inteiro.
    expect(servidoPeloServidor('/admin')).toBe(false);
  });
});

describe('paraRegex', () => {
  it('aceita query string, que o Vite inclui na url que compara', () => {
    // O QR impresso no certificado aponta para `/?verify=<hash>`.
    expect(new RegExp(paraRegex('/')).test('/?verify=AVA-123')).toBe(true);
    expect(new RegExp(paraRegex('/aluno')).test('/aluno?aba=x')).toBe(true);
  });

  it('não confunde prefixo de nome com caminho', () => {
    expect(new RegExp(paraRegex('/aluno')).test('/alunos')).toBe(false);
  });
});
