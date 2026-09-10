import { describe, it, expect } from 'vitest';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

/*
 * Trava da escada tipográfica (Blocos 3 e 4 do handoff).
 *
 * A varredura mexeu em ~1.600 classes. Sem uma trava, o próximo `text-[10px]`
 * entra sem que ninguém perceba — foi assim que 799 ocorrências de texto entre
 * 9px e 11px se acumularam, incluindo texto de **7,5px**.
 *
 * O teste lê o código-fonte de propósito: o defeito é uma CLASSE escrita no
 * JSX, não um comportamento em tempo de execução. Um teste de render não o
 * pegaria sem montar todas as telas.
 *
 * As superfícies ainda não convertidas estão listadas em `PENDENTES`, com a
 * contagem de hoje. Isso não é exceção silenciosa: se o número CAIR, o teste
 * pede que a lista seja atualizada, e se subir, ele acusa a regressão.
 */

const RAIZ = join(__dirname, '..', '..', 'src');

/** Superfícies já convertidas — nelas o piso vale integralmente. */
const CONVERTIDAS = [
  'App.tsx',
  'components/pages',
  'components/shared',
  'components/StudentDashboard.tsx',
  'components/student',
  'components/InstructorDashboard.tsx',
  'components/instructor',
  'components/LiveClassroom.tsx',
  'components/CourseForum.tsx',
];

/**
 * Superfícies ainda no padrão antigo, com a contagem de hoje.
 *
 * O painel administrativo tem tratamento próprio por decisão do usuário
 * (10/09/2026): pode ganhar melhoria de navegação, mas não troca de cor nem
 * perde função. A tipografia dele entra numa rodada revisável sozinha.
 */
const PENDENTES: Record<string, number> = {
  /*
   * Subiu de 477 para 479 com os grupos e a gaveta do Bloco 6: são dois
   * cabeçalhos de grupo em caixa alta (`uppercase`), que é o único lugar onde o
   * handoff mantém caixa alta. O código novo respeita o piso de 12px — foi este
   * teste que pegou o `text-[11px]` que eu havia escrito aqui.
   */
  'components/AdminDashboard.tsx': 479,
  'components/ProfileView.tsx': 193,
};

/** Remove comentário de bloco e de linha, para não confundir registro com defeito. */
function semComentarios(texto: string): string {
  return texto.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*/g, '');
}

function arquivos(alvo: string): string[] {
  const caminho = join(RAIZ, alvo);
  if (statSync(caminho).isFile()) return [caminho];

  return readdirSync(caminho)
    .filter((n) => n.endsWith('.tsx') || n.endsWith('.ts'))
    .map((n) => join(caminho, n));
}

function ler(alvo: string): { caminho: string; texto: string }[] {
  return arquivos(alvo).map((caminho) => ({ caminho, texto: readFileSync(caminho, 'utf-8') }));
}

/** Tamanhos arbitrários abaixo do piso de 12px, fracionários incluídos. */
function abaixoDoPiso(texto: string): string[] {
  const achados: string[] = texto.match(/text-\[[0-9]+(?:\.[0-9]+)?px\]/g) ?? [];

  return achados.filter((c) => {
    const px = parseFloat(c.slice(6, -3));

    return px < 12;
  });
}

describe('escada tipográfica nas superfícies convertidas', () => {
  it('nenhum texto abaixo do piso de 12px', () => {
    for (const alvo of CONVERTIDAS) {
      for (const { caminho, texto } of ler(alvo)) {
        expect(abaixoDoPiso(texto), caminho).toEqual([]);
      }
    }
  });

  it('nenhum cinza que não alcança contraste em texto', () => {
    /*
     * `slate-400` (#94a3b8) sobre branco dá ~2,6:1, e era a cor do nome do
     * professor e de rótulos. `slate-450`/`slate-500` também não alcançam
     * 4,5:1 nos tamanhos em que apareciam.
     */
    for (const alvo of CONVERTIDAS) {
      for (const { caminho, texto } of ler(alvo)) {
        expect(texto.match(/text-slate-(400|450|500)\b/g) ?? [], caminho).toEqual([]);
      }
    }
  });

  it('nenhum peso 300 em corpo de texto', () => {
    // `font-light` em 10px foi o par que produziu texto ilegível.
    for (const alvo of CONVERTIDAS) {
      for (const { caminho, texto } of ler(alvo)) {
        expect(texto.match(/\bfont-light\b/g) ?? [], caminho).toEqual([]);
      }
    }
  });

  it('a monoespaçada não é usada como enfeite', () => {
    /*
     * Ela aparecia em sobretítulo, número de indicador, rótulo e data — 228
     * vezes —, e em nenhum desses lugares carrega informação.
     *
     * A exceção é UMA, e é verificada abaixo em vez de apenas permitida.
     */
    for (const alvo of CONVERTIDAS) {
      for (const { caminho, texto } of ler(alvo)) {
        if (caminho.endsWith('LessonCodeBlock.tsx')) continue;

        expect(texto.match(/\bfont-mono\b/g) ?? [], caminho).toEqual([]);
      }
    }
  });

  it('a única monoespaçada que fica está no <pre> de código', () => {
    /*
     * Ali a largura fixa carrega informação: mostra a indentação e distingue
     * `l` de `1` de `I`. Este teste existe para a exceção não virar porta
     * aberta — se ela sair do `<pre>`, cai.
     */
    const texto = readFileSync(join(RAIZ, 'components', 'student', 'LessonCodeBlock.tsx'), 'utf-8');
    const usos = texto.match(/\bfont-mono\b/g) ?? [];

    expect(usos).toHaveLength(1);
    // O `<pre>` e a classe com `font-mono` estão na mesma marcação.
    const trecho = texto.slice(texto.indexOf('<pre'), texto.indexOf('</pre>'));
    expect(trecho).toContain('font-mono');
  });
});

