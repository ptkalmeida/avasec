import { describe, it, expect } from 'vitest';
import {
  parseLessonContent,
  serializeLessonBlock,
  replaceLessonBlock,
  removeLessonBlock,
  insertLessonBlockAt,
  ehLegenda,
  moveLessonBlock,
} from '../../src/utils/lessonContent';

describe('parseLessonContent', () => {
  it('trata conteúdo vazio sem quebrar', () => {
    const r = parseLessonContent('');
    expect(r.blocks).toEqual([]);
    expect(r.sections).toEqual([]);
    expect(r.hasCode).toBe(false);
  });

  it('numera seções (##) e mantém subtítulos (###) sem número', () => {
    const r = parseLessonContent('## Primeira\n\n### Detalhe\n\n## Segunda');

    expect(r.sections).toEqual([
      { id: 'primeira', text: 'Primeira', level: 2, index: 1 },
      { id: 'detalhe', text: 'Detalhe', level: 3 },
      { id: 'segunda', text: 'Segunda', level: 2, index: 2 },
    ]);
  });

  it('gera slug sem acento para ancorar o índice', () => {
    const r = parseLessonContent('## Herança e Instanciação');
    expect(r.sections[0].id).toBe('heranca-e-instanciacao');
  });

  it('mantém compatibilidade: conteúdo antigo só com ### continua renderizando', () => {
    const r = parseLessonContent('### O que é UX Design?\nUX trata da experiência.');

    expect(r.blocks[0]).toMatchObject({ kind: 'subsection', id: 'o-que-e-ux-design', text: 'O que é UX Design?' });
    expect(r.blocks[1]).toMatchObject({ kind: 'paragraph', text: 'UX trata da experiência.' });
  });

  it('extrai bloco de código com linguagem e preserva a indentação', () => {
    const r = parseLessonContent('```java\npublic class Pessoa {\n    private String nome;\n}\n```');

    expect(r.hasCode).toBe(true);
    expect(r.blocks).toHaveLength(1);
    expect(r.blocks[0]).toMatchObject({
      kind: 'code',
      language: 'java',
      caption: null,
      code: 'public class Pessoa {\n    private String nome;\n}',
    });
  });

  it('aceita bloco de código sem linguagem declarada', () => {
    const r = parseLessonContent('```\nsem realce\n```');
    expect(r.blocks[0]).toMatchObject({ kind: 'code', language: null });
  });

  it('associa a legenda ao bloco de código seguinte', () => {
    const r = parseLessonContent('Código 5: Método atualizarID:\n```java\nvoid x() {}\n```');

    expect(r.blocks).toHaveLength(1);
    expect(r.blocks[0]).toMatchObject({
      kind: 'code',
      caption: 'Código 5: Método atualizarID:',
    });
  });

  it('legenda sem código depois vira parágrafo comum', () => {
    const r = parseLessonContent('Código 5: isso nunca virou bloco.\n\n## Fim');

    expect(r.blocks[0]).toMatchObject({ kind: 'paragraph', text: 'Código 5: isso nunca virou bloco.' });
  });

  it('separa lista numerada de lista com marcador', () => {
    const r = parseLessonContent('1. um\n2. dois\n\n- alfa\n- beta');

    expect(r.blocks).toHaveLength(2);
    expect(r.blocks[0]).toMatchObject({ kind: 'orderedList', items: ['um', 'dois'] });
    expect(r.blocks[1]).toMatchObject({ kind: 'bulletList', items: ['alfa', 'beta'] });
  });

  it('não confunde ### com ##', () => {
    const r = parseLessonContent('### Sub');
    expect(r.sections[0].level).toBe(3);
  });

  it('fecha o bloco de código mesmo sem cerca final', () => {
    const r = parseLessonContent('```java\nint a = 1;');
    expect(r.blocks[0]).toMatchObject({ kind: 'code', code: 'int a = 1;' });
  });
});

