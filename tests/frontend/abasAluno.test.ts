import { describe, it, expect } from 'vitest';
import {
  abaVisivelParaAluno,
  abasVisiveisDoAluno,
  acoesDoAluno,
  lugaresDoAluno,
} from '../../src/utils/abasAluno';

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

describe('Bloco 6: lugar e ação deixam de ser a mesma coisa', () => {
  const COM_CURSO = { temCursoAtivo: true };

  it('Ajuda e Perfil saem da navegação e viram ação de canto', () => {
    /*
     * "Central de Ajuda / FAQ" estava desenhada como aba e **abre uma gaveta
     * lateral**: prometia trocar de tela e não trocava. E "Meu Perfil" é área
     * pessoal, não seção de estudo.
     */
    const lugares = lugaresDoAluno(TUDO, ADMIN_PERMITE, COM_CURSO).map((l) => l.id);

    expect(lugares).not.toContain('ajuda');
    expect(lugares).not.toContain('perfil');
    expect(acoesDoAluno(TUDO, ADMIN_PERMITE).map((a) => a.id)).toEqual(['ajuda', 'perfil']);
  });

  it('Painel é sempre o primeiro, e não depende de flag', () => {
    expect(lugaresDoAluno(TUDO, ADMIN_PERMITE, COM_CURSO)[0]).toEqual({
      id: 'painel', rotulo: 'Painel', aba: 'general',
    });
    expect(lugaresDoAluno({}, {}, {})[0].id).toBe('painel');
  });

  it('"Meu curso" só aparece com curso ativo', () => {
    // Sem matrícula ativa a entrada não levaria a lugar nenhum.
    expect(lugaresDoAluno(TUDO, ADMIN_PERMITE, COM_CURSO).map((l) => l.id)).toContain('curso');
    expect(lugaresDoAluno(TUDO, ADMIN_PERMITE, { temCursoAtivo: false }).map((l) => l.id)).not.toContain('curso');
    expect(lugaresDoAluno(TUDO, ADMIN_PERMITE, null).map((l) => l.id)).not.toContain('curso');
  });

  it('Certificados é um lugar da navegação, e não um card escondido', () => {
    /*
     * Antes: `/aluno/certificados` era rota real, tinha rótulo na trilha e
     * nenhuma tela — caía no bloco "esta seção não está disponível", porque
     * `certificates` não está entre as abas visíveis.
     */
    expect(lugaresDoAluno(TUDO, ADMIN_PERMITE, COM_CURSO).map((l) => l.id)).toContain('certificados');
    expect(abaVisivelParaAluno('certificates', TUDO, ADMIN_PERMITE)).toBe(false);
  });

  it('com a flag de certificados desligada, o lugar não aparece', () => {
    const lugares = lugaresDoAluno({ ...TUDO, certificados: false }, ADMIN_PERMITE, COM_CURSO);

    expect(lugares.map((l) => l.id)).not.toContain('certificados');
  });

  it('Biblioteca continua alcançável — ela está LIGADA hoje', () => {
    /*
     * O handoff lista a navegação como "Painel · Meu curso · Certificados", o
     * que deixaria Biblioteca de fora. `materiaisComplementares` é `true`:
     * tirar a entrada sem mover a tela removeria acesso a recurso no ar.
     */
    expect(lugaresDoAluno(TUDO, ADMIN_PERMITE, COM_CURSO).map((l) => l.id)).toContain('biblioteca');
    expect(lugaresDoAluno({ ...TUDO, materiaisComplementares: false }, ADMIN_PERMITE, COM_CURSO)
      .map((l) => l.id)).not.toContain('biblioteca');
  });

  it('cada lugar de aba aponta para a aba certa', () => {
    const porId = Object.fromEntries(
      lugaresDoAluno(TUDO, ADMIN_PERMITE, COM_CURSO).map((l) => [l.id, l.aba])
    );

    expect(porId.painel).toBe('general');
    expect(porId.documentos).toBe('documents');
    expect(porId.biblioteca).toBe('library');
    expect(porId.mensagens).toBe('messages');
    expect(porId.eventos).toBe('events');
    // Curso e certificados não são abas do painel.
    expect(porId.curso).toBeUndefined();
    expect(porId.certificados).toBeUndefined();
  });

  it('a navegação não oscila entre três e sete itens por causa de mensagens', () => {
    /*
     * Era o sintoma do Bloco 0 visto de outro ângulo: a chave do administrador
     * mexia na barra inteira. Aqui ela mexe em UM item.
     */
    const com = lugaresDoAluno(TUDO, ADMIN_PERMITE, COM_CURSO).map((l) => l.id);
    const sem = lugaresDoAluno(TUDO, { allowDirectMessages: false }, COM_CURSO).map((l) => l.id);

    expect(com.length - sem.length).toBe(1);
    expect(sem).not.toContain('mensagens');
  });

  it('Ajuda sobrevive a tudo desligado', () => {
    // Quem não encontra o caminho precisa de ajuda justamente aí.
    expect(acoesDoAluno({}, {}).map((a) => a.id)).toEqual(['ajuda']);
    expect(acoesDoAluno(null, null).map((a) => a.id)).toEqual(['ajuda']);
  });

  it('nenhum rótulo repetido entre navegação e canto', () => {
    const rotulos = [
      ...lugaresDoAluno(TUDO, ADMIN_PERMITE, COM_CURSO).map((l) => l.rotulo),
      ...acoesDoAluno(TUDO, ADMIN_PERMITE).map((a) => a.rotulo),
    ];

    expect(new Set(rotulos).size).toBe(rotulos.length);
  });
});
