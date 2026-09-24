import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import { useState } from 'react';
import { LessonContentEditor } from '../../src/components/instructor/LessonContentEditor';

const CONTEUDO = [
  '## Do Rabisco ao Esqueleto Digital',
  '',
  'Os wireframes servem para validar a estrutura do layout.',
  '',
  '### Tipos de Wireframe',
  '',
  '1. Baixa Fidelidade',
  '2. Média Fidelidade',
  '',
  'Código 1: exemplo:',
  '```java',
  'int a = 1;',
  '```',
].join('\n');

/** Envolve o editor num estado real, como os formulários do instrutor fazem. */
const Harness: React.FC<{
  inicial?: string;
  onUpload?: (file: File) => Promise<{ ok: boolean; url?: string; error?: string }>;
  videoUrlDaAula?: string;
}> = ({ inicial = CONTEUDO, onUpload, videoUrlDaAula }) => {
  const [valor, setValor] = useState(inicial);

  return (
    <>
      <LessonContentEditor value={valor} onChange={setValor} onUpload={onUpload} videoUrlDaAula={videoUrlDaAula} />
      <output data-testid="valor">{valor}</output>
    </>
  );
};

const valor = () => screen.getByTestId('valor').textContent ?? '';

describe('LessonContentEditor', () => {
  it('sobe e desce trecho por trecho, sem seta na ponta', () => {
    render(<Harness />);

    // Primeiro trecho não sobe; último não desce.
    expect(screen.queryByRole('button', { name: 'Subir seção' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Descer bloco de código' })).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Subir bloco de código' }));

    // O código subiu com a legenda, acima da lista numerada.
    const texto = valor();
    expect(texto.indexOf('Código 1: exemplo:')).toBeLessThan(texto.indexOf('1. Baixa Fidelidade'));
    expect(texto.indexOf('Código 1: exemplo:')).toBeGreaterThan(texto.indexOf('### Tipos de Wireframe'));
  });

  it('depois de mover, o foco fica no botão do trecho no lugar novo', () => {
    render(<Harness />);

    fireEvent.click(screen.getByRole('button', { name: 'Descer parágrafo' }));

    expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Descer parágrafo' }));
    expect(valor().indexOf('### Tipos de Wireframe')).toBeLessThan(valor().indexOf('Os wireframes servem'));
  });

  it('avisa, sem mexer no texto, quando a troca mudaria o conteúdo', () => {
    render(<Harness inicial={'Exemplo: veja o fluxo abaixo.\n\nOutro parágrafo.\n\n![fluxo](/uploads/f.png)'} />);
    const antes = valor();

    fireEvent.click(screen.getAllByRole('button', { name: 'Descer parágrafo' })[0]);

    expect(valor()).toBe(antes);
    // `getByText`, não `getByRole('status')`: o <output> do Harness também é status.
    expect(screen.getByText(/não pode ir para lá/i)).toHaveAttribute('role', 'status');
  });

  it('mostra a aula como o aluno vê, num card só, sem área de marcação', () => {
    render(<Harness />);

    expect(screen.getByText('Do Rabisco ao Esqueleto Digital')).toBeInTheDocument();
    expect(screen.getByText('Tipos de Wireframe')).toBeInTheDocument();
    // Nenhum campo aberto: a edição começa pelo lápis do trecho.
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
  });

  it('dá um lápis e uma lixeira para cada trecho', () => {
    render(<Harness />);

    expect(screen.getByRole('button', { name: 'Editar seção' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Editar subtítulo' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Editar parágrafo' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Editar lista numerada' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Editar bloco de código' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Remover seção' })).toBeInTheDocument();
  });

  it('edita a seção por campo de texto simples, sem expor marcação', () => {
    render(<Harness />);
    fireEvent.click(screen.getByRole('button', { name: 'Editar seção' }));

    const campo = screen.getByDisplayValue('Do Rabisco ao Esqueleto Digital');
    // O campo traz o texto puro: nada de "##" para o gestor decifrar.
    expect(campo).toBeInTheDocument();

    fireEvent.change(campo, { target: { value: 'Título revisado' } });
    fireEvent.click(screen.getByRole('button', { name: /aplicar/i }));

    expect(valor()).toContain('## Título revisado');
    expect(valor()).not.toContain('Do Rabisco');
  });

  it('não toca no resto do conteúdo ao salvar um trecho', () => {
    render(<Harness />);
    fireEvent.click(screen.getByRole('button', { name: 'Editar parágrafo' }));
    fireEvent.change(screen.getByDisplayValue(/wireframes servem/), { target: { value: 'Texto novo.' } });
    fireEvent.click(screen.getByRole('button', { name: /aplicar/i }));

    // A indentação do código e as demais linhas permanecem intactas.
    expect(valor()).toContain('```java\nint a = 1;\n```');
    expect(valor()).toContain('## Do Rabisco ao Esqueleto Digital');
    expect(valor()).toContain('### Tipos de Wireframe');
    expect(valor()).toContain('1. Baixa Fidelidade');
  });

  it('edita lista com um item por linha, sem pedir numeração', () => {
    render(<Harness />);
    fireEvent.click(screen.getByRole('button', { name: 'Editar lista numerada' }));

    // getByDisplayValue normaliza espaços; aqui as quebras de linha são o ponto,
    // então pegamos o textarea e checamos o valor cru.
    const campo = screen.getByRole('textbox') as HTMLTextAreaElement;
    expect(campo.value).toBe('Baixa Fidelidade\nMédia Fidelidade');

    fireEvent.change(campo, { target: { value: 'Um\nDois\nTrês' } });
    fireEvent.click(screen.getByRole('button', { name: /aplicar/i }));

    // A numeração é reconstruída pelo editor.
    expect(valor()).toContain('1. Um\n2. Dois\n3. Três');
  });

  it('edita código com linguagem e legenda em campos próprios', () => {
    render(<Harness />);
    fireEvent.click(screen.getByRole('button', { name: 'Editar bloco de código' }));

    expect(screen.getByDisplayValue('Código 1: exemplo:')).toBeInTheDocument();
    fireEvent.change(screen.getByDisplayValue('int a = 1;'), { target: { value: 'int b = 2;' } });
    fireEvent.change(screen.getByRole('combobox'), { target: { value: 'php' } });
    fireEvent.click(screen.getByRole('button', { name: /aplicar/i }));

    expect(valor()).toContain('```php\nint b = 2;\n```');
    expect(valor()).toContain('Código 1: exemplo:');
  });

  it('cancelar edição não altera o conteúdo', () => {
    render(<Harness />);
    fireEvent.click(screen.getByRole('button', { name: 'Editar seção' }));
    fireEvent.change(screen.getByDisplayValue('Do Rabisco ao Esqueleto Digital'), { target: { value: 'descartar' } });
    fireEvent.click(screen.getByRole('button', { name: /cancelar/i }));

    expect(valor()).toBe(CONTEUDO);
  });

  it('remove o trecho pela lixeira', () => {
    render(<Harness />);
    fireEvent.click(screen.getByRole('button', { name: 'Remover subtítulo' }));

    expect(valor()).not.toContain('### Tipos de Wireframe');
    expect(valor()).toContain('## Do Rabisco ao Esqueleto Digital');
  });

  it('insere trecho novo no ponto escolhido', () => {
    render(<Harness />);

    // O primeiro "Adicionar" insere antes de tudo.
    fireEvent.click(screen.getAllByRole('button', { name: /adicionar bloco aqui/i })[0]);
    fireEvent.click(screen.getByRole('button', { name: /^parágrafo$/i }));

    expect(valor()).toContain('Escreva o parágrafo aqui.');
    expect(valor()).toContain('## Do Rabisco ao Esqueleto Digital');
  });

  it('não oferece criar bloco de código: quem edita não é programador', () => {
    render(<Harness />);
    fireEvent.click(screen.getAllByRole('button', { name: /adicionar bloco aqui/i })[0]);

    expect(screen.getByRole('button', { name: /^parágrafo$/i })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^código$/i })).not.toBeInTheDocument();
  });

  it('mas continua editando bloco de código que já existe na aula', () => {
    // Tirar a edição junto tornaria o código das 13 aulas atuais impossível de corrigir.
    render(<Harness />);

    expect(screen.getByRole('button', { name: 'Editar bloco de código' })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Editar bloco de código' }));
    expect(screen.getByDisplayValue('int a = 1;')).toBeInTheDocument();
  });

  it('oferece os tipos de trecho quando a aula está vazia', () => {
    render(<Harness inicial="" />);

    expect(screen.getByText(/ainda não tem material escrito/i)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /^seção$/i }));
    expect(valor()).toBe('## Título da seção');
  });

  it('conta seções e trechos para orientar quem edita', () => {
    render(<Harness />);

    expect(screen.getByText('1 seções')).toBeInTheDocument();
    expect(screen.getByText('5 trechos')).toBeInTheDocument();
    expect(screen.getByText('contém bloco de código')).toBeInTheDocument();
  });

  it('explica o funcionamento em linguagem simples quando pedido', () => {
    render(<Harness />);
    fireEvent.click(screen.getByRole('button', { name: /como funciona/i }));

    expect(screen.getByText(/entre dois trechos, para inserir/i)).toBeInTheDocument();
  });
});

