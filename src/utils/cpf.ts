/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Validação de CPF no cliente (ADR 11) — espelho de
 * `backend-laravel/app/Support/Cpf.php`, no mesmo padrão de
 * `videoSource.ts` ↔ `VideoSource.php`.
 *
 * Serve para feedback imediato no formulário e para a máscara de digitação.
 * A AUTORIDADE continua sendo o backend: nunca confie nesta checagem para
 * decidir acesso — ela é conveniência de UX, não controle de segurança.
 */

/** Mantém só os dígitos. */
export const normalizeCpf = (value: string): string => value.replace(/\D/g, '');

/** Valida formato e os dois dígitos verificadores. */
export const isValidCpf = (value: string): boolean => {
  const digits = normalizeCpf(value);

  if (digits.length !== 11) return false;

  // Sequências de dígito repetido passam no cálculo por acidente, mas nenhuma
  // é um CPF real.
  if (/^(\d)\1{10}$/.test(digits)) return false;

  for (let position = 9; position < 11; position++) {
    let sum = 0;
    for (let i = 0; i < position; i++) {
      sum += Number(digits[i]) * (position + 1 - i);
    }
    const expected = ((10 * sum) % 11) % 10;
    if (Number(digits[position]) !== expected) return false;
  }

  return true;
};

/** Máscara progressiva para digitação: 529982 -> 529.982 */
export const maskCpf = (value: string): string => {
  const d = normalizeCpf(value).slice(0, 11);

  if (d.length <= 3) return d;
  if (d.length <= 6) return `${d.slice(0, 3)}.${d.slice(3)}`;
  if (d.length <= 9) return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6)}`;

  return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6, 9)}-${d.slice(9)}`;
};

/** Máscara progressiva de CEP: 20031170 -> 20031-170 */
export const maskCep = (value: string): string => {
  const d = value.replace(/\D/g, '').slice(0, 8);

  return d.length <= 5 ? d : `${d.slice(0, 5)}-${d.slice(5)}`;
};

/** Máscara progressiva de celular: 21999991234 -> (21) 99999-1234 */
export const maskCelular = (value: string): string => {
  const d = value.replace(/\D/g, '').slice(0, 11);

  if (d.length <= 2) return d;
  if (d.length <= 6) return `(${d.slice(0, 2)}) ${d.slice(2)}`;
  if (d.length <= 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;

  return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
};

/** Política de senha — espelho de `StrongPasswordRule.php`. */
export const PASSWORD_MIN_LENGTH = 8;

export const passwordProblem = (password: string): string | null => {
  if (password.length < PASSWORD_MIN_LENGTH) {
    return `A senha deve ter no mínimo ${PASSWORD_MIN_LENGTH} caracteres.`;
  }
  if (!/\p{L}/u.test(password)) return 'A senha deve conter ao menos uma letra.';
  if (!/\d/.test(password)) return 'A senha deve conter ao menos um número.';

  return null;
};
