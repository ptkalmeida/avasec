import { describe, it, expect } from 'vitest';
import {
  normalizeCpf,
  isValidCpf,
  maskCpf,
  maskCep,
  maskCelular,
  passwordProblem,
  PASSWORD_MIN_LENGTH,
} from '../../src/utils/cpf';

/**
 * Espelho de backend-laravel/tests/Feature/CpfLoginTest.php — o backend é a
 * autoridade, mas divergência entre os dois validadores viraria um formulário
 * que aceita e um servidor que rejeita (ou o contrário).
 */
describe('validação de CPF', () => {
  it('aceita CPF válido, com e sem pontuação', () => {
    expect(isValidCpf('52998224725')).toBe(true);
    expect(isValidCpf('529.982.247-25')).toBe(true);
  });

  it('rejeita dígito verificador errado', () => {
    expect(isValidCpf('52998224724')).toBe(false);
  });

  it('rejeita quantidade de dígitos incorreta', () => {
    expect(isValidCpf('123456789')).toBe(false);
    expect(isValidCpf('529982247251')).toBe(false);
    expect(isValidCpf('')).toBe(false);
  });

  it('rejeita sequências de dígito repetido', () => {
    // Passam no cálculo por acidente matemático, mas nenhuma é CPF real.
    for (const seq of ['00000000000', '11111111111', '99999999999']) {
      expect(isValidCpf(seq), `${seq} deveria ser inválido`).toBe(false);
    }
  });

  it('normaliza para dígitos', () => {
    expect(normalizeCpf('529.982.247-25')).toBe('52998224725');
  });
});

describe('máscaras de digitação', () => {
  it('aplica máscara de CPF progressivamente', () => {
    expect(maskCpf('529')).toBe('529');
    expect(maskCpf('529982')).toBe('529.982');
    expect(maskCpf('529982247')).toBe('529.982.247');
    expect(maskCpf('52998224725')).toBe('529.982.247-25');
  });

  it('descarta dígitos além do 11º no CPF', () => {
    expect(maskCpf('5299822472599')).toBe('529.982.247-25');
  });

  it('aplica máscara de CEP e celular', () => {
    expect(maskCep('20031170')).toBe('20031-170');
    expect(maskCelular('21999991234')).toBe('(21) 99999-1234');
    expect(maskCelular('2133331234')).toBe('(21) 3333-1234');
  });
});

describe('política de senha', () => {
  it('aceita senha com letra, número e tamanho mínimo', () => {
    expect(passwordProblem('cultura2026')).toBeNull();
  });

  it('rejeita senha curta', () => {
    expect(passwordProblem('abc123')).toContain(String(PASSWORD_MIN_LENGTH));
  });

  it('rejeita senha só de números e só de letras', () => {
    expect(passwordProblem('12345678')).toBe('A senha deve conter ao menos uma letra.');
    expect(passwordProblem('senhasenha')).toBe('A senha deve conter ao menos um número.');
  });
});