describe('LessonContentEditor — mídia no corpo da aula', () => {
  const PNG = new File(['x'], 'diagrama.png', { type: 'image/png' });

  /** Abre o primeiro menu "+" e escolhe um tipo pelo rótulo. */
  const abrirMenu = (tipo: RegExp) => {
    fireEvent.click(screen.getAllByRole('button', { name: /adicionar/i })[0]);
    fireEvent.click(screen.getByRole('button', { name: tipo }));
  };

  const enviar = (file: File) => {
    const input = document.querySelector('input[type="file"]') as HTMLInputElement;
    fireEvent.change(input, { target: { files: [file] } });
  };

  it('o menu Adicionar oferece Imagem e abre o formulário em vez de inserir texto', () => {
    render(<Harness onUpload={vi.fn(async () => ({ ok: true, url: '/uploads/x.png' }))} />);
    const antes = valor();

    abrirMenu(/^imagem$/i);

    expect(screen.getByText(/adicionar: imagem/i)).toBeInTheDocument();
    // Nada foi inserido ainda: a imagem só existe depois do envio.
    expect(valor()).toBe(antes);
  });

  it('envia o arquivo e grava o bloco de imagem no conteúdo', async () => {
    const onUpload = vi.fn(async () => ({ ok: true, url: '/uploads/novo.png' }));
    render(<Harness onUpload={onUpload} />);

    abrirMenu(/^imagem$/i);
    enviar(PNG);
    await screen.findByText(/imagem enviada/i);

    fireEvent.change(screen.getByPlaceholderText(/hierarquia das classes/i), {
      target: { value: 'Diagrama das classes' },
    });
    fireEvent.click(screen.getByRole('button', { name: /aplicar/i }));

    expect(onUpload).toHaveBeenCalledWith(PNG);
    expect(valor()).toContain('![Diagrama das classes](/uploads/novo.png)');
  });

  it('não deixa aplicar enquanto a descrição estiver vazia', async () => {
    render(<Harness onUpload={vi.fn(async () => ({ ok: true, url: '/uploads/x.png' }))} />);

    abrirMenu(/^imagem$/i);
    enviar(PNG);
    await screen.findByText(/imagem enviada/i);

    expect(screen.getByRole('button', { name: /aplicar/i })).toBeDisabled();
  });

  it('mostra a falha do envio sem perder o que já foi digitado', async () => {
    render(<Harness onUpload={vi.fn(async () => ({ ok: false, error: 'Arquivo excede o tamanho máximo permitido.' }))} />);

    abrirMenu(/^imagem$/i);
    fireEvent.change(screen.getByPlaceholderText(/hierarquia das classes/i), {
      target: { value: 'Minha descrição' },
    });
    enviar(PNG);

    expect(await screen.findByText(/excede o tamanho máximo/i)).toBeInTheDocument();
    expect(screen.getByPlaceholderText(/hierarquia das classes/i)).toHaveValue('Minha descrição');
  });

  it('avisa quando a legenda não começa por Figura, Imagem ou Gráfico', async () => {
    render(<Harness onUpload={vi.fn(async () => ({ ok: true, url: '/uploads/x.png' }))} />);

    abrirMenu(/^imagem$/i);
    enviar(PNG);
    await screen.findByText(/imagem enviada/i);
    fireEvent.change(screen.getByPlaceholderText(/hierarquia das classes/i), { target: { value: 'Um diagrama' } });
    fireEvent.change(screen.getByPlaceholderText(/figura 1/i), { target: { value: 'o mapa do Brasil' } });

    expect(screen.getByText(/comece a legenda por figura/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /aplicar/i })).toBeDisabled();
  });

  it('grava a legenda acima da imagem, para o parser associar as duas', async () => {
    render(<Harness onUpload={vi.fn(async () => ({ ok: true, url: '/uploads/x.png' }))} />);

    abrirMenu(/^imagem$/i);
    enviar(PNG);
    await screen.findByText(/imagem enviada/i);
    fireEvent.change(screen.getByPlaceholderText(/hierarquia das classes/i), { target: { value: 'Um diagrama' } });
    fireEvent.change(screen.getByPlaceholderText(/figura 1/i), { target: { value: 'Figura 1 - o diagrama' } });
    fireEvent.click(screen.getByRole('button', { name: /aplicar/i }));

    expect(valor()).toContain('Figura 1 - o diagrama\n![Um diagrama](/uploads/x.png)');
  });

  it('vídeo do corpo aceita link do YouTube e recusa endereço qualquer', () => {
    render(<Harness />);

    abrirMenu(/^vídeo$/i);
    const campo = screen.getByPlaceholderText(/cole o link do youtube/i);

    fireEvent.change(campo, { target: { value: 'https://exemplo.com/pagina' } });
    expect(screen.getByText(/não reconhecemos esse link/i)).toBeInTheDocument();

    fireEvent.change(campo, { target: { value: 'https://youtu.be/dQw4w9WgXcQ' } });
    expect(screen.queryByText(/não reconhecemos esse link/i)).not.toBeInTheDocument();
  });

  it('sem envio de arquivo disponível, o formulário avisa em vez de travar', () => {
    render(<Harness />);

    abrirMenu(/^imagem$/i);

    expect(screen.getByText(/envio de arquivos não está disponível/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /escolher imagem/i })).toBeDisabled();
  });

  it('o lápis do bloco de imagem abre o formulário preenchido, sem o seletor de Tipo', () => {
    render(<Harness inicial={'Figura 1 - o diagrama\n![Um diagrama](/uploads/x.png)'} />);

    fireEvent.click(screen.getByRole('button', { name: /editar imagem/i }));

    expect(screen.getByDisplayValue('Um diagrama')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Figura 1 - o diagrama')).toBeInTheDocument();
    // Imagem não é conversível: converter em parágrafo jogaria o endereço fora.
    expect(screen.queryByText('Tipo')).not.toBeInTheDocument();
  });

  it('a lixeira remove a imagem e a legenda de uma vez', () => {
    render(<Harness inicial={'Um parágrafo.\n\nFigura 1 - o diagrama\n![Um diagrama](/uploads/x.png)'} />);

    fireEvent.click(screen.getByRole('button', { name: /remover imagem/i }));

    expect(valor()).toBe('Um parágrafo.');
  });
});