describe('ranges dos blocos', () => {
  const CONTEUDO = [
    '## Primeira seção',
    '',
    'Um parágrafo qualquer.',
    '',
    '1. um',
    '2. dois',
    '',
    'Código 1: exemplo:',
    '```java',
    'int a = 1;',
    '```',
    '',
    'Figura 1 - o diagrama',
    '![Diagrama das classes](/uploads/diagrama.png)',
    '',
    '@[Demonstração](https://youtu.be/abc123)',
    '',
    '### Fim',
  ].join('\n');

  it('cada range aponta exatamente para o trecho que gerou o bloco', () => {
    const r = parseLessonContent(CONTEUDO);

    // O recorte pelo range tem de reproduzir o bloco serializado — é essa
    // igualdade que permite editar UM bloco sem reescrever o resto do texto.
    for (const bloco of r.blocks) {
      expect(CONTEUDO.slice(bloco.range.start, bloco.range.end)).toBe(serializeLessonBlock(bloco));
    }
  });

  it('o range do bloco de código inclui a legenda', () => {
    const r = parseLessonContent(CONTEUDO);
    const code = r.blocks.find((b) => b.kind === 'code')!;
    const recorte = CONTEUDO.slice(code.range.start, code.range.end);

    expect(recorte).toContain('Código 1: exemplo:');
    expect(recorte).toContain('int a = 1;');
  });

  it('troca um bloco sem tocar no restante do conteúdo', () => {
    const r = parseLessonContent(CONTEUDO);
    const paragrafo = r.blocks.find((b) => b.kind === 'paragraph')!;

    const novo = replaceLessonBlock(CONTEUDO, paragrafo.range, 'Texto trocado.');

    expect(novo).toContain('Texto trocado.');
    expect(novo).not.toContain('Um parágrafo qualquer.');
    expect(novo).toContain('## Primeira seção');
    expect(novo).toContain('```java');
    expect(novo).toContain('### Fim');
  });

  it('remove o bloco sem deixar buraco de linhas em branco', () => {
    const r = parseLessonContent(CONTEUDO);
    const lista = r.blocks.find((b) => b.kind === 'orderedList')!;

    const novo = removeLessonBlock(CONTEUDO, lista.range);

    expect(novo).not.toContain('1. um');
    expect(novo).not.toMatch(/\n{3,}/);
    expect(parseLessonContent(novo).blocks).toHaveLength(r.blocks.length - 1);
  });

  it('insere bloco novo depois do indicado', () => {
    const r = parseLessonContent(CONTEUDO);

    const novo = insertLessonBlockAt(CONTEUDO, r.blocks[0].range.end, '### Recém-criado');

    expect(parseLessonContent(novo).blocks[1]).toMatchObject({ kind: 'subsection', text: 'Recém-criado' });
  });

  it('primeiro bloco de conteúdo vazio não vem com linhas sobrando', () => {
    expect(insertLessonBlockAt('', 0, '## Começo')).toBe('## Começo');
  });

  // O menu "+" do topo do editor pede posição 0. A versão anterior mandava um
  // sentinela que esta função lia como "no fim", e o trecho escolhido no começo
  // da aula ia parar no rodapé — caminho que nenhum teste cobria.
  it('insere no COMEÇO quando a posição é 0 e já existe conteúdo', () => {
    const novo = insertLessonBlockAt(CONTEUDO, 0, '## Abertura');
    const blocos = parseLessonContent(novo).blocks;

    expect(blocos[0]).toMatchObject({ kind: 'section', text: 'Abertura' });
    expect(blocos).toHaveLength(parseLessonContent(CONTEUDO).blocks.length + 1);
    expect(novo.startsWith('## Abertura')).toBe(true);
    expect(novo).not.toMatch(/\n{3,}/);
  });

  it('insere no FIM quando a posição é o tamanho do conteúdo', () => {
    const novo = insertLessonBlockAt(CONTEUDO, CONTEUDO.length, '## Encerramento');
    const blocos = parseLessonContent(novo).blocks;

    expect(blocos[blocos.length - 1]).toMatchObject({ kind: 'section', text: 'Encerramento' });
    expect(novo).not.toMatch(/\n{3,}/);
  });

  it('posição fora da faixa é limitada, sem estourar nem furar o texto', () => {
    const adiante = insertLessonBlockAt(CONTEUDO, CONTEUDO.length + 5000, '## Depois');
    const atras = insertLessonBlockAt(CONTEUDO, -42, '## Antes');

    expect(parseLessonContent(adiante).blocks.at(-1)).toMatchObject({ kind: 'section', text: 'Depois' });
    expect(parseLessonContent(atras).blocks[0]).toMatchObject({ kind: 'section', text: 'Antes' });
    expect(adiante).not.toMatch(/\n{3,}/);
    expect(atras).not.toMatch(/\n{3,}/);
  });

  it('inserir no meio preserva os blocos vizinhos', () => {
    const r = parseLessonContent(CONTEUDO);
    const antes = r.blocks.length;

    const novo = insertLessonBlockAt(CONTEUDO, r.blocks[1].range.end, '- item avulso');
    const blocos = parseLessonContent(novo).blocks;

    expect(blocos).toHaveLength(antes + 1);
    expect(blocos[2]).toMatchObject({ kind: 'bulletList', items: ['item avulso'] });
    expect(blocos[0]).toMatchObject({ kind: r.blocks[0].kind });
    expect(blocos.at(-1)).toMatchObject({ kind: r.blocks.at(-1)!.kind });
  });
});

