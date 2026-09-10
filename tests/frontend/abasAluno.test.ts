import { describe, it, expect } from 'vitest';
import { abaVisivelParaAluno, abasVisiveisDoAluno } from '../../src/utils/abasAluno';

// Tudo ligado, que é a configuração de hoje em src/config/features.ts.
const TUDO = {
  solicitacoesAcademicas: true,
  forum: true,
  mensagensDiretas: true,
  materiaisComplementares: true,
  eventosWebinars: true,
  perfilBasico: true,
};
const ADMIN_PERMITE = { allowDirectMessages: true };

describe('desligar mensagens NÃO derruba a navegação do aluno', () => {
  it('a flag de mensagens desligada tira SÓ a aba de mensagens', () => {
    /*
     * O defeito que estava no ar: a barra de abas inteira vivia dentro de
     * `features.mensagensDiretas && systemSettings.allowDirectMessages`. O aluno
     * perdia Documentos, Biblioteca, Eventos, Ajuda e Perfil de uma vez.
     */
    const abas = abasVisiveisDoAluno({ ...TUDO, mensagensDiretas: false }, ADMIN_PERMITE);

    expect(abas).not.toContain('messages');
    expect(abas).toEqual(['general', 'documents', 'library', 'events', 'faq', 'settings']);
  });

  it('o ADMIN desligando mensagens nas Configurações também tira só a aba', () => {
    /*
     * Este é o caminho que tornava o defeito alcançável hoje, sem deploy:
     * `allowDirectMessages` é chave editável na tela de Configurações do
     * Sistema. Um clique do administrador apagava a navegação do aluno.
     */
    const abas = abasVisiveisDoAluno(TUDO, { allowDirectMessages: false });

    expect(abas).not.toContain('messages');
    expect(abas).toContain('documents');
    expect(abas).toContain('library');
    expect(abas).toContain('events');
    expect(abas).toContain('faq');
    expect(abas).toContain('settings');
  });

  it('nenhuma aba além de Mensagens depende de mensagens', () => {
    // Varre as duas chaves e confere que só `messages` reage a elas.
    const semMensagem = ['general', 'documents', 'library', 'events', 'faq', 'settings'] as const;

    for (const combinacao of [
      { f: { mensagensDiretas: false }, s: { allowDirectMessages: true } },
      { f: { mensagensDiretas: true }, s: { allowDirectMessages: false } },
      { f: { mensagensDiretas: false }, s: { allowDirectMessages: false } },
      { f: { forum: false }, s: { allowDirectMessages: true } },
    ]) {
      const abas = abasVisiveisDoAluno({ ...TUDO, ...combinacao.f }, combinacao.s);
      expect(abas).not.toContain('messages');
      for (const aba of semMensagem) {
        expect(abas, JSON.stringify(combinacao)).toContain(aba);
      }
    }
  });
});

describe('mensagens exige as duas autorizações', () => {
  it('só aparece com flag de produto E chave do admin', () => {
    expect(abasVisiveisDoAluno(TUDO, ADMIN_PERMITE)).toContain('messages');
    expect(abaVisivelParaAluno('messages', TUDO, ADMIN_PERMITE)).toBe(true);
    expect(abaVisivelParaAluno('messages', { ...TUDO, forum: false }, ADMIN_PERMITE)).toBe(false);
  });

  it('ausência de valor é tratada como desligado, não como ligado', () => {
    /*
     * Configuração que ainda não carregou não pode abrir um canal. Ligar por
     * omissão é o erro que faz uma tela aparecer por meio segundo antes de
     * desaparecer — e, num canal de mensagens, é pior que isso.
     */
    expect(abasVisiveisDoAluno(TUDO, {})).not.toContain('messages');
    expect(abasVisiveisDoAluno(TUDO, null)).not.toContain('messages');
    expect(abasVisiveisDoAluno({}, ADMIN_PERMITE)).not.toContain('messages');
  });
});

describe('a barra nunca fica vazia', () => {
  it('sem flag nenhuma, o aluno ainda tem o painel e a ajuda', () => {
    // `general` e `faq` não têm flag: é decisão, e não esquecimento.
    expect(abasVisiveisDoAluno({}, {})).toEqual(['general', 'faq']);
    expect(abasVisiveisDoAluno(null, null)).toEqual(['general', 'faq']);
  });

  it('a ordem é estável', () => {
    // A barra não pode reordenar entre renders: a pessoa aprende a posição.
    expect(abasVisiveisDoAluno(TUDO, ADMIN_PERMITE)).toEqual([
      'general', 'documents', 'messages', 'library', 'events', 'faq', 'settings',
    ]);
  });
});
