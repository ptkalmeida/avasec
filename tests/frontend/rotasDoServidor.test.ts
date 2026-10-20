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
    expect(servidoPeloServidor('/aluno', REGRAS)).toBe(true);
    // Biblioteca ainda é React: entregar ao servidor abriria o casco velho.
    expect(servidoPeloServidor('/aluno/biblioteca', REGRAS)).toBe(false);
    expect(servidoPeloServidor('/aluno/mensagens', REGRAS)).toBe(false);
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
    // As telas que já são Blade no contrato real do projeto.
    expect(servidoPeloServidor('/aluno')).toBe(true);
    expect(servidoPeloServidor('/aluno/certificados')).toBe(true);
    // E as que ainda são React.
    expect(servidoPeloServidor('/aluno/biblioteca')).toBe(false);
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
