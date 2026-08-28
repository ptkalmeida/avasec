import { describe, it, expect } from 'vitest';
import { parseLessonContent } from '../../src/utils/lessonContent';

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

    expect(r.blocks[0]).toEqual({ kind: 'subsection', id: 'o-que-e-ux-design', text: 'O que é UX Design?' });
    expect(r.blocks[1]).toEqual({ kind: 'paragraph', text: 'UX trata da experiência.' });
  });

  it('extrai bloco de código com linguagem e preserva a indentação', () => {
    const r = parseLessonContent('```java\npublic class Pessoa {\n    private String nome;\n}\n```');

    expect(r.hasCode).toBe(true);
    expect(r.blocks).toHaveLength(1);
    expect(r.blocks[0]).toEqual({
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

    expect(r.blocks[0]).toEqual({ kind: 'paragraph', text: 'Código 5: isso nunca virou bloco.' });
  });

  it('separa lista numerada de lista com marcador', () => {
    const r = parseLessonContent('1. um\n2. dois\n\n- alfa\n- beta');

    expect(r.blocks).toEqual([
      { kind: 'orderedList', items: ['um', 'dois'] },
      { kind: 'bulletList', items: ['alfa', 'beta'] },
    ]);
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
