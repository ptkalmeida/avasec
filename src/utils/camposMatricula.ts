/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Formato dos campos do cadastro de aluno.
 *
 * Existe porque o formulário de matrícula aceitava qualquer coisa em qualquer
 * campo. O caso que motivou: um CPF digitado no campo "E-mail Acadêmico" —
 * `type="email"` só reclama no envio, e a tela ficava com o CPF ali parecendo
 * aceito. Nome aceitava dígitos, Município aceitava dígitos, e a UF aceitava
 * dois caracteres quaisquer ("12", "XX") porque só havia `maxLength={2}` e
 * `toUpperCase()`.
 *
 * Duas camadas, de propósito:
 *  - `apenas*`: sanitiza NA DIGITAÇÃO, recusando o caractere errado no ato —
 *    é o comportamento que `maskCpf` já dava ao CPF.
 *  - `problema*`: valida NO ENVIO o que a digitação não consegue julgar
 *    (comprimento, UF existente, e-mail bem formado).
 *
 * A autoridade continua sendo o backend: isto é feedback imediato, não controle
 * de segurança.
 */

import { normalizeCpf } from './cpf';

/** Unidades federativas do Brasil — a UF só pode ser uma destas 27. */
export const UFS: readonly string[] = [
  'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG',
  'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
];

/**
 * Só letras e o que compõe nome de pessoa: espaço, apóstrofo e hífen.
 *
 * Acentos precisam entrar explicitamente — `\w` do JavaScript não cobre
 * `á`, `ç` ou `ã`, e um filtro ingênuo apagaria a acentuação enquanto a pessoa
 * digita "Conceição".
 */
/*
 * As duas classes são escritas por extenso, e não montadas a partir de uma
 * string compartilhada, por dois motivos que já custaram erro aqui:
 *
 * 1. Numa string JS, "\s" é a letra `s` e "\-" é `-`. Montar a classe via
 *    `new RegExp` exigiria escape duplo ("\\s"), e a versão errada aceitava
 *    qualquer caractere sem que nada reclamasse.
 * 2. `RegExp.test()` num literal com flag `g` avança `lastIndex` e o mantém
 *    entre chamadas — a segunda chamada com a mesma string começa do meio e
 *    devolve `false` para um valor inválido. Por isso a de remover tem `g` e a
 *    de testar não, em vez de uma só reaproveitada.
 */
const REMOVE_NAO_LETRA = /[^A-Za-zÀ-ÖØ-öø-ÿ'\-\s]/g;
const TEM_NAO_LETRA = /[^A-Za-zÀ-ÖØ-öø-ÿ'\-\s]/;

/** Sanitiza nome/município na digitação: recusa dígito e símbolo. */
export const apenasLetras = (valor: string): string =>
  valor.replace(REMOVE_NAO_LETRA, '').replace(/\s{2,}/g, ' ');

/** Sanitiza UF na digitação: duas letras, em maiúscula. */
export const apenasUf = (valor: string): string =>
  valor.replace(/[^A-Za-z]/g, '').slice(0, 2).toUpperCase();

/**
 * Sanitiza a área de interesse: letras, números, espaço e os separadores que o
 * próprio placeholder sugere ("Economia Criativa & IA").
 */
export const apenasAreaInteresse = (valor: string): string =>
  valor.replace(/[^A-Za-zÀ-ÖØ-öø-ÿ0-9&,\-/\s]/g, '').replace(/\s{2,}/g, ' ');

/** O texto é, na verdade, um CPF? Usado para explicar o campo trocado. */
export const pareceCpf = (valor: string): boolean => {
  const digitos = normalizeCpf(valor);

  // 11 dígitos e nada além de dígitos, pontos, hífen e espaço.
  return digitos.length === 11 && /^[\d.\-\s]+$/.test(valor.trim());
};

/**
 * Primeiro problema do nome, ou null.
 *
 * Exige dois termos porque o campo se chama "Nome Completo" e o nome vai para
 * o histórico escolar e para o certificado impresso — sobrenome faltando é
 * retrabalho de secretaria depois.
 */
export const problemaNoNome = (valor: string): string | null => {
  const limpo = valor.trim().replace(/\s{2,}/g, ' ');
  if (limpo === '') return 'Informe o nome do aluno.';
  if (TEM_NAO_LETRA.test(limpo)) {
    return 'O nome aceita apenas letras — sem números ou símbolos.';
  }
  if (limpo.replace(/[\s'-]/g, '').length < 3) {
    return 'O nome está curto demais.';
  }
  if (limpo.split(' ').filter((p) => p !== '').length < 2) {
    return 'Informe o nome completo, com pelo menos um sobrenome.';
  }

  return null;
};

/** Primeiro problema do e-mail, ou null. */
export const problemaNoEmail = (valor: string): string | null => {
  const limpo = valor.trim();
  if (limpo === '') return 'Informe o e-mail acadêmico do aluno.';

  // Mensagem específica para o erro que de fato acontece: o CPF vai para o
  // campo de e-mail. "E-mail inválido" não ajudaria a pessoa a se achar.
  if (pareceCpf(limpo)) {
    return 'Isto é um CPF. O CPF vai no campo próprio, abaixo — aqui vai o e-mail.';
  }
  if (/\s/.test(limpo)) return 'O e-mail não pode conter espaços.';

  // Formato deliberadamente simples: um @, algo antes, e um domínio com ponto.
  // Validar e-mail por regex ao extremo recusa endereço legítimo; a conferência
  // real é o servidor aceitar (e, no futuro, a confirmação por e-mail).
  if (!/^[^@\s]+@[^@\s.]+(\.[^@\s.]+)+$/.test(limpo)) {
    return 'Informe um e-mail válido, no formato nome@dominio.com.';
  }

  return null;
};

/** Primeiro problema do município, ou null. Campo opcional. */
export const problemaNoMunicipio = (valor: string): string | null => {
  const limpo = valor.trim();
  if (limpo === '') return null;
  if (TEM_NAO_LETRA.test(limpo)) {
    return 'O município aceita apenas letras.';
  }
  if (limpo.replace(/[\s'-]/g, '').length < 2) {
    return 'O nome do município está curto demais.';
  }

  return null;
};

/** Primeiro problema da UF, ou null. Campo opcional. */
export const problemaNaUf = (valor: string): string | null => {
  const limpo = valor.trim().toUpperCase();
  if (limpo === '') return null;
  // Antes bastavam dois caracteres: "12" e "XX" entravam no cadastro.
  if (!UFS.includes(limpo)) {
    return 'UF inválida — use a sigla de duas letras do estado (ex.: PE).';
  }

  return null;
};

/** Primeiro problema da área de interesse, ou null. Campo opcional. */
export const problemaNaArea = (valor: string): string | null => {
  const limpo = valor.trim();
  if (limpo === '') return null;
  if (/[^A-Za-zÀ-ÖØ-öø-ÿ0-9&,\-/\s]/.test(limpo)) {
    return 'A área de interesse aceita apenas letras, números e os sinais & , - /';
  }

  return null;
};
