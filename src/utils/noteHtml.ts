/**
 * Saneamento do HTML das anotações do aluno.
 *
 * O editor de anotações é contentEditable, então o conteúdo é HTML e precisa ser
 * lido/escrito via innerHTML. Duas consequências:
 *
 * 1. O que está no localStorage volta para dentro do documento. É self-XSS (só a
 *    própria pessoa alimenta o próprio armazenamento), mas a anotação também vira
 *    ARQUIVO EXPORTADO: um .html baixado carrega o script ativo, e aí o arquivo pode
 *    ser aberto ou repassado por outra pessoa.
 * 2. Colar de outra página traz a marcação da origem inteira — inclusive `on*` e
 *    `<script>` — sem a pessoa perceber.
 *
 * A lista é de permissão, não de bloqueio: o editor produz um conjunto pequeno e
 * conhecido de tags (negrito, itálico, listas, títulos), e tudo fora dele é ruído
 * ou risco. Lista de bloqueio erra por omissão a cada tag nova do HTML.
 */

import { safeUrl } from './safeUrl';

const TAGS_PERMITIDAS = new Set([
  'B', 'STRONG', 'I', 'EM', 'U', 'S', 'STRIKE',
  'BR', 'P', 'DIV', 'SPAN',
  'UL', 'OL', 'LI',
  'H1', 'H2', 'H3', 'H4',
  'BLOCKQUOTE', 'CODE', 'PRE',
  'A',
]);

/** Único atributo preservado, e só em <a>, com a URL validada. */
const ATRIBUTO_PERMITIDO = 'href';

/**
 * Devolve o HTML apenas com formatação de texto. Remove script, iframe, style,
 * event handlers, `javascript:` em href e qualquer tag fora da lista — preservando
 * o texto de dentro dela, para a anotação não perder conteúdo escrito.
 */
export function sanitizeNoteHtml(html: string): string {
  if (typeof html !== 'string' || html === '') return '';

  // DOMParser não executa script nem carrega recurso: o documento é inerte.
  const doc = new DOMParser().parseFromString(`<body>${html}</body>`, 'text/html');

  const limpar = (no: Element): void => {
    // Percorre uma cópia: a lista viva muda enquanto substituímos nós.
    for (const filho of Array.from(no.children)) {
      limpar(filho);
    }

    if (!TAGS_PERMITIDAS.has(no.tagName)) {
      // SCRIPT/STYLE/IFRAME saem inteiros — o "texto" deles é código, não conteúdo.
      if (no.tagName === 'SCRIPT' || no.tagName === 'STYLE' || no.tagName === 'IFRAME') {
        no.remove();
        return;
      }
      // Nas demais, mantém o texto e descarta a tag (desembrulha).
      no.replaceWith(...Array.from(no.childNodes));
      return;
    }

    for (const atributo of Array.from(no.attributes)) {
      if (no.tagName === 'A' && atributo.name.toLowerCase() === ATRIBUTO_PERMITIDO) {
        const destino = safeUrl(atributo.value);
        if (destino === null) {
          no.removeAttribute(atributo.name);
        }
        continue;
      }
      // Tudo o mais cai: `style` permite exfiltração por url(), `on*` executa, e
      // atributo de dados não tem uso nenhum numa anotação.
      no.removeAttribute(atributo.name);
    }

    if (no.tagName === 'A') {
      // Link em arquivo baixado ou em aba nova não deve alcançar a janela de origem.
      no.setAttribute('rel', 'noreferrer noopener');
      no.setAttribute('target', '_blank');
    }
  };

  for (const filho of Array.from(doc.body.children)) {
    limpar(filho);
  }

  return doc.body.innerHTML;
}

/**
 * Escapa texto para interpolar em HTML. Necessário no arquivo exportado: o título da
 * aula vem do SERVIDOR (escrito por quem gerencia o curso), então ia sem escape para
 * dentro de <title> e <h1> do .html baixado — isso não é self-XSS, é conteúdo de
 * terceiro executando na máquina do aluno.
 */
export function escapeHtml(texto: string): string {
  return texto
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