/**
 * Conversão de tipo — o editor troca o `kind` reaproveitando o texto já escrito.
 * A regra do editor é "linhas": título vira uma linha só, parágrafo preserva as
 * quebras e lista usa cada linha como item. Estes testes travam a serialização
 * que sustenta essa conversão.
 */
describe('mídia no corpo da aula', () => {
  it('reconhece imagem sozinha na linha, com descrição e endereço', () => {
    const r = parseLessonContent('![Diagrama das classes](/uploads/x.png)');

    expect(r.blocks[0]).toMatchObject({
      kind: 'image', url: '/uploads/x.png', alt: 'Diagrama das classes', caption: null,
    });
  });

  it('reconhece vídeo sozinho na linha sem colidir com a sintaxe de imagem', () => {
    const r = parseLessonContent('@[Demonstração](https://youtu.be/abc123)\n\n![Foto](/uploads/f.png)');

    expect(r.blocks[0]).toMatchObject({ kind: 'video', url: 'https://youtu.be/abc123', title: 'Demonstração' });
    expect(r.blocks[1]).toMatchObject({ kind: 'image', url: '/uploads/f.png' });
  });

  // Descuido de autoria não pode virar marcação crua na tela do aluno: a leitura
  // aceita, e é o editor que exige a descrição de quem escreve.
  it('imagem sem descrição é lida com alt vazio', () => {
    expect(parseLessonContent('![](/uploads/x.png)').blocks[0]).toMatchObject({ kind: 'image', alt: '' });
  });

  it('imagem sem endereço não vira bloco — o autor vê o erro no texto', () => {
    expect(parseLessonContent('![Descrição]()').blocks[0]).toMatchObject({ kind: 'paragraph' });
  });

  it('linha com texto além da imagem continua sendo parágrafo', () => {
    expect(parseLessonContent('Veja ![Foto](/uploads/x.png) aqui').blocks[0]).toMatchObject({ kind: 'paragraph' });
  });

  it('colchete dentro da descrição não quebra o conteúdo: a linha vira parágrafo', () => {
    expect(parseLessonContent('![Figura [1] do livro](/uploads/x.png)').blocks[0]).toMatchObject({ kind: 'paragraph' });
  });

  it('imagem dentro de bloco de código continua sendo código', () => {
    const r = parseLessonContent('```md\n![Foto](/uploads/x.png)\n```');

    expect(r.blocks).toHaveLength(1);
    expect(r.blocks[0]).toMatchObject({ kind: 'code', code: '![Foto](/uploads/x.png)' });
  });

  it('associa a legenda à imagem seguinte', () => {
    const r = parseLessonContent('Figura 1 - o diagrama\n![Diagrama](/uploads/x.png)');

    expect(r.blocks).toHaveLength(1);
    expect(r.blocks[0]).toMatchObject({ kind: 'image', caption: 'Figura 1 - o diagrama' });
  });

  it('legenda de figura sem mídia embaixo continua virando parágrafo', () => {
    const r = parseLessonContent('Figura 1 - sozinha\n\nUm parágrafo.');

    expect(r.blocks[0]).toMatchObject({ kind: 'paragraph', text: 'Figura 1 - sozinha' });
  });

  it('o range da imagem inclui a legenda, para o lápis e a lixeira levarem as duas', () => {
    const conteudo = 'Figura 1 - o diagrama\n![Diagrama](/uploads/x.png)';
    const bloco = parseLessonContent(conteudo).blocks[0];

    expect(conteudo.slice(bloco.range.start, bloco.range.end)).toBe(conteudo);
  });

  it('serializar e reler a mídia devolve o mesmo bloco, com e sem legenda', () => {
    const casos = [
      '![Diagrama](/uploads/x.png)',
      'Figura 2: o fluxo\n![Fluxo](/uploads/y.png)',
      '@[Aula gravada](https://youtu.be/abc123)',
      'Vídeo 1 - a demonstração\n@[Demo](https://youtu.be/abc123)',
    ];

    for (const caso of casos) {
      const bloco = parseLessonContent(caso).blocks[0];
      expect(serializeLessonBlock(bloco)).toBe(caso);
    }
  });

  it('serializar remove colchete da descrição, para a releitura não quebrar', () => {
    const texto = serializeLessonBlock({
      kind: 'image', url: '/uploads/x.png', alt: 'Figura [1] do livro', caption: null,
      range: { start: 0, end: 0 },
    });

    expect(texto).toBe('![Figura 1 do livro](/uploads/x.png)');
    expect(parseLessonContent(texto).blocks[0]).toMatchObject({ kind: 'image', alt: 'Figura 1 do livro' });
  });

  it('remover a imagem tira a legenda junto e não deixa buraco de linhas', () => {
    const conteudo = 'Um parágrafo.\n\nFigura 1 - o diagrama\n![Diagrama](/uploads/x.png)\n\nOutro parágrafo.';
    const imagem = parseLessonContent(conteudo).blocks.find((b) => b.kind === 'image')!;

    const novo = removeLessonBlock(conteudo, imagem.range);

    expect(novo).not.toContain('Figura 1');
    expect(novo).not.toContain('/uploads/x.png');
    expect(novo).not.toMatch(/\n{3,}/);
    expect(parseLessonContent(novo).blocks).toHaveLength(2);
  });

  it('ehLegenda reconhece o padrão e recusa texto solto', () => {
    expect(ehLegenda('Figura 1 - o diagrama')).toBe(true);
    expect(ehLegenda('Imagem 2: o mapa')).toBe(true);
    expect(ehLegenda('Vídeo 3 - a demonstração')).toBe(true);
    expect(ehLegenda('o mapa do Brasil')).toBe(false);
  });
});