/**
 * Conferência do arquivo (Caso 6): nome e miniatura de QUAL imagem subiu.
 * O jsdom não implementa URL.createObjectURL; o stub devolve um endereço
 * previsível por arquivo e o espião em revokeObjectURL trava a liberação de
 * memória, que é o que não pode ser esquecido.
 */
describe('LessonContentEditor — conferência do arquivo enviado', () => {
  const originais = { criar: URL.createObjectURL, liberar: URL.revokeObjectURL };
  const criar = vi.fn((f: Blob) => `blob:${(f as File).name}`);
  const liberar = vi.fn();

  beforeEach(() => {
    criar.mockClear();
    liberar.mockClear();
    URL.createObjectURL = criar as unknown as typeof URL.createObjectURL;
    URL.revokeObjectURL = liberar;
  });

  afterEach(() => {
    // Desmonta ANTES de restaurar: o cleanup global roda depois deste hook, e a
    // limpeza da miniatura no unmount encontraria o revoke original — que no
    // jsdom nem existe.
    cleanup();
    URL.createObjectURL = originais.criar;
    URL.revokeObjectURL = originais.liberar;
  });

  const abrirImagem = () => {
    fireEvent.click(screen.getAllByRole('button', { name: /adicionar/i })[0]);
    fireEvent.click(screen.getByRole('button', { name: /^imagem$/i }));
  };

  const enviar = (file: File) => {
    const input = document.querySelector('input[type="file"]') as HTMLInputElement;
    fireEvent.change(input, { target: { files: [file] } });
  };

  const upload = () => vi.fn(async () => ({ ok: true, url: '/uploads/1790-abc.png' }));

  it('o cartão mostra o nome original do arquivo, não o nome sorteado pelo servidor', async () => {
    render(<Harness onUpload={upload()} />);

    abrirImagem();
    enviar(new File(['x'], 'Evidência.png', { type: 'image/png' }));

    expect(await screen.findByText('Evidência.png')).toBeInTheDocument();
    expect(screen.getByText(/· PNG$/)).toBeInTheDocument();
    expect(screen.queryByText(/1790-abc/)).not.toBeInTheDocument();
  });

  it('a miniatura vem do arquivo local, sem baixar a imagem de novo', async () => {
    render(<Harness onUpload={upload()} />);

    abrirImagem();
    enviar(new File(['x'], 'Evidência.png', { type: 'image/png' }));
    await screen.findByText('Evidência.png');

    expect(screen.getByTestId('miniatura')).toHaveAttribute('src', 'blob:Evidência.png');
    expect(criar).toHaveBeenCalledTimes(1);
  });

  it('trocar a imagem substitui nome e miniatura e libera a miniatura anterior', async () => {
    render(<Harness onUpload={upload()} />);

    abrirImagem();
    enviar(new File(['x'], 'primeira.png', { type: 'image/png' }));
    await screen.findByText('primeira.png');
    enviar(new File(['x'], 'segunda.jpg', { type: 'image/jpeg' }));
    await screen.findByText('segunda.jpg');

    expect(screen.queryByText('primeira.png')).not.toBeInTheDocument();
    expect(screen.getByTestId('miniatura')).toHaveAttribute('src', 'blob:segunda.jpg');
    expect(liberar).toHaveBeenCalledWith('blob:primeira.png');
  });

  it('fechar o formulário libera a miniatura da memória', async () => {
    render(<Harness onUpload={upload()} />);

    abrirImagem();
    enviar(new File(['x'], 'Evidência.png', { type: 'image/png' }));
    await screen.findByText('Evidência.png');
    fireEvent.click(screen.getByRole('button', { name: /cancelar/i }));

    expect(liberar).toHaveBeenCalledWith('blob:Evidência.png');
  });

  it('bloco reaberto pelo lápis mostra a miniatura do endereço gravado, sem nome', () => {
    render(<Harness inicial={'![Um diagrama](/uploads/x.png)'} />);

    fireEvent.click(screen.getByRole('button', { name: /editar imagem/i }));

    expect(screen.getByTestId('miniatura')).toHaveAttribute('src', '/uploads/x.png');
    expect(screen.getByText('Imagem atual da aula')).toBeInTheDocument();
    expect(criar).not.toHaveBeenCalled();
  });

  it('endereço recusado pelo safeUrl não vira miniatura quebrada', () => {
    render(<Harness inicial={'![Inofensiva](javascript:location=name)'} />);

    fireEvent.click(screen.getByRole('button', { name: /editar imagem/i }));

    expect(screen.queryByTestId('miniatura')).not.toBeInTheDocument();
    expect(screen.getByText('Imagem atual da aula')).toBeInTheDocument();
  });

  it('miniatura que falha ao carregar dá lugar ao ícone, sem imagem quebrada', () => {
    render(<Harness inicial={'![Um diagrama](/uploads/sumiu.png)'} />);

    fireEvent.click(screen.getByRole('button', { name: /editar imagem/i }));
    fireEvent.error(screen.getByTestId('miniatura'));

    expect(screen.queryByTestId('miniatura')).not.toBeInTheDocument();
  });

  it('o aviso de imagem pesada continua aparecendo junto do cartão', async () => {
    render(<Harness onUpload={upload()} />);

    abrirImagem();
    enviar(new File([new Uint8Array(3 * 1024 * 1024)], 'grande.png', { type: 'image/png' }));

    expect(await screen.findByText('grande.png')).toBeInTheDocument();
    expect(screen.getByText(/esta imagem é pesada \(3\.0 MB\)/i)).toBeInTheDocument();
  });
});

