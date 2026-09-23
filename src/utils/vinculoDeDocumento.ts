/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Estado do envio do arquivo escolhido no painel de material de apoio.
 *
 * Existe porque o painel preenchia título, tamanho e tipo NA HORA, a partir do
 * arquivo, e o formulário parecia pronto enquanto o envio ainda corria — ou
 * depois de ele falhar. Sem distinguir esses momentos, o botão respondia com uma
 * mensagem única que pedia exatamente o que o autor já tinha feito.
 */
export type EnvioDeArquivo =
  | { estado: 'nenhum' }
  | { estado: 'enviando' }
  | { estado: 'enviado' }
  | { estado: 'falhou'; erro: string };

export interface RascunhoDeDocumento {
  titulo: string;
  url: string;
  envio: EnvioDeArquivo;
}

/**
 * Por que o documento ainda não pode ser vinculado — ou `null` quando pode.
 *
 * A ordem importa: o estado do envio vem antes dos campos, porque título e
 * tamanho já aparecem preenchidos pelo arquivo, e "preencha o título" seria a
 * resposta errada para quem está esperando o upload ou acabou de vê-lo falhar.
 */
export function motivoParaNaoVincular(rascunho: RascunhoDeDocumento): string | null {
  const { envio } = rascunho;

  if (envio.estado === 'enviando') return 'Aguarde: o arquivo ainda está sendo enviado.';
  if (envio.estado === 'falhou') {
    return `O envio do arquivo falhou: ${envio.erro} Tente de novo ou escolha outro arquivo.`;
  }
  if (rascunho.titulo.trim() === '') return 'Dê um título ao recurso.';
  if (rascunho.url.trim() === '') return 'Informe o endereço do conteúdo ou selecione um arquivo.';

  return null;
}

/**
 * O que o campo de endereço mostra quando há arquivo escolhido. Antes era sempre
 * "Arquivo carregado localmente" — inclusive com o envio falho, o que dizia ao
 * autor que estava tudo certo.
 */
export function textoDoEnvio(envio: EnvioDeArquivo): string {
  switch (envio.estado) {
    case 'enviando':
      return 'Enviando arquivo...';
    case 'enviado':
      return 'Arquivo enviado ao servidor';
    case 'falhou':
      return 'O envio do arquivo falhou';
    case 'nenhum':
      return 'Nenhum arquivo enviado';
  }
}