describe('conversão de tipo de bloco', () => {
  it('lista numerada de um item vira seção com o mesmo texto', () => {
    const r = parseLessonContent('1. Abertura');
    const lista = r.blocks[0];

    expect(lista).toMatchObject({ kind: 'orderedList', items: ['Abertura'] });

    const convertido = serializeLessonBlock({
      kind: 'section', id: '', index: 0, text: 'Abertura', range: lista.range,
    });

    expect(convertido).toBe('## Abertura');
    expect(parseLessonContent(convertido).blocks[0]).toMatchObject({ kind: 'section', text: 'Abertura' });
  });

  it('seções convertidas numeram 1, 2, 3 mesmo com parágrafos entre elas', () => {
    const conteudo = '## Abertura\n\nUm parágrafo.\n\n## Para que serve\n\nOutro parágrafo.\n\n## Organização';
    const r = parseLessonContent(conteudo);

    expect(r.sections.filter((s) => s.level === 2).map((s) => s.index)).toEqual([1, 2, 3]);
  });

  it('parágrafo vira lista com uma linha por item', () => {
    const convertido = serializeLessonBlock({
      kind: 'bulletList', items: ['primeiro', 'segundo'], range: { start: 0, end: 0 },
    });

    expect(convertido).toBe('- primeiro\n- segundo');
    expect(parseLessonContent(convertido).blocks[0]).toMatchObject({
      kind: 'bulletList', items: ['primeiro', 'segundo'],
    });
  });

  it('texto sobrevive à ida e volta entre subtítulo e lista', () => {
    const range = { start: 0, end: 0 };
    const texto = 'Clareza também conta';

    // subtítulo -> lista
    const comoLista = serializeLessonBlock({ kind: 'orderedList', items: [texto], range });
    const lida = parseLessonContent(comoLista).blocks[0];

    expect(lida).toMatchObject({ kind: 'orderedList', items: [texto] });

    // lista -> subtítulo, juntando as linhas como o editor faz
    const itens = lida.kind === 'orderedList' ? lida.items : [];
    const devolta = serializeLessonBlock({ kind: 'subsection', id: '', text: itens.join(' '), range });

    expect(devolta).toBe(`### ${texto}`);
  });
});

