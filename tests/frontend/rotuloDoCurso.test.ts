import { describe, it, expect } from 'vitest';
import { rotuloDoCursoGerido } from '../../src/utils/rotuloDoCurso';

/*
 * O painel do instrutor tinha dois seletores de curso ligados à mesma escolha.
 * Ficou só o "Curso ativo", que herdou do antigo a categoria e a marca de
 * vigência encerrada.
 */
describe('rotuloDoCursoGerido', () => {
  it('mostra a categoria antes do nome', () => {
    expect(rotuloDoCursoGerido({ title: 'Desenvolvimento Full-Stack', category: 'Tecnologia' }))
      .toBe('Tecnologia • Desenvolvimento Full-Stack');
  });

  it('curso com contrato vencido é marcado — não pode abrir sem aviso', () => {
    expect(rotuloDoCursoGerido({ title: 'Curso Antigo', category: 'Artes', contractExpirationDate: '2020-01-01' }))
      .toBe('[VIGÊNCIA ENCERRADA] Artes • Curso Antigo');
  });

  it('contrato vigente ou sem data não recebe a marca', () => {
    expect(rotuloDoCursoGerido({ title: 'Curso', category: 'Artes', contractExpirationDate: '2999-12-31' }))
      .toBe('Artes • Curso');
    expect(rotuloDoCursoGerido({ title: 'Curso', category: 'Artes' })).toBe('Artes • Curso');
  });

  it('sem categoria, não sobra separador solto', () => {
    expect(rotuloDoCursoGerido({ title: 'Curso', category: '' })).toBe('Curso');
  });
});
