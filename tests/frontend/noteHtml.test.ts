import { describe, it, expect } from 'vitest';
import { sanitizeNoteHtml, escapeHtml } from '../../src/utils/noteHtml';

describe('sanitizeNoteHtml', () => {
  it('preserva a formatação que o editor produz', () => {
    const html = '<div><b>Negrito</b> e <i>itálico</i><ul><li>Item</li></ul></div>';
    expect(sanitizeNoteHtml(html)).toBe(html);
  });

  it('remove script inteiro, com o código dentro', () => {
    const limpo = sanitizeNoteHtml('<p>Antes</p><script>alert(1)</script><p>Depois</p>');
    expect(limpo).toBe('<p>Antes</p><p>Depois</p>');
    expect(limpo).not.toContain('alert');
  });

  it('remove style e iframe', () => {
    expect(sanitizeNoteHtml('<style>body{display:none}</style><b>ok</b>')).toBe('<b>ok</b>');
    expect(sanitizeNoteHtml('<iframe src="https://exemplo.com"></iframe><b>ok</b>')).toBe('<b>ok</b>');
  });

  it('remove event handler mantendo a tag e o texto', () => {
    expect(sanitizeNoteHtml('<b onclick="alert(1)">texto</b>')).toBe('<b>texto</b>');
    expect(sanitizeNoteHtml('<img src=x onerror="alert(1)">')).toBe('');
  });

  it('remove style inline, que permite exfiltrar por url()', () => {
    expect(sanitizeNoteHtml('<span style="background:url(//x)">t</span>')).toBe('<span>t</span>');
  });

  it('desembrulha tag desconhecida preservando o texto escrito', () => {
    // A anotação é conteúdo do aluno: perder o texto seria pior que perder a tag.
    expect(sanitizeNoteHtml('<marquee>Minha anotação</marquee>')).toBe('Minha anotação');
    expect(sanitizeNoteHtml('<form><b>importante</b></form>')).toBe('<b>importante</b>');
  });

  it('remove href com javascript: e mantém link legítimo', () => {
    expect(sanitizeNoteHtml('<a href="javascript:alert(1)">x</a>'))
      .toBe('<a rel="noreferrer noopener" target="_blank">x</a>');
    expect(sanitizeNoteHtml('<a href="https://exemplo.com">x</a>'))
      .toContain('href="https://exemplo.com"');
  });

  it('marca link com rel/target, para não alcançar a janela de origem', () => {
    const limpo = sanitizeNoteHtml('<a href="https://exemplo.com">x</a>');
    expect(limpo).toContain('rel="noreferrer noopener"');
    expect(limpo).toContain('target="_blank"');
  });

  it('lida com entrada vazia e aninhamento profundo', () => {
    expect(sanitizeNoteHtml('')).toBe('');
    expect(sanitizeNoteHtml('<div><div><script>alert(1)</script><b>fundo</b></div></div>'))
      .toBe('<div><div><b>fundo</b></div></div>');
  });
});

describe('escapeHtml', () => {
  it('neutraliza título de aula usado no arquivo exportado', () => {
    // O título vem do servidor: sem escape, isso executaria no .html baixado.
    expect(escapeHtml('</title><script>alert(1)</script>'))
      .toBe('&lt;/title&gt;&lt;script&gt;alert(1)&lt;/script&gt;');
  });

  it('escapa aspas e ampersand', () => {
    expect(escapeHtml(`Aula "1" & 2 's`)).toBe('Aula &quot;1&quot; &amp; 2 &#39;s');
  });

  it('não altera texto comum', () => {
    expect(escapeHtml('Usando câmera fotográfica')).toBe('Usando câmera fotográfica');
  });
});