describe('classes que não existem no tema', () => {
  it('nada usa `text-3.5xl`', () => {
    /*
     * Estava em três títulos, incluindo o de TODA página institucional. O
     * Tailwind 4 não gera classe para tamanho não declarado no `@theme`:
     * o título ficava em 24px em qualquer largura e nenhum erro aparecia.
     */
    const encontrados: string[] = [];
    const andar = (dir: string) => {
      for (const nome of readdirSync(dir)) {
        const caminho = join(dir, nome);
        if (statSync(caminho).isDirectory()) {
          andar(caminho);
        } else if (nome.endsWith('.tsx') || nome.endsWith('.ts')) {
          // Comentários fora: a menção que explica o defeito não é o defeito.
          const texto = semComentarios(readFileSync(caminho, 'utf-8'));
          if (texto.includes('text-3.5xl')) {
            encontrados.push(caminho);
          }
        }
      }
    };
    andar(RAIZ);

    expect(encontrados).toEqual([]);
  });
});

describe('duas famílias de fonte, não quatro', () => {
  const css = readFileSync(join(RAIZ, 'index.css'), 'utf-8');

  it('Space Grotesk e JetBrains Mono saíram do @import', () => {
    // A Space Grotesk era baixada em toda visita e aplicada a ZERO elementos.
    expect(css).not.toContain('Space+Grotesk');
    expect(css).not.toContain('JetBrains+Mono');
  });

  it('as duas que ficam continuam declaradas', () => {
    expect(css).toContain('Playfair+Display');
    expect(css).toContain('family=Inter');
  });

  it('`--font-mono` aponta para a monoespaçada do sistema', () => {
    // O <pre> de código depende dela; o que saiu foi a webfont, não o token.
    expect(css).toMatch(/--font-mono:\s*ui-monospace/);
  });

  it('não há token de fonte sem consumidor', () => {
    // `--font-display` existia e nenhuma classe o usava.
    expect(css).not.toMatch(/--font-display:/);
  });
});

describe('tokens de cor com papel declarado', () => {
  const css = readFileSync(join(RAIZ, 'index.css'), 'utf-8');

  it('há um neutro para superfície escura', () => {
    /*
     * Os `ink-*` são calibrados para fundo branco: `ink-2` sobre a seção
     * quase preta dá 2,38:1. Sem este token, a varredura piora o contraste
     * justamente onde o fundo é escuro.
     */
    expect(css).toContain('--color-escult-ink-claro');
  });

  it('o vermelho de ação é distinto do vermelho de indicador', () => {
    // Branco sobre #EE4266 dá 3,75:1 — abaixo do piso. #d62f52 dá 4,79:1.
    expect(css).toContain('--color-escult-red-acao: #d62f52');
    expect(css).toContain('--color-escult-red: #EE4266');
  });
});

describe('as superfícies ainda não convertidas', () => {
  it('não regridem enquanto esperam a vez', () => {
    /*
     * A contagem é o retrato de hoje. Se subir, alguém acrescentou texto
     * abaixo do piso; se cair, a superfície começou a ser convertida e a
     * lista precisa acompanhar — nos dois casos é bom o teste avisar.
     */
    for (const [alvo, esperado] of Object.entries(PENDENTES)) {
      const [{ texto }] = ler(alvo);
      const total = (texto.match(/text-\[[0-9]+(?:\.[0-9]+)?px\]|\bfont-mono\b|\buppercase\b/g) ?? []).length;

      expect(total, `${alvo} mudou de ${esperado} para ${total}`).toBe(esperado);
    }
  });
});

describe('estrutura de cabeçalhos', () => {
  it('a página institucional tem um <h1>, e é o título dela', () => {
    /*
     * O portal público inteiro — inicial e as nove institucionais — não tinha
     * NENHUM <h1>: o título de página era um <h3>. Para quem navega por
     * cabeçalhos, o título vinha anunciado como subsubtítulo.
     */
    const shell = readFileSync(join(RAIZ, 'components', 'pages', 'PageShell.tsx'), 'utf-8');

    expect(shell).toMatch(/<h1[^>]*>\s*\{title\}/);
    expect(shell).not.toMatch(/<h3[^>]*>\s*\{title\}/);
  });

  it('a página inicial também tem um <h1>', () => {
    const app = readFileSync(join(RAIZ, 'App.tsx'), 'utf-8');

    expect(app).toMatch(/<h1[^>]*font-serif/);
  });
});