/*
 * Subir e descer trecho por trecho no editor. A troca mexe só no texto dos dois
 * trechos vizinhos; se relida mudaria mais do que a ordem, não acontece.
 */
describe('moveLessonBlock', () => {
  const tipos = (conteudo: string) => parseLessonContent(conteudo).blocks.map((b) => b.kind);

  const AULA = [
    '### Estruturando o seu AVA',
    'A arquitetura de informação organiza os conteúdos.',
    '',
    '- Card Sorting',
    '- Sitemaps',
    '',
    'Figura 1 - Organograma',
    '![organograma](/uploads/org.png)',
  ].join('\n');

  it('sobe a figura, com a legenda junto', () => {
    const novo = moveLessonBlock(AULA, 3, -1);

    expect(tipos(novo)).toEqual(['subsection', 'paragraph', 'image', 'bulletList']);
    const figura = parseLessonContent(novo).blocks[2];
    expect(figura).toMatchObject({ kind: 'image', caption: 'Figura 1 - Organograma', alt: 'organograma' });
  });

  it('desce um trecho', () => {
    expect(tipos(moveLessonBlock(AULA, 0, 1))).toEqual(['paragraph', 'subsection', 'bulletList', 'image']);
  });

  it('trechos colados por uma quebra só não se fundem ao trocar', () => {
    // Título e parágrafo colados por uma quebra só: sem a linha em branco na
    // emenda, o parágrafo acima do título poderia engolir a linha do título.
    const novo = moveLessonBlock(AULA, 0, 1);

    expect(novo.startsWith('A arquitetura de informação organiza os conteúdos.\n\n### Estruturando o seu AVA')).toBe(true);
  });

  it('nas pontas e com índice inválido não faz nada', () => {
    expect(moveLessonBlock(AULA, 0, -1)).toBe(AULA);
    expect(moveLessonBlock(AULA, 3, 1)).toBe(AULA);
    expect(moveLessonBlock(AULA, 9, -1)).toBe(AULA);
    expect(moveLessonBlock(AULA, -1, 1)).toBe(AULA);
    expect(moveLessonBlock('', 0, 1)).toBe('');
  });

  it('não perde texto: subir e descer de volta devolve a mesma aula', () => {
    const idaEVolta = moveLessonBlock(moveLessonBlock(AULA, 2, 1), 3, -1);

    expect(parseLessonContent(idaEVolta).blocks.map(serializeLessonBlock))
      .toEqual(parseLessonContent(AULA).blocks.map(serializeLessonBlock));
  });

  it('seções trocam de lugar e renumeram pela nova ordem', () => {
    const conteudo = '## Primeira\n\nTexto um.\n\n## Segunda';
    const novo = moveLessonBlock(conteudo, 2, -1);

    const secoes = parseLessonContent(novo).sections;
    expect(secoes.map((sec) => [sec.text, sec.index])).toEqual([['Primeira', 1], ['Segunda', 2]]);
    expect(parseLessonContent(novo).blocks.map((b) => b.kind)).toEqual(['section', 'section', 'paragraph']);
  });

  it('recusa a troca que transformaria um parágrafo em legenda da figura', () => {
    // "Exemplo: ..." logo acima de uma figura é lido como legenda dela: o
    // parágrafo sumiria como trecho. Melhor não mover que mudar o texto.
    const conteudo = 'Exemplo: veja o fluxo abaixo.\n\nOutro parágrafo.\n\n![fluxo](/uploads/f.png)';

    expect(moveLessonBlock(conteudo, 0, 1)).toBe(conteudo);
  });
});
