/**
 * URL segura para usar em href/src. Espelho de `App\Support\SafeUrl` (PHP) —
 * casos de teste devem manter paridade, como em videoSource.ts.
 *
 * Por que existe nos dois lados: a validação no servidor barra o que entra de agora
 * em diante, mas os cursos e itens de biblioteca já gravados nunca passaram por ela.
 * Um `javascript:` que já esteja no banco continuaria chegando ao `href` do aluno.
 * A checagem no cliente é a barreira para o dado que já existe.
 *
 * Aceita: http, https e caminho relativo começando em "/" (uploads do sistema).
 * Recusa: todo outro esquema (javascript:, data:, vbscript:, file:, blob:) e URL sem host.
 */

const MAX_LENGTH = 2000;

/** Devolve a URL se for segura para href/src, senão `null`. */
export function safeUrl(url: string | null | undefined): string | null {
  if (typeof url !== 'string') return null;

  const bruta = url.trim();
  if (bruta === '' || bruta.length > MAX_LENGTH) return null;

  // Espaço em branco é ignorado pelo navegador dentro do esquema: "java\tscript:alert(1)"
  // executa. Validar a string crua deixaria passar exatamente esse caso.
  const normalizada = bruta.replace(/[\s\u0000]+/g, '');
  if (normalizada === '') return null;

  if (normalizada.startsWith('/')) {
    return normalizada.includes('..') ? null : bruta;
  }

  const esquema = /^([A-Za-z][A-Za-z0-9+.-]*):/.exec(normalizada);
  if (esquema === null) return null;

  const protocolo = esquema[1].toLowerCase();
  if (protocolo !== 'http' && protocolo !== 'https') return null;

  // Autoridade vazia ("http:///caminho") é recusada de propósito: o parser do
  // navegador a interpreta como host "caminho", enquanto o parse_url do PHP devolve
  // host nulo e recusa. Sem esta linha os dois lados discordariam, e paridade que
  // depende de peculiaridade de parser é paridade que quebra sem aviso.
  if (/^[A-Za-z][A-Za-z0-9+.-]*:\/\/\//.test(normalizada)) return null;

  try {
    const parsed = new URL(normalizada);
    return parsed.host === '' ? null : bruta;
  } catch {
    return null;
  }
}

/**
 * Versão para usar direto no JSX. Devolve `undefined` quando a URL é recusada, para
 * o atributo simplesmente não ser emitido — `href="#"` daria ao usuário um link que
 * parece funcionar e não vai a lugar nenhum.
 */
export function safeHref(url: string | null | undefined): string | undefined {
  return safeUrl(url) ?? undefined;
}