/**
 * Papéis dos dois vídeos (Caso 5, Fase 4): o do topo é o principal, o do corpo é
 * complemento. A comparação é pela fonte interpretada, não pelo texto do link.
 */
describe('LessonContentEditor — vídeo principal e vídeo complementar', () => {
  const abrirVideo = () => {
    fireEvent.click(screen.getAllByRole('button', { name: /adicionar/i })[0]);
    fireEvent.click(screen.getByRole('button', { name: /^vídeo$/i }));
  };

  const colar = (link: string) => {
    fireEvent.change(screen.getByPlaceholderText(/cole o link do youtube/i), { target: { value: link } });
  };

  it('o formulário do corpo se apresenta como vídeo complementar', () => {
    render(<Harness />);
    abrirVideo();

    expect(screen.getByText(/adicionar: vídeo complementar/i)).toBeInTheDocument();
    expect(screen.getByText(/o vídeo principal da aula fica no campo do topo/i)).toBeInTheDocument();
  });

  it('avisa quando o vídeo do corpo é o mesmo do topo', () => {
    render(<Harness videoUrlDaAula="https://youtu.be/dQw4w9WgXcQ" />);
    abrirVideo();
    colar('https://youtu.be/dQw4w9WgXcQ');

    expect(screen.getByText(/este já é o vídeo principal da aula/i)).toBeInTheDocument();
  });

  it('reconhece o mesmo vídeo mesmo com formato de link diferente', () => {
    render(<Harness videoUrlDaAula="https://www.youtube.com/watch?v=dQw4w9WgXcQ" />);
    abrirVideo();
    colar('https://youtu.be/dQw4w9WgXcQ');

    expect(screen.getByText(/este já é o vídeo principal da aula/i)).toBeInTheDocument();
  });

  it('não avisa quando os vídeos são diferentes', () => {
    render(<Harness videoUrlDaAula="https://youtu.be/dQw4w9WgXcQ" />);
    abrirVideo();
    colar('https://youtu.be/9bZkp7q19f0');

    expect(screen.queryByText(/este já é o vídeo principal da aula/i)).not.toBeInTheDocument();
  });

  it('o aviso de repetição não impede aplicar', () => {
    render(<Harness videoUrlDaAula="https://youtu.be/dQw4w9WgXcQ" />);
    abrirVideo();
    colar('https://youtu.be/dQw4w9WgXcQ');
    fireEvent.change(screen.getByPlaceholderText(/demonstração do cadastro/i), { target: { value: 'Revisão' } });

    expect(screen.getByRole('button', { name: /aplicar/i })).toBeEnabled();
  });

  it('sugere o campo do topo quando a aula ainda não tem vídeo principal', () => {
    render(<Harness videoUrlDaAula="" />);
    abrirVideo();
    colar('https://youtu.be/dQw4w9WgXcQ');

    expect(screen.getByText(/esta aula ainda não tem vídeo principal/i)).toBeInTheDocument();
  });

  it('sem saber o vídeo do topo, não opina', () => {
    render(<Harness />);
    abrirVideo();
    colar('https://youtu.be/dQw4w9WgXcQ');

    expect(screen.queryByText(/este já é o vídeo principal/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/ainda não tem vídeo principal/i)).not.toBeInTheDocument();
  });
});
