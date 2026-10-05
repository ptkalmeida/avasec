import { describe, it, expect } from 'vitest';
import { canalDeMensagensAberto } from '../../src/utils/canalDeMensagens';
import { abasVisiveisDoAluno } from '../../src/utils/abasAluno';

const LIGADO = { forum: true, mensagensDiretas: true };
const ADMIN_PERMITE = { allowDirectMessages: true };

describe('o sino e a aba passam a ler a mesma condição', () => {
  it('com o fórum desligado, o canal está fechado', () => {
    /*
     * Era o defeito: o sino exigia só `mensagensDiretas`, e a aba exige o trio.
     * Com `forum: false` — a configuração de hoje — havia sino ativo, piscando,
     * levando a uma aba que não existe. E a rolagem procurava
     * `#chat-portal-section`, elemento que só o painel do aluno renderiza.
     */
    expect(canalDeMensagensAberto('instructor', { ...LIGADO, forum: false }, ADMIN_PERMITE)).toBe(false);
    expect(canalDeMensagensAberto('student', { ...LIGADO, forum: false }, ADMIN_PERMITE)).toBe(false);
  });

  it('a chave do administrador fecha o canal para os dois papéis', () => {
    // `allowDirectMessages` é editável nas Configurações do Sistema.
    expect(canalDeMensagensAberto('instructor', LIGADO, { allowDirectMessages: false })).toBe(false);
    expect(canalDeMensagensAberto('student', LIGADO, { allowDirectMessages: false })).toBe(false);
  });

  it('com tudo ligado, o canal está aberto', () => {
    expect(canalDeMensagensAberto('instructor', LIGADO, ADMIN_PERMITE)).toBe(true);
    expect(canalDeMensagensAberto('student', LIGADO, ADMIN_PERMITE)).toBe(true);
  });

  it('o administrador não tem canal — ele não lê conversa de aluno', () => {
    // É a mesma diretriz de privacidade que o rodapé da barra lateral anuncia.
    expect(canalDeMensagensAberto('admin', LIGADO, ADMIN_PERMITE)).toBe(false);
    expect(canalDeMensagensAberto(null, LIGADO, ADMIN_PERMITE)).toBe(false);
    expect(canalDeMensagensAberto(undefined, LIGADO, ADMIN_PERMITE)).toBe(false);
  });

  it('flags ausentes não abrem o canal por omissão', () => {
    expect(canalDeMensagensAberto('student', null, null)).toBe(false);
    expect(canalDeMensagensAberto('student', {}, {})).toBe(false);
    expect(canalDeMensagensAberto('student', { forum: true }, ADMIN_PERMITE)).toBe(false);
  });

  it('a aba de mensagens do aluno concorda com a função, sempre', () => {
    /*
     * Este é o ponto do módulo: as duas telas não podem divergir. Aqui as
     * oito combinações do trio são conferidas de uma vez.
     */
    for (const forum of [true, false]) {
      for (const mensagensDiretas of [true, false]) {
        for (const allowDirectMessages of [true, false]) {
          const features = { forum, mensagensDiretas };
          const ajustes = { allowDirectMessages };
          const aberto = canalDeMensagensAberto('student', features, ajustes);

          expect(
            abasVisiveisDoAluno(features, ajustes).includes('messages'),
            JSON.stringify({ forum, mensagensDiretas, allowDirectMessages })
          ).toBe(aberto);
        }
      }
    }
  });
});
