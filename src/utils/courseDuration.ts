/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * Tempo total de uma grade, somado a partir das aulas.
 *
 * O card "Tempo Total Estimado" do painel do instrutor mostrava **`~12.4 horas`
 * escrito literalmente no JSX**. Curso vazio, recém-criado, anunciava 12,4 horas;
 * acrescentar uma aula não mudava nada. É a mesma família do painel "Métricas
 * AVA" que já saiu do ar por indicador inventado: número que parece medição e não
 * mede.
 *
 * `Lesson.duration` é TEXTO livre digitado por quem cadastra a aula (`"15 min"`),
 * não um número de minutos. Somar isso exige interpretar, e interpretar texto
 * livre falha — por isso o resultado separa o que foi somado do que não deu para
 * ler, em vez de tratar aula ilegível como aula de zero minuto. Uma grade com
 * metade das durações em branco não pode anunciar um total como se fosse completo.
 */

/** Minutos de uma grade, e quantas aulas não tiveram duração legível. */
export interface TempoDaGrade {
  minutos: number;
  /** Aulas cuja duração não pôde ser lida (texto vazio, livre ou inválido). */
  semDuracao: number;
  /** Aulas consideradas no total. */
  contadas: number;
}

/**
 * Minutos de um texto de duração, ou null quando não há como saber.
 *
 * Aceita o formato usado no cadastro (`"15 min"`, `"90"`) e as variações que
 * aparecem quando alguém digita à mão (`"1h"`, `"1h30"`, `"1 hora 30"`). Devolve
 * null — e não 0 — para o que não reconhece: zero somaria silenciosamente, e um
 * total errado é pior que um total que se declara incompleto.
 */
export function minutosDaDuracao(texto: string | null | undefined): number | null {
  if (typeof texto !== 'string') return null;

  const limpo = texto.trim().toLowerCase().replace(',', '.');
  if (limpo === '') return null;

  // "1h30", "1 h 30", "1hora30", "2 horas"
  const comHora = limpo.match(/^(\d+(?:\.\d+)?)\s*(?:h|hora|horas)\s*(\d+)?\s*(?:min|minuto|minutos)?$/);
  if (comHora) {
    const horas = Number(comHora[1]);
    const minutos = comHora[2] === undefined ? 0 : Number(comHora[2]);
    if (!Number.isFinite(horas) || !Number.isFinite(minutos)) return null;

    return Math.round(horas * 60 + minutos);
  }

  // "15 min", "15min", "15 minutos", "15"
  const soMinutos = limpo.match(/^(\d+(?:\.\d+)?)\s*(?:min|minuto|minutos|m)?$/);
  if (soMinutos) {
    const n = Number(soMinutos[1]);

    return Number.isFinite(n) ? Math.round(n) : null;
  }

  return null;
}

/** Soma a duração das aulas de uma grade. */
export function tempoDaGrade(
  aulas: readonly { duration?: string | null }[] | null | undefined
): TempoDaGrade {
  const lista = aulas ?? [];
  let minutos = 0;
  let semDuracao = 0;
  let contadas = 0;

  for (const aula of lista) {
    const m = minutosDaDuracao(aula.duration);
    if (m === null) {
      semDuracao += 1;
      continue;
    }
    minutos += m;
    contadas += 1;
  }

  return { minutos, semDuracao, contadas };
}

/**
 * Texto do tempo total para a tela.
 *
 * Grade vazia devolve `'—'`, não `'0 horas'`: curso sem aula não tem duração
 * estimada, e anunciar zero horas como se fosse uma medida é o mesmo vício do
 * número fixo, só com outro valor.
 */
export function textoDoTempoDaGrade(
  aulas: readonly { duration?: string | null }[] | null | undefined
): string {
  const { minutos, semDuracao, contadas } = tempoDaGrade(aulas);

  if (contadas === 0) return '—';

  const base = minutos < 60
    ? `${minutos} min`
    // Uma decimal, e sem `~`: o total é a soma exata do que está cadastrado. O
    // til antigo dava ao número fixo a aparência de estimativa cuidadosa.
    : `${(minutos / 60).toFixed(1).replace('.', ',')} h`;

  return semDuracao === 0
    ? base
    : `${base} (${semDuracao} ${semDuracao === 1 ? 'aula sem duração' : 'aulas sem duração'})`;
}
