import { describe, it, expect } from 'vitest';
import {
  UFS,
  apenasLetras,
  apenasUf,
  apenasAreaInteresse,
  pareceCpf,
  problemaNoNome,
  problemaNoEmail,
  problemaNoMunicipio,
  problemaNaUf,
  problemaNaArea,
} from '../../src/utils/camposMatricula';

describe('sanitização na digitação', () => {
  it('nome recusa dígito e símbolo no ato', () => {
    expect(apenasLetras('Clara 123')).toBe('Clara ');
    expect(apenasLetras('Clara@Ribeiro')).toBe('ClaraRibeiro');
    expect(apenasLetras('759.981.542-23')).toBe('-');
  });

  it('nome PRESERVA acento, apóstrofo e hífen', () => {
    // Um filtro ingênuo apagaria a acentuação enquanto a pessoa digita.
    expect(apenasLetras('Conceição Sá')).toBe('Conceição Sá');
    expect(apenasLetras("Ana-Luísa D'Ávila")).toBe("Ana-Luísa D'Ávila");
    expect(apenasLetras('João Müller Çedilha')).toBe('João Müller Çedilha');
  });

  it('nome colapsa espaço repetido', () => {
    expect(apenasLetras('Clara    Ribeiro')).toBe('Clara Ribeiro');
  });

  it('UF fica em duas letras maiúsculas', () => {
    expect(apenasUf('pe')).toBe('PE');
    expect(apenasUf('p3e')).toBe('PE');
    expect(apenasUf('sao paulo')).toBe('SA');
    expect(apenasUf('12')).toBe('');
  });

  it('área de interesse aceita os sinais do próprio placeholder', () => {
    expect(apenasAreaInteresse('Economia Criativa & IA')).toBe('Economia Criativa & IA');
    expect(apenasAreaInteresse('Design, UX/UI')).toBe('Design, UX/UI');
    expect(apenasAreaInteresse('Dados <script>')).toBe('Dados script');
  });
});

describe('nome completo', () => {
  it('exige nome e sobrenome', () => {
    // O campo se chama "Nome Completo", e o nome vai para o certificado.
    expect(problemaNoNome('Clara')).toMatch(/sobrenome/i);
    expect(problemaNoNome('Clara Ribeiro')).toBeNull();
  });

  it('recusa dígito', () => {
    expect(problemaNoNome('Clara 2')).toMatch(/apenas letras/i);
  });

  it('recusa vazio e nome curto demais', () => {
    expect(problemaNoNome('   ')).toMatch(/informe o nome/i);
    expect(problemaNoNome('A B')).toMatch(/curto/i);
  });

  it('aceita nome com acento, apóstrofo e composto', () => {
    expect(problemaNoNome('Conceição Sá')).toBeNull();
    expect(problemaNoNome("Ana-Luísa D'Ávila")).toBeNull();
    expect(problemaNoNome('José da Silva Neto')).toBeNull();
  });

  it('a validação NÃO alterna entre chamadas', () => {
    // Regressão de regex com flag `g`: `test()` guarda lastIndex e a segunda
    // chamada com a mesma string devolveria o contrário da primeira.
    const invalido = 'Clara 123';
    expect(problemaNoNome(invalido)).toMatch(/apenas letras/i);
    expect(problemaNoNome(invalido)).toMatch(/apenas letras/i);
    expect(problemaNoNome(invalido)).toMatch(/apenas letras/i);
  });
});

describe('e-mail', () => {
  it('diz que é CPF quando o CPF foi digitado no campo de e-mail', () => {
    // O erro real do print: 759.981.542-23 dentro do campo "E-mail Acadêmico".
    // "E-mail inválido" não ajudaria a pessoa a se achar.
    expect(problemaNoEmail('759.981.542-23')).toMatch(/isto é um cpf/i);
    expect(problemaNoEmail('75998154223')).toMatch(/isto é um cpf/i);
  });

  it('recusa formato inválido', () => {
    expect(problemaNoEmail('clara')).toMatch(/e-mail válido/i);
    expect(problemaNoEmail('clara@lms')).toMatch(/e-mail válido/i);
    expect(problemaNoEmail('clara@@lms.edu')).toMatch(/e-mail válido/i);
    expect(problemaNoEmail('cla ra@lms.edu')).toMatch(/espaços/i);
    expect(problemaNoEmail('  ')).toMatch(/informe o e-mail/i);
  });

  it('aceita e-mail comum e com subdomínio', () => {
    expect(problemaNoEmail('clara.ribeiro@lms.edu')).toBeNull();
    expect(problemaNoEmail('clara@escola.pe.gov.br')).toBeNull();
    expect(problemaNoEmail('  clara@lms.edu  ')).toBeNull();
  });
});

describe('pareceCpf', () => {
  it('reconhece com e sem máscara', () => {
    expect(pareceCpf('759.981.542-23')).toBe(true);
    expect(pareceCpf('75998154223')).toBe(true);
  });

  it('não confunde e-mail nem número curto com CPF', () => {
    expect(pareceCpf('clara@lms.edu')).toBe(false);
    expect(pareceCpf('123')).toBe(false);
    expect(pareceCpf('clara75998154223')).toBe(false);
  });
});

describe('município e UF', () => {
  it('município recusa dígito e aceita composto', () => {
    expect(problemaNoMunicipio('Recife 2')).toMatch(/apenas letras/i);
    expect(problemaNoMunicipio('São Bernardo do Campo')).toBeNull();
    expect(problemaNoMunicipio("Santa Bárbara d'Oeste")).toBeNull();
  });

  it('município é opcional', () => {
    expect(problemaNoMunicipio('')).toBeNull();
    expect(problemaNoMunicipio('   ')).toBeNull();
  });

  it('UF tem de ser uma das 27 do país', () => {
    // Antes bastavam dois caracteres: "12" e "XX" entravam no cadastro.
    expect(problemaNaUf('XX')).toMatch(/uf inválida/i);
    expect(problemaNaUf('12')).toMatch(/uf inválida/i);
    expect(problemaNaUf('S')).toMatch(/uf inválida/i);
    expect(problemaNaUf('PE')).toBeNull();
    expect(problemaNaUf('pe')).toBeNull();
    expect(problemaNaUf('')).toBeNull();
  });

  it('a lista tem as 27 unidades federativas', () => {
    expect(UFS).toHaveLength(27);
    expect(UFS).toContain('DF');
    expect(new Set(UFS).size).toBe(27);
  });
});

describe('área de interesse', () => {
  it('recusa símbolo fora do conjunto e aceita o do placeholder', () => {
    expect(problemaNaArea('Economia Criativa & IA')).toBeNull();
    expect(problemaNaArea('Dados <script>alert(1)</script>')).toMatch(/apenas letras/i);
    expect(problemaNaArea('')).toBeNull();
  });
});
