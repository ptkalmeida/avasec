import { describe, it, expect } from 'vitest';
import { assuntoDaMensagem, comAssuntoDaAula } from '../../src/utils/assuntoMensagem';

describe('marcar a aula de origem', () => {
  it('ida e volta: o gestor recebe a aula separada do corpo', () => {
    /*
     * O pedido: quando o aluno tira dúvida de dentro de uma aula, o gestor tem
     * de ver DE QUAL aula. Sem isso ele precisa perguntar de volta, e a dúvida
     * espera um turno inteiro.
     */
    const marcada = comAssuntoDaAula('NodeJS Express: APIs e Rotas REST', 'não entendi o middleware');

    expect(assuntoDaMensagem(marcada)).toEqual({
      aula: 'NodeJS Express: APIs e Rotas REST',
      corpo: 'não entendi o middleware',
    });
  });

  it('sem título, não inventa marcação', () => {
    expect(comAssuntoDaAula('', 'dúvida geral')).toBe('dúvida geral');
    expect(comAssuntoDaAula('   ', 'dúvida geral')).toBe('dúvida geral');
  });
});

describe('leitura conservadora', () => {
  it('mensagem comum passa intacta', () => {
    expect(assuntoDaMensagem('bom dia, professor')).toEqual({
      aula: null,
      corpo: 'bom dia, professor',
    });
  });

  it('colchete NO MEIO não é confundido com referência a aula', () => {
    /*
     * O risco de ler marcação com folga: uma resposta do gestor citando
     * `array[0]` viraria "dúvida sobre a aula 0". O prefixo só vale no início.
     */
    const texto = 'veja o exemplo [Aula: 2] que passei no material';

    expect(assuntoDaMensagem(texto)).toEqual({ aula: null, corpo: texto });
  });

  it('prefixo vazio não vira rótulo', () => {
    expect(assuntoDaMensagem('[Aula: ] esqueci de dizer')).toEqual({
      aula: null,
      corpo: 'esqueci de dizer',
    });
  });

  it('título com colchete trunca o rótulo mas não perde o corpo', () => {
    // Limite conhecido e aceito: o rótulo sai cortado, a dúvida continua legível.
    const r = assuntoDaMensagem('[Aula: Arrays [avançado]] como indexar?');

    expect(r.aula).toBe('Arrays [avançado');
    expect(r.corpo).toBe('] como indexar?');
  });

  it('entrada ausente não quebra a tela', () => {
    expect(assuntoDaMensagem(null)).toEqual({ aula: null, corpo: '' });
    expect(assuntoDaMensagem(undefined)).toEqual({ aula: null, corpo: '' });
    expect(assuntoDaMensagem('')).toEqual({ aula: null, corpo: '' });
  });

  it('aceita a variação sem espaço depois dos dois-pontos', () => {
    expect(assuntoDaMensagem('[Aula:Aula 1] oi')).toEqual({ aula: 'Aula 1', corpo: 'oi' });
  });
});
