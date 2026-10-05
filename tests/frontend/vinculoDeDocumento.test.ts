import { describe, it, expect } from 'vitest';
import { motivoParaNaoVincular, textoDoEnvio, EnvioDeArquivo } from '../../src/utils/vinculoDeDocumento';

const rascunho = (envio: EnvioDeArquivo, titulo = 'Evidência', url = '') => ({ titulo, url, envio });

describe('motivoParaNaoVincular', () => {
  it('libera o vínculo quando o arquivo subiu e há título e endereço', () => {
    expect(motivoParaNaoVincular(rascunho({ estado: 'enviado' }, 'Evidência', '/uploads/x.png'))).toBeNull();
  });

  it('libera o vínculo de link manual, sem arquivo', () => {
    expect(motivoParaNaoVincular(rascunho({ estado: 'nenhum' }, 'Apostila', 'https://exemplo.com/a.pdf'))).toBeNull();
  });

  // O caso relatado: título e tamanho já preenchidos pelo arquivo, envio falhou,
  // e a mensagem antiga pedia para "preencher o título e link".
  it('envio que falhou é dito como falha, não como campo vazio', () => {
    const motivo = motivoParaNaoVincular(
      rascunho({ estado: 'falhou', erro: 'Arquivo excede o tamanho máximo permitido.' }),
    );

    expect(motivo).toContain('O envio do arquivo falhou');
    expect(motivo).toContain('Arquivo excede o tamanho máximo permitido.');
    expect(motivo).not.toMatch(/preencha|título/i);
  });

  it('pede para aguardar enquanto o arquivo ainda está subindo', () => {
    expect(motivoParaNaoVincular(rascunho({ estado: 'enviando' }))).toMatch(/aguarde/i);
  });

  it('o estado do envio vence os campos, mesmo com título vazio', () => {
    expect(motivoParaNaoVincular(rascunho({ estado: 'enviando' }, ''))).toMatch(/aguarde/i);
  });

  it('só pede o título quando é mesmo o título que falta', () => {
    expect(motivoParaNaoVincular(rascunho({ estado: 'nenhum' }, '   ', 'https://exemplo.com'))).toBe('Dê um título ao recurso.');
  });

  it('só pede o endereço quando é mesmo o endereço que falta', () => {
    expect(motivoParaNaoVincular(rascunho({ estado: 'nenhum' }, 'Apostila', ''))).toMatch(/informe o endereço/i);
  });
});

describe('textoDoEnvio', () => {
  // O campo dizia "Arquivo carregado localmente" até com o envio falho.
  it('o campo de endereço nunca diz que deu certo quando o envio falhou', () => {
    const texto = textoDoEnvio({ estado: 'falhou', erro: 'qualquer' });

    expect(texto).toMatch(/falhou/i);
    expect(texto).not.toMatch(/carregado|enviado ao servidor/i);
  });

  it('distingue enviando de enviado', () => {
    expect(textoDoEnvio({ estado: 'enviando' })).toMatch(/enviando/i);
    expect(textoDoEnvio({ estado: 'enviado' })).toMatch(/enviado ao servidor/i);
  });
});
