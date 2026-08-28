import { describe, it, expect } from 'vitest';
import {
  parseLessonContent,
  serializeLessonBlock,
  replaceLessonBlock,
  removeLessonBlock,
  insertLessonBlockAfter,
} from '../../src/utils/lessonContent';

describe('parseLessonContent', () => {
  it('trata conteúdo vazio sem quebrar', () => {
    const r = parseLessonContent('');
    expect(r.blocks).toEqual([]);
    expect(r.sections).toEqual([]);
    expect(r.hasCode).toBe(false);
  });

  it('numera seções (##) e mantém subtítulos (###) sem número', () => {
    const r = parseLessonContent('## Primeira\n\n### Detalhe\n\n## Segunda');

    expect(r.sections).toEqual([
      { id: 'primeira', text: 'Primeira', level: 2, index: 1 },
      { id: 'detalhe', text: 'Detalhe', level: 3 },
      { id: 'segunda', text: 'Segunda', level: 2, index: 2 },
    ]);
  });

  it('gera slug sem acento para ancorar o índice', () => {
    const r = parseLessonContent('## Herança e Instanciação');
    expect(r.sections[0].id).toBe('heranca-e-instanciacao');
  });

  it('mantém compatibilidade: conteúdo antigo só com ### continua renderizando', () => {
    const r = parseLessonContent('### O que é UX Design?\nUX trata da experiência.');

    expect(r.blocks[0]).toMatchObject({ kind: 'subsection', id: 'o-que-e-ux-design', text: 'O que é UX Design?' });
    expect(r.blocks[1]).toMatchObject({ kind: 'paragraph', text: 'UX trata da experiência.' });
  });

  it('extrai bloco de código com linguagem e preserva a indentação', () => {
    const r = parseLessonContent('```java\npublic class Pessoa {\n    private String nome;\n}\n```');

    expect(r.hasCode).toBe(true);
    expect(r.blocks).toHaveLength(1);
    expect(r.blocks[0]).toMatchObject({
      kind: 'code',
      language: 'java',
      caption: null,
      code: 'public class Pessoa {\n    private String nome;\n}',
    });
  });

  it('aceita bloco de código sem linguagem declarada', () => {
    const r = parseLessonContent('```\nsem realce\n```');
    expect(r.blocks[0]).toMatchObject({ kind: 'code', language: null });
  });

  it('associa a legenda ao bloco de código seguinte', () => {
    const r = parseLessonContent('Código 5: Método atualizarID:\n```java\nvoid x() {}\n```');

    expect(r.blocks).toHaveLength(1);
    expect(r.blocks[0]).toMatchObject({
      kind: 'code',
      caption: 'Código 5: Método atualizarID:',
    });
  });

  it('legenda sem código depois vira parágrafo comum', () => {
    const r = parseLessonContent('Código 5: isso nunca virou bloco.\n\n## Fim');

    expect(r.blocks[0]).toMatchObject({ kind: 'paragraph', text: 'Código 5: isso nunca virou bloco.' });
  });

  it('separa lista numerada de lista com marcador', () => {
    const r = parseLessonContent('1. um\n2. dois\n\n- alfa\n- beta');

    expect(r.blocks).toHaveLength(2);
    expect(r.blocks[0]).toMatchObject({ kind: 'orderedList', items: ['um', 'dois'] });
    expect(r.blocks[1]).toMatchObject({ kind: 'bulletList', items: ['alfa', 'beta'] });
  });

  it('não confunde ### com ##', () => {
    const r = parseLessonContent('### Sub');
    expect(r.sections[0].level).toBe(3);
  });

  it('fecha o bloco de código mesmo sem cerca final', () => {
    const r = parseLessonContent('```java\nint a = 1;');
    expect(r.blocks[0]).toMatchObject({ kind: 'code', code: 'int a = 1;' });
  });
});

describe('ranges dos blocos', () => {
  const CONTEUDO = [
    '## Primeira seção',
    '',
    'Um parágrafo qualquer.',
    '',
    '1. um',
    '2. dois',
    '',
    'Código 1: exemplo:',
    '```java',
    'int a = 1;',
    '```',
    '',
    '### Fim',
  ].join('\n');

  it('cada range aponta exatamente para o trecho que gerou o bloco', () => {
    const r = parseLessonContent(CONTEUDO);

    // O recorte pelo range tem de reproduzir o bloco serializado — é essa
    // igualdade que permite editar UM bloco sem reescrever o resto do texto.
    for (const bloco of r.blocks) {
      expect(CONTEUDO.slice(bloco.range.start, bloco.range.end)).toBe(serializeLessonBlock(bloco));
    }
  });

  it('o range do bloco de código inclui a legenda', () => {
    const r = parseLessonContent(CONTEUDO);
    const code = r.blocks.find((b) => b.kind === 'code')!;
    const recorte = CONTEUDO.slice(code.range.start, code.range.end);

    expect(recorte).toContain('Código 1: exemplo:');
    expect(recorte).toContain('int a = 1;');
  });

  it('troca um bloco sem tocar no restante do conteúdo', () => {
    const r = parseLessonContent(CONTEUDO);
    const paragrafo = r.blocks.find((b) => b.kind === 'paragraph')!;

    const novo = replaceLessonBlock(CONTEUDO, paragrafo.range, 'Texto trocado.');

    expect(novo).toContain('Texto trocado.');
    expect(novo).not.toContain('Um parágrafo qualquer.');
    expect(novo).toContain('## Primeira seção');
    expect(novo).toContain('```java');
    expect(novo).toContain('### Fim');
  });

  it('remove o bloco sem deixar buraco de linhas em branco', () => {
    const r = parseLessonContent(CONTEUDO);
    const lista = r.blocks.find((b) => b.kind === 'orderedList')!;

    const novo = removeLessonBlock(CONTEUDO, lista.range);

    expect(novo).not.toContain('1. um');
    expect(novo).not.toMatch(/\n{3,}/);
    expect(parseLessonContent(novo).blocks).toHaveLength(r.blocks.length - 1);
  });

  it('insere bloco novo depois do indicado', () => {
    const r = parseLessonContent(CONTEUDO);

    const novo = insertLessonBlockAfter(CONTEUDO, r.blocks[0].range, '### Recém-criado');

    expect(parseLessonContent(novo).blocks[1]).toMatchObject({ kind: 'subsection', text: 'Recém-criado' });
  });

  it('primeiro bloco de conteúdo vazio não vem com linhas sobrando', () => {
    expect(insertLessonBlockAfter('', null, '## Começo')).toBe('## Começo');
  });
});
