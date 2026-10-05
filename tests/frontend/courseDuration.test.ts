import { describe, it, expect } from 'vitest';
import { minutosDaDuracao, tempoDaGrade, textoDoTempoDaGrade } from '../../src/utils/courseDuration';

const aula = (duration?: string | null) => ({ duration });

describe('leitura da duração digitada', () => {
  it('lê o formato que o cadastro usa', () => {
    // É o que está no banco hoje: "15 min", "22 min", "30 min".
    expect(minutosDaDuracao('15 min')).toBe(15);
    expect(minutosDaDuracao('22 min')).toBe(22);
    expect(minutosDaDuracao('90')).toBe(90);
    expect(minutosDaDuracao('45min')).toBe(45);
    expect(minutosDaDuracao('45 minutos')).toBe(45);
  });

  it('lê as variações que aparecem quando se digita à mão', () => {
    expect(minutosDaDuracao('1h')).toBe(60);
    expect(minutosDaDuracao('1h30')).toBe(90);
    expect(minutosDaDuracao('2 horas')).toBe(120);
    expect(minutosDaDuracao('1 hora 30 min')).toBe(90);
    expect(minutosDaDuracao('1,5h')).toBe(90);
  });

  it('devolve null, e não zero, para o que não reconhece', () => {
    /*
     * A distinção que importa: zero somaria em silêncio, e o total sairia menor
     * sem ninguém saber por quê. Null obriga quem chama a decidir o que dizer.
     */
    expect(minutosDaDuracao('a combinar')).toBeNull();
    expect(minutosDaDuracao('')).toBeNull();
    expect(minutosDaDuracao('   ')).toBeNull();
    expect(minutosDaDuracao(null)).toBeNull();
    expect(minutosDaDuracao(undefined)).toBeNull();
    expect(minutosDaDuracao('quinze')).toBeNull();
  });
});

describe('soma da grade', () => {
  it('soma as aulas legíveis e conta as ilegíveis à parte', () => {
    const r = tempoDaGrade([aula('15 min'), aula('30 min'), aula('a combinar'), aula('')]);

    expect(r).toEqual({ minutos: 45, semDuracao: 2, contadas: 2 });
  });

  it('grade vazia ou ausente não é erro', () => {
    expect(tempoDaGrade([])).toEqual({ minutos: 0, semDuracao: 0, contadas: 0 });
    expect(tempoDaGrade(null)).toEqual({ minutos: 0, semDuracao: 0, contadas: 0 });
    expect(tempoDaGrade(undefined)).toEqual({ minutos: 0, semDuracao: 0, contadas: 0 });
  });
});

describe('texto que vai para a tela', () => {
  it('curso VAZIO não anuncia duração nenhuma', () => {
    /*
     * O defeito que originou tudo isto: o card mostrava `~12.4 horas` escrito
     * literalmente no JSX, então um curso recém-criado, sem uma única aula, já
     * anunciava 12,4 horas de conteúdo.
     *
     * E o substituto não pode ser "0 horas": zero também é uma afirmação sobre
     * duração, e curso sem aula não tem duração a afirmar.
     */
    expect(textoDoTempoDaGrade([])).toBe('—');
    expect(textoDoTempoDaGrade(null)).toBe('—');
    expect(textoDoTempoDaGrade([aula('a combinar')])).toBe('—');
  });

  it('MUDA quando uma aula é acrescentada', () => {
    // A segunda queixa: acrescentar aula não mexia no total, porque o total não
    // vinha das aulas.
    const uma = textoDoTempoDaGrade([aula('30 min')]);
    const duas = textoDoTempoDaGrade([aula('30 min'), aula('45 min')]);

    expect(uma).toBe('30 min');
    expect(duas).not.toBe(uma);
    expect(duas).toBe('1,3 h');
  });

  it('abaixo de uma hora fala em minutos', () => {
    // "0,3 h" para uma aula de 20 minutos seria pior que o número fixo.
    expect(textoDoTempoDaGrade([aula('20 min')])).toBe('20 min');
    expect(textoDoTempoDaGrade([aula('59')])).toBe('59 min');
    expect(textoDoTempoDaGrade([aula('60')])).toBe('1,0 h');
  });

  it('declara quantas aulas ficaram de fora, em vez de somar como zero', () => {
    expect(textoDoTempoDaGrade([aula('1h'), aula('a combinar')]))
      .toBe('1,0 h (1 aula sem duração)');
    expect(textoDoTempoDaGrade([aula('1h'), aula(''), aula(null)]))
      .toBe('1,0 h (2 aulas sem duração)');
  });

  it('confere com a grade real do curso 1', () => {
    // 15 + 22 + 30 + 25 + 18 = 110 min = 1,8 h. O card dizia 12,4.
    const grade = [aula('15 min'), aula('22 min'), aula('30 min'), aula('25 min'), aula('18 min')];

    expect(tempoDaGrade(grade).minutos).toBe(110);
    expect(textoDoTempoDaGrade(grade)).toBe('1,8 h');
  });
});
