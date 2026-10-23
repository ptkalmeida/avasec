{{--
    `/inst/curso/{ref}/aulas/{id}` — a página de UMA aula.

    Substitui `LessonManagePage` do painel React: as mesmas quatro zonas, na
    ordem em que o aluno as recebe (identificação, vídeo, material didático,
    anexos), cada uma com o lápis que abre o formulário.

    O que muda, de propósito:

    - cada zona grava SÓ a aula que está aberta. O React reenviava o curso inteiro
      (`PUT /api/courses/{id}` com todas as aulas) a cada salvamento, e uma aba
      velha desfazia em silêncio o trabalho de outra pessoa;
    - a zona aberta vem do endereço (`?editar=`), e não de estado no navegador;
    - o selo "Aula obrigatória/opcional" não veio: a coluna `isOptional` não
      existe no banco, e o botão nunca gravou nada.
--}}
@php
    $base = '/inst/curso/'.$ref.'/aulas/'.rawurlencode($aula['id']);
    $temVideo = \App\Support\VideoSource::tryParse($aula['videoUrl'] ?? null) !== null;
    $secoes = count(array_filter($blocos['secoes'], static fn (array $s): bool => $s['nivel'] === 2));
    $campo = 'w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30';
    $salvar = 'cursor-pointer rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2';
@endphp
<x-layouts.instrutor :titulo="$aula['title'].' — '.$curso['title']" ativo="grade" :cursoDoMenu="$curso">

    <div class="mx-auto max-w-4xl space-y-6">

        <nav aria-label="Trilha de navegação" class="text-apoio text-escult-ink-3">
            <a href="/inst" class="underline-offset-[3px] hover:text-ava-acao hover:underline">Meus cursos</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <a href="/inst/curso/{{ $ref }}" class="underline-offset-[3px] hover:text-ava-acao hover:underline">{{ $curso['title'] }}</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <a href="/inst/curso/{{ $ref }}/grade-curricular" class="underline-offset-[3px] hover:text-ava-acao hover:underline">Grade curricular</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <span class="text-ava-tinta">Aula {{ $posicao }}</span>
        </nav>

        @if (session('avisoDoCurso'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-apoio font-semibold text-emerald-800">
                {{ session('avisoDoCurso') }}
            </div>
        @endif

        @if ($errors->has('aula'))
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-apoio font-semibold text-rose-800">
                {{ $errors->first('aula') }}
            </div>
        @endif

        <div class="space-y-1">
            <h1 class="font-titulo text-3xl leading-tight tracking-tight text-ava-tinta break-words">{{ $aula['title'] }}</h1>
            <p class="text-apoio text-escult-ink-2">{{ $curso['title'] }} · aula {{ $posicao }} de {{ $total }}</p>
        </div>

        {{-- 1. Identificação --}}
        <x-instrutor.zona-da-aula id="identificacao" titulo="Identificação da aula" :editando="$editar === 'identificacao'">
            @if ($editar === 'identificacao')
                <form method="POST" action="{{ $base }}/identificacao" class="space-y-4">
                    @csrf
                    @if ($errors->has('identificacao'))
                        <p role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-apoio font-semibold text-rose-800">{{ $errors->first('identificacao') }}</p>
                    @endif
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="min-w-0 space-y-1.5 sm:col-span-2">
                            <label for="titulo" class="block text-rotulo font-semibold text-ava-tinta">Título da aula</label>
                            <input id="titulo" name="titulo" type="text" required maxlength="191" class="{{ $campo }}"
                                   value="{{ old('titulo', $aula['title']) }}">
                        </div>
                        <div class="min-w-0 space-y-1.5">
                            <label for="carga" class="block text-rotulo font-semibold text-ava-tinta">Carga</label>
                            <input id="carga" name="carga" type="text" required maxlength="30" placeholder="20 min" class="{{ $campo }}"
                                   value="{{ old('carga', $aula['duration']) }}">
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="{{ $salvar }}">Salvar identificação</button>
                    </div>
                </form>
            @else
                <p class="font-titulo text-lg leading-tight text-ava-tinta break-words">{{ $aula['title'] }}</p>
                <p class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-apoio text-escult-ink-2">
                    <span>{{ ($aula['duration'] ?? '') !== '' ? $aula['duration'] : 'Sem carga definida' }}</span>
                    <span>{{ count($documentos) === 1 ? '1 documento' : count($documentos).' documentos' }}</span>
                </p>
            @endif
        </x-instrutor.zona-da-aula>

        {{-- 2. Vídeo --}}
        <x-instrutor.zona-da-aula id="video" titulo="Vídeo principal da aula"
                                  :dica="$temVideo ? 'O aluno vê o player no topo da aula.' : 'Sem vídeo: a aula abre como conteúdo de leitura.'"
                                  :editando="$editar === 'video'">
            @if ($editar === 'video')
                <form method="POST" action="{{ $base }}/video" class="space-y-4">
                    @csrf
                    @if ($errors->has('video'))
                        <p role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-apoio font-semibold text-rose-800">{{ $errors->first('video') }}</p>
                    @endif
                    <div class="space-y-1.5">
                        <label for="video-url" class="block text-rotulo font-semibold text-ava-tinta">Link do vídeo no YouTube</label>
                        <p id="dica-video" class="text-apoio text-escult-ink-2">
                            Abra o vídeo no YouTube, copie o endereço da barra do navegador e cole aqui. Deixe em branco para tirar o vídeo da aula.
                            Depois de salvar, o vídeo aparece na página do jeito que o aluno vai ver.
                        </p>
                        <input id="video-url" name="video" type="text" inputmode="url" aria-describedby="dica-video"
                               placeholder="https://youtu.be/…" class="{{ $campo }}"
                               value="{{ old('video', $aula['videoUrl'] ?? '') }}">
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="{{ $salvar }}">Salvar vídeo</button>
                    </div>
                </form>
            @elseif ($temVideo)
                <div class="mx-auto max-w-2xl">
                    <x-aluno.video :url="$aula['videoUrl']" :titulo="$aula['title']" />
                </div>
            @else
                <div class="rounded-xl border border-dashed border-ava-borda bg-ava-faixa/50 p-6 text-center">
                    <p class="text-rotulo font-semibold text-ava-tinta">Aula sem vídeo</p>
                    <p class="mt-0.5 text-apoio text-escult-ink-2">O aluno recebe esta aula como leitura. Use “Editar” para anexar um vídeo.</p>
                </div>
            @endif
        </x-instrutor.zona-da-aula>

        {{-- 3. Material didático --}}
        <x-instrutor.zona-da-aula id="material" titulo="Material didático"
                                  :dica="$secoes.($secoes === 1 ? ' seção' : ' seções').($blocos['temCodigo'] ? ' · contém bloco de código' : '')"
                                  :editando="$editar === 'material'">
            @if ($editar !== 'material')
                <p class="mb-3 text-sobretitulo uppercase text-escult-ink-2">Como o aluno vê</p>
                @if ($blocos['blocos'] === [])
                    <p class="text-apoio italic text-escult-ink-2">Esta aula ainda não tem material escrito. Use “Editar” para começar.</p>
                @else
                    <x-aluno.conteudo-da-aula :blocos="$blocos['blocos']" />
                @endif
            @else
                @php
                    $rotulos = ['secao' => 'Seção', 'subsecao' => 'Subtítulo', 'paragrafo' => 'Parágrafo', 'listaNumerada' => 'Lista numerada', 'listaMarcadores' => 'Lista', 'codigo' => 'Bloco de código', 'imagem' => 'Imagem', 'video' => 'Vídeo'];
                    $palavras = trim($conteudo) === '' ? 0 : count(preg_split('/\s+/u', trim($conteudo)) ?: []);
                    $lista = $blocos['blocos'];
                    $botaoNeutro = 'inline-flex cursor-pointer items-center rounded-[10px] border border-ava-borda bg-white px-3 py-1.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao';
                    $corpo = $base.'/material';
                @endphp

                <div class="space-y-3">
                    <p class="text-apoio text-escult-ink-2">
                        Esta é a tela do aluno. Cada alteração é gravada na hora, só no texto desta aula.
                    </p>

                    <details class="rounded-xl border border-ava-borda bg-ava-faixa/50 p-3 text-apoio text-ava-tinta">
                        <summary class="cursor-pointer font-semibold">Como funciona</summary>
                        <ul class="mt-2 list-disc space-y-1 pl-5 leading-relaxed">
                            <li><strong>Editar</strong> abre o trecho para alteração; <strong>Remover</strong> o tira do texto.</li>
                            <li><strong>Subir</strong> e <strong>Descer</strong> trocam o trecho de lugar. Figura e código levam a legenda junto.</li>
                            <li><strong>+ Adicionar</strong>, entre dois trechos, insere algo novo ali.</li>
                            <li><strong>Seção</strong> numera a parte e cria o índice “Nesta aula”; <strong>Subtítulo</strong> separa assuntos dentro dela.</li>
                            <li>Ao editar um trecho, o campo <strong>Tipo</strong> converte o que já está escrito — uma lista numerada vira Seção sem redigitar. Use isso quando os títulos estiverem saindo todos como “1.”: a Seção numera 1, 2, 3 pela aula inteira.</li>
                            <li><strong>Imagem</strong> exige uma <strong>descrição</strong>: é o que o aluno recebe quando a imagem não carrega, e o que o leitor de tela lê em voz alta. A legenda (“Figura 1 - …”) aparece abaixo da figura.</li>
                            <li><strong>Vídeo</strong> encaixa um vídeo do YouTube no meio da explicação. O vídeo <em>principal</em> da aula fica na zona do topo.</li>
                            <li>Imagem enviada fica num endereço público: quem tiver o link abre o arquivo sem estar matriculado. <strong>Não use imagem para prova, gabarito ou dado pessoal.</strong></li>
                        </ul>
                    </details>

                    @if ($errors->has('material'))
                        <p role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-apoio font-semibold text-rose-800">{{ $errors->first('material') }}</p>
                    @endif
                    @foreach (session('avisosDoMaterial', []) as $aviso)
                        <p role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-apoio text-amber-950">{{ $aviso }}</p>
                    @endforeach

                    @if ($lista === [])
                        <div class="space-y-3 rounded-2xl border border-dashed border-ava-borda bg-ava-faixa/50 p-6 text-center">
                            <p class="text-apoio italic text-escult-ink-2">Esta aula ainda não tem material escrito.</p>
                            <x-instrutor.menu-adicionar :apos="-1" :base="$base" :versao="$versao" :aberto="$novo === null"
                                                        :novo="$novo !== null && $apos === -1 ? $novo : null" :campo="$campo" :uploadAtivo="$uploadAtivo" />
                        </div>
                    @else
                        <x-instrutor.menu-adicionar :apos="-1" :base="$base" :versao="$versao" :aberto="$adicionarEm === -1 && $novo === null"
                                                    :novo="$novo !== null && $apos === -1 ? $novo : null" :campo="$campo" :uploadAtivo="$uploadAtivo" />

                        <ol role="list" class="space-y-1">
                            @foreach ($lista as $i => $bloco)
                                @php
                                    $tipo = $bloco['tipo'];
                                @endphp
                                <li id="bloco-{{ $i }}" class="scroll-mt-6">
                                    @if ($editarBloco === $i)
                                        @php
                                            $textoAtual = match ($tipo) {
                                                'listaNumerada', 'listaMarcadores' => implode("\n", $bloco['itens'] ?? []),
                                                'secao', 'subsecao', 'paragrafo' => $bloco['texto'] ?? '',
                                                default => '',
                                            };
                                            $linguagens = \App\Support\MaterialDaAula::LINGUAGENS;
                                            if ($tipo === 'codigo' && is_string($bloco['linguagem'] ?? null) && ! in_array($bloco['linguagem'], $linguagens, true)) {
                                                $linguagens[] = $bloco['linguagem'];
                                            }
                                            $ehTexto = in_array($tipo, \App\Support\MaterialDaAula::CONVERSIVEIS, true);
                                        @endphp
                                        <form method="POST" action="{{ $corpo }}" enctype="multipart/form-data"
                                              @if ($ehTexto) x-data="{ tipo: @js(old('tipo', $tipo)) }" @endif
                                              class="space-y-4 rounded-xl border-2 border-ava-acao/60 bg-ava-faixa/40 p-4">
                                            @csrf
                                            <input type="hidden" name="acao" value="atualizar">
                                            <input type="hidden" name="versao" value="{{ $versao }}">
                                            <input type="hidden" name="indice" value="{{ $i }}">
                                            @if ($tipo === 'imagem' || $tipo === 'video')
                                                <input type="hidden" name="tipo" value="{{ $tipo }}">
                                            @endif

                                            <p class="text-sobretitulo uppercase text-ava-acao">Editando: {{ mb_strtolower($rotulos[$tipo] ?? $tipo) }}</p>

                                            @if ($errors->has('material'))
                                                <p role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-apoio font-semibold text-rose-800">{{ $errors->first('material') }}</p>
                                            @endif

                                            @if ($ehTexto)
                                                <div class="space-y-1.5">
                                                    <label for="tipo-{{ $i }}" class="block text-rotulo font-semibold text-ava-tinta">Tipo</label>
                                                    <select id="tipo-{{ $i }}" name="tipo" x-model="tipo" class="{{ $campo }}">
                                                        @foreach (\App\Support\MaterialDaAula::CONVERSIVEIS as $c)
                                                            <option value="{{ $c }}">{{ $rotulos[$c] }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="space-y-1.5">
                                                    <label for="texto-{{ $i }}" class="block text-rotulo font-semibold text-ava-tinta">
                                                        <span x-show="tipo === 'secao'">Título da seção (entra no índice)</span>
                                                        <span x-show="tipo === 'subsecao'" x-cloak>Subtítulo</span>
                                                        <span x-show="tipo === 'paragrafo'" x-cloak>Texto do parágrafo</span>
                                                        <span x-show="tipo === 'listaNumerada' || tipo === 'listaMarcadores'" x-cloak>Itens — um por linha</span>
                                                    </label>
                                                    <p id="dica-texto-{{ $i }}" class="text-apoio text-escult-ink-2">
                                                        <span x-show="tipo === 'paragrafo'">Para destacar uma palavra, envolva com dois asteriscos: **assim**.</span>
                                                        <span x-show="tipo === 'listaNumerada' || tipo === 'listaMarcadores'" x-cloak>A numeração é automática; não precisa escrever “1.” nem “-”.</span>
                                                        <span x-show="tipo === 'secao' || tipo === 'subsecao'" x-cloak>Uma linha só: se houver várias, elas são juntadas.</span>
                                                    </p>
                                                    <textarea id="texto-{{ $i }}" name="texto" rows="4" required aria-describedby="dica-texto-{{ $i }}" class="{{ $campo }}">{{ old('texto', $textoAtual) }}</textarea>
                                                </div>

                                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                    <div class="min-w-0 space-y-1.5" x-show="tipo === 'paragrafo'" x-cloak>
                                                        <label for="tamanho-{{ $i }}" class="block text-rotulo font-semibold text-ava-tinta">Tamanho</label>
                                                        <select id="tamanho-{{ $i }}" name="tamanho" class="{{ $campo }}">
                                                            @foreach (['pequeno' => 'Pequeno', 'normal' => 'Normal', 'grande' => 'Grande', 'muito-grande' => 'Muito grande'] as $v => $r)
                                                                <option value="{{ $v }}" @selected(old('tamanho', $bloco['tamanho'] ?? 'normal') === $v)>{{ $r }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="min-w-0 space-y-1.5" x-show="tipo === 'paragrafo' || tipo === 'subsecao'" x-cloak>
                                                        <label for="alinhamento-{{ $i }}" class="block text-rotulo font-semibold text-ava-tinta">Alinhamento</label>
                                                        <select id="alinhamento-{{ $i }}" name="alinhamento" class="{{ $campo }}">
                                                            @foreach (['esquerda' => 'À esquerda', 'centro' => 'Centralizado', 'direita' => 'À direita', 'justificado' => 'Justificado'] as $v => $r)
                                                                <option value="{{ $v }}" @selected(old('alinhamento', $bloco['alinhamento'] ?? 'esquerda') === $v)>{{ $r }}</option>
                                                            @endforeach
                                                        </select>
                                                        <p class="text-apoio text-escult-ink-2">Em tela de celular, o justificado pode abrir espaços entre as palavras.</p>
                                                    </div>
                                                </div>
                                            @elseif ($tipo === 'codigo')
                                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                                    <div class="min-w-0 space-y-1.5">
                                                        <label for="linguagem-{{ $i }}" class="block text-rotulo font-semibold text-ava-tinta">Linguagem</label>
                                                        <select id="linguagem-{{ $i }}" name="linguagem" class="{{ $campo }}">
                                                            @foreach ($linguagens as $l)
                                                                <option value="{{ $l }}" @selected(old('linguagem', $bloco['linguagem'] ?? 'java') === $l)>{{ ['java' => 'Java', 'javascript' => 'JavaScript', 'typescript' => 'TypeScript', 'php' => 'PHP', 'python' => 'Python', 'sql' => 'SQL', 'json' => 'JSON', 'css' => 'CSS', 'html' => 'HTML', 'bash' => 'Terminal'][$l] ?? $l }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="min-w-0 space-y-1.5 sm:col-span-2">
                                                        <label for="legenda-{{ $i }}" class="block text-rotulo font-semibold text-ava-tinta">Legenda <span class="font-normal text-escult-ink-2">(opcional)</span></label>
                                                        <input id="legenda-{{ $i }}" name="legenda" type="text" class="{{ $campo }}"
                                                               placeholder="Ex.: Código 1: declaração da classe" value="{{ old('legenda', $bloco['legenda'] ?? '') }}">
                                                    </div>
                                                </div>
                                                <div class="space-y-1.5">
                                                    <label for="codigo-{{ $i }}" class="block text-rotulo font-semibold text-ava-tinta">Código</label>
                                                    <textarea id="codigo-{{ $i }}" name="codigo" rows="9" spellcheck="false"
                                                              class="{{ $campo }} font-mono text-apoio leading-relaxed">{{ old('codigo', $bloco['codigo'] ?? '') }}</textarea>
                                                </div>
                                            @elseif ($tipo === 'imagem')
                                                @php
                                                    $imagemAtual = \App\Support\SafeUrl::sanitize($bloco['url'] ?? null);
                                                @endphp
                                                @if ($imagemAtual !== null)
                                                    <div class="flex items-center gap-3 rounded-xl border border-ava-borda bg-white p-2.5">
                                                        <img src="{{ $imagemAtual }}" alt="" class="h-14 w-14 shrink-0 rounded-lg border border-ava-borda object-cover">
                                                        <p class="text-apoio font-semibold text-ava-tinta">Imagem atual da aula</p>
                                                    </div>
                                                @endif
                                                @if ($uploadAtivo)
                                                    <div class="space-y-1.5">
                                                        <label for="arquivo-{{ $i }}" class="block text-rotulo font-semibold text-ava-tinta">Trocar imagem <span class="font-normal text-escult-ink-2">(opcional)</span></label>
                                                        <input id="arquivo-{{ $i }}" name="arquivo" type="file" accept="image/png,image/jpeg,image/webp,image/gif"
                                                               class="block w-full text-apoio text-ava-tinta file:mr-3 file:cursor-pointer file:rounded-[10px] file:border file:border-ava-borda file:bg-white file:px-4 file:py-2 file:text-rotulo file:font-semibold file:text-ava-tinta hover:file:bg-ava-faixa">
                                                    </div>
                                                @endif
                                                <div class="space-y-1.5">
                                                    <label for="descricao-{{ $i }}" class="block text-rotulo font-semibold text-ava-tinta">Descrição da imagem (obrigatória)</label>
                                                    <input id="descricao-{{ $i }}" name="descricao" type="text" required class="{{ $campo }}" value="{{ old('descricao', $bloco['alt'] ?? '') }}">
                                                </div>
                                                <div class="space-y-1.5">
                                                    <label for="legenda-{{ $i }}" class="block text-rotulo font-semibold text-ava-tinta">Legenda <span class="font-normal text-escult-ink-2">(opcional)</span></label>
                                                    <input id="legenda-{{ $i }}" name="legenda" type="text" class="{{ $campo }}"
                                                           placeholder="Ex.: Figura 1 - fluxo de matrícula" value="{{ old('legenda', $bloco['legenda'] ?? '') }}">
                                                </div>
                                            @else
                                                <div class="space-y-1.5">
                                                    <label for="url-{{ $i }}" class="block text-rotulo font-semibold text-ava-tinta">Link do vídeo no YouTube</label>
                                                    <input id="url-{{ $i }}" name="url" type="text" inputmode="url" required class="{{ $campo }}" value="{{ old('url', $bloco['url'] ?? '') }}">
                                                </div>
                                                <div class="space-y-1.5">
                                                    <label for="descricao-{{ $i }}" class="block text-rotulo font-semibold text-ava-tinta">Título do vídeo (obrigatório)</label>
                                                    <input id="descricao-{{ $i }}" name="descricao" type="text" required class="{{ $campo }}" value="{{ old('descricao', $bloco['titulo'] ?? '') }}">
                                                </div>
                                                <div class="space-y-1.5">
                                                    <label for="legenda-{{ $i }}" class="block text-rotulo font-semibold text-ava-tinta">Legenda <span class="font-normal text-escult-ink-2">(opcional)</span></label>
                                                    <input id="legenda-{{ $i }}" name="legenda" type="text" class="{{ $campo }}"
                                                           placeholder="Ex.: Vídeo 1 - demonstração" value="{{ old('legenda', $bloco['legenda'] ?? '') }}">
                                                </div>
                                            @endif

                                            {{-- Prévia ao vivo: o servidor monta o trecho com o MESMO componente do aluno, sem gravar. --}}
                                            <div class="space-y-2 rounded-xl border border-ava-borda bg-white p-4"
                                                 x-data="{ html: '', ruim: false, espera: null, n: 0,
                                                    agendar(f) { clearTimeout(this.espera); this.espera = setTimeout(() => this.buscar(f), 350); },
                                                    async buscar(f) {
                                                        const eu = ++this.n;
                                                        const dados = new FormData(f);
                                                        dados.delete('arquivo');
                                                        try {
                                                            const r = await fetch(@js($base.'/previa'), { method: 'POST', body: dados, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                                                            const t = await r.text();
                                                            if (eu !== this.n) { return; }
                                                            this.html = t; this.ruim = ! r.ok;
                                                        } catch (e) {
                                                            if (eu === this.n) { this.html = ''; this.ruim = true; }
                                                        }
                                                    } }"
                                                 x-init="const f = $el.closest('form'); f.addEventListener('input', () => agendar(f)); f.addEventListener('change', () => agendar(f)); buscar(f)">
                                                <p class="text-sobretitulo uppercase text-escult-ink-2">Prévia — como o aluno vai ver</p>
                                                <div aria-live="polite" x-html="html"></div>
                                                <p x-show="ruim && html === ''" x-cloak class="text-apoio text-escult-ink-2">Não foi possível montar a prévia agora. O “Aplicar” continua funcionando.</p>
                                            </div>

                                            <div class="flex flex-wrap justify-end gap-2">
                                                <a href="{{ request()->url() }}?editar=material#bloco-{{ $i }}" class="{{ $botaoNeutro }}">Cancelar</a>
                                                <button type="submit" class="{{ $salvar }}">Aplicar</button>
                                            </div>
                                        </form>
                                    @else
                                        <div class="rounded-xl border border-transparent px-3 py-2 hover:border-ava-borda hover:bg-ava-faixa/40" x-data="{ removendo: false }">
                                            <div class="mb-2 flex flex-wrap items-center gap-2" x-show="! removendo">
                                                <span class="mr-auto rounded-full bg-ava-faixa px-2 py-0.5 text-sobretitulo uppercase text-escult-ink-2">{{ $rotulos[$tipo] ?? $tipo }}</span>

                                                @foreach (['up' => ['Subir', $i > 0], 'down' => ['Descer', $i < count($lista) - 1]] as $direcao => [$rotuloMover, $pode])
                                                    @if ($pode)
                                                        <form method="POST" action="{{ $corpo }}">
                                                            @csrf
                                                            <input type="hidden" name="acao" value="mover">
                                                            <input type="hidden" name="versao" value="{{ $versao }}">
                                                            <input type="hidden" name="indice" value="{{ $i }}">
                                                            <input type="hidden" name="direcao" value="{{ $direcao }}">
                                                            <button type="submit" class="{{ $botaoNeutro }}">{{ $rotuloMover }}<span class="sr-only"> {{ mb_strtolower($rotulos[$tipo] ?? $tipo) }} {{ $i + 1 }}</span></button>
                                                        </form>
                                                    @endif
                                                @endforeach

                                                <a href="{{ request()->url() }}?editar=material&bloco={{ $i }}#bloco-{{ $i }}" class="{{ $botaoNeutro }}">
                                                    Editar<span class="sr-only"> {{ mb_strtolower($rotulos[$tipo] ?? $tipo) }} {{ $i + 1 }}</span>
                                                </a>
                                                <button type="button" @click="removendo = true"
                                                        class="cursor-pointer rounded-[10px] border border-ava-borda bg-white px-3 py-1.5 text-rotulo font-semibold text-rose-700 transition-colors hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
                                                    Remover<span class="sr-only"> {{ mb_strtolower($rotulos[$tipo] ?? $tipo) }} {{ $i + 1 }}</span>
                                                </button>
                                            </div>

                                            <form method="POST" action="{{ $corpo }}" x-show="removendo" x-cloak
                                                  class="mb-2 space-y-2 rounded-xl border border-rose-200 bg-rose-50 p-3">
                                                @csrf
                                                <input type="hidden" name="acao" value="remover">
                                                <input type="hidden" name="versao" value="{{ $versao }}">
                                                <input type="hidden" name="indice" value="{{ $i }}">
                                                <p class="text-apoio text-rose-900">
                                                    Tirar este trecho do texto da aula? O que está escrito nele sai da aula. A remoção fica no registro de auditoria, com o texto do trecho.
                                                </p>
                                                <div class="flex flex-wrap gap-2">
                                                    <button type="submit"
                                                            class="cursor-pointer rounded-[10px] bg-rose-700 px-4 py-2 text-rotulo font-semibold text-white transition-colors hover:bg-rose-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2">
                                                        Remover o trecho
                                                    </button>
                                                    <button type="button" @click="removendo = false" class="{{ $botaoNeutro }}">Manter</button>
                                                </div>
                                            </form>

                                            <x-aluno.conteudo-da-aula :blocos="[$bloco]" />
                                        </div>
                                    @endif

                                    <x-instrutor.menu-adicionar :apos="$i" :base="$base" :versao="$versao" :aberto="$adicionarEm === $i && $novo === null"
                                                                :novo="$novo !== null && $apos === $i ? $novo : null" :campo="$campo" :uploadAtivo="$uploadAtivo" />
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    <p class="flex flex-wrap gap-x-4 gap-y-1 text-apoio font-semibold text-escult-ink-2">
                        <span>{{ $secoes === 1 ? '1 seção' : $secoes.' seções' }}</span>
                        <span>{{ count($lista) === 1 ? '1 trecho' : count($lista).' trechos' }}</span>
                        <span>{{ $palavras === 1 ? '1 palavra' : $palavras.' palavras' }}</span>
                        @if ($blocos['temCodigo'])
                            <span class="text-ava-acao">contém bloco de código</span>
                        @endif
                    </p>
                </div>
            @endif
        </x-instrutor.zona-da-aula>

        {{-- 4. Anexos --}}
        <x-instrutor.zona-da-aula id="anexos" :titulo="'Material de apoio ('.count($documentos).')'"
                                  dica="Arquivos e links que o aluno abre no fim da aula."
                                  :editando="$editar === 'anexos'">
            @if ($errors->has('anexos'))
                <p role="alert" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-apoio font-semibold text-rose-800">{{ $errors->first('anexos') }}</p>
            @endif

            @if ($documentos === [])
                <p class="text-apoio italic text-escult-ink-2">Nenhum documento vinculado.</p>
            @else
                <ul role="list" class="grid grid-cols-1 gap-2.5 {{ $editar === 'anexos' ? '' : 'sm:grid-cols-2' }}">
                    @foreach ($documentos as $doc)
                        @php
                            $endereco = \App\Support\SafeUrl::sanitize($doc['url'] ?? null);
                        @endphp
                        <li class="min-w-0 rounded-xl border border-ava-borda bg-ava-faixa/50 px-3 py-2.5"
                            x-data="{ desvinculando: false }">
                            <div class="flex min-w-0 items-center gap-2.5">
                                <span class="shrink-0 rounded border border-ava-borda bg-white px-1.5 py-0.5 text-sobretitulo uppercase text-escult-ink-2">{{ $doc['type'] ?? '' }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-apoio font-semibold text-ava-tinta">{{ $doc['title'] ?? '' }}</span>
                                    @if ($editar === 'anexos')
                                        <span class="block truncate text-nota text-escult-ink-2">{{ ! empty($doc['size']) ? $doc['size'].' · ' : '' }}{{ $doc['url'] ?? '' }}</span>
                                    @endif
                                </span>
                                @if ($editar !== 'anexos' && ! empty($doc['size']))
                                    <span class="shrink-0 text-apoio text-escult-ink-2">{{ $doc['size'] }}</span>
                                @endif
                            </div>

                            @if ($editar === 'anexos')
                                <div class="mt-2 flex flex-wrap gap-2" x-show="! desvinculando">
                                    @if ($endereco !== null)
                                        <a href="{{ $endereco }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer"
                                           class="inline-flex items-center rounded-[10px] border border-ava-borda bg-white px-3 py-1.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                                            Abrir<span class="sr-only"> {{ $doc['title'] ?? '' }} em outra aba</span>
                                        </a>
                                    @endif
                                    <button type="button" @click="desvinculando = true"
                                            class="cursor-pointer rounded-[10px] border border-ava-borda bg-white px-3 py-1.5 text-rotulo font-semibold text-rose-700 transition-colors hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
                                        Desvincular<span class="sr-only"> {{ $doc['title'] ?? '' }}</span>
                                    </button>
                                </div>

                                <form method="POST" action="{{ $base }}/anexos/{{ rawurlencode($doc['id'] ?? '') }}/remover"
                                      x-show="desvinculando" x-cloak
                                      class="mt-2 space-y-2 rounded-xl border border-rose-200 bg-rose-50 p-3">
                                    @csrf
                                    <p class="text-apoio text-rose-900">
                                        Desvincular “{{ $doc['title'] ?? '' }}” da aula? Os alunos deixam de vê-lo. Nada é apagado:
                                        o registro continua guardado, com quem o desvinculou e quando.
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="submit"
                                                class="cursor-pointer rounded-[10px] bg-rose-700 px-4 py-2 text-rotulo font-semibold text-white transition-colors hover:bg-rose-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2">
                                            Desvincular o documento
                                        </button>
                                        <button type="button" @click="desvinculando = false"
                                                class="cursor-pointer rounded-[10px] border border-ava-borda bg-white px-4 py-2 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                                            Manter
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($editar === 'anexos')
                <form method="POST" action="{{ $base }}/anexos" enctype="multipart/form-data"
                      class="mt-5 space-y-4 border-t border-ava-borda pt-5">
                    @csrf
                    <p class="text-rotulo font-semibold text-ava-tinta">Anexar novo arquivo ou link</p>

                    @if (config('features.uploadArquivos') === true)
                        <div class="space-y-1.5">
                            <label for="arquivo" class="block text-rotulo font-semibold text-ava-tinta">Arquivo do seu computador</label>
                            <p id="dica-arquivo" class="text-apoio text-escult-ink-2">
                                PDF, Word, imagens e outros. Se escolher um arquivo, o endereço abaixo é ignorado.
                            </p>
                            <input id="arquivo" name="arquivo" type="file" aria-describedby="dica-arquivo"
                                   class="block w-full text-apoio text-ava-tinta file:mr-3 file:cursor-pointer file:rounded-[10px] file:border file:border-ava-borda file:bg-white file:px-4 file:py-2 file:text-rotulo file:font-semibold file:text-ava-tinta hover:file:bg-ava-faixa">
                        </div>
                        <p class="text-center text-sobretitulo uppercase text-escult-ink-2">ou informe um link</p>
                    @endif

                    <div class="space-y-1.5">
                        <label for="anexo-url" class="block text-rotulo font-semibold text-ava-tinta">Endereço do conteúdo</label>
                        <input id="anexo-url" name="url" type="text" inputmode="url" class="{{ $campo }}"
                               placeholder="https://exemplo.com/material-aula-1" value="{{ old('url') }}">
                    </div>

                    <div class="space-y-1.5">
                        <label for="anexo-titulo" class="block text-rotulo font-semibold text-ava-tinta">Título do recurso</label>
                        <p id="dica-titulo" class="text-apoio text-escult-ink-2">Em branco, usa o nome do arquivo.</p>
                        <input id="anexo-titulo" name="titulo" type="text" maxlength="191" aria-describedby="dica-titulo" class="{{ $campo }}"
                               placeholder="Ex.: Slides — Primeiros passos" value="{{ old('titulo') }}">
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="min-w-0 space-y-1.5">
                            <label for="anexo-tipo" class="block text-rotulo font-semibold text-ava-tinta">Tipo</label>
                            <select id="anexo-tipo" name="tipo" class="{{ $campo }}">
                                @foreach (['auto' => 'Detectar automaticamente', 'pdf' => 'Apostila (.pdf)', 'doc' => 'Anotações (.doc)', 'url' => 'Link externo', 'drive' => 'Google Drive', 'outro' => 'Outro tipo'] as $valor => $rotulo)
                                    <option value="{{ $valor }}" @selected(old('tipo', 'auto') === $valor)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="min-w-0 space-y-1.5">
                            <label for="anexo-tamanho" class="block text-rotulo font-semibold text-ava-tinta">Tamanho aproximado <span class="font-normal text-escult-ink-2">(opcional)</span></label>
                            <input id="anexo-tamanho" name="tamanho" type="text" maxlength="30" class="{{ $campo }}"
                                   placeholder="2,1 MB" value="{{ old('tamanho') }}">
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="{{ $salvar }}">Vincular documento</button>
                    </div>
                </form>
            @endif
        </x-instrutor.zona-da-aula>

        {{-- 5. Histórico de versões (ADR 021): o que estava no ar antes de cada alteração. --}}
        <section id="historico" aria-labelledby="titulo-historico" class="scroll-mt-6 space-y-3 rounded-2xl border border-ava-borda bg-white p-5">
            <div>
                <h2 id="titulo-historico" class="font-titulo text-lg leading-tight text-ava-tinta">Histórico de versões</h2>
                <p class="mt-1 text-apoio text-escult-ink-2">
                    Toda vez que o título, a carga, o vídeo ou o texto desta aula mudam, a versão anterior fica guardada aqui.
                    Voltar a uma versão não apaga nada: o que está no ar agora também entra no histórico.
                </p>
            </div>

            @forelse ($versoes as $v)
                <details class="rounded-xl border border-ava-borda bg-ava-faixa/30">
                    <summary class="cursor-pointer px-4 py-3 text-apoio font-semibold text-ava-tinta">
                        {{ $v['quando'] }} <span class="font-normal text-escult-ink-2">— {{ $v['resumo'] }}</span>
                    </summary>
                    <div class="space-y-3 border-t border-ava-borda px-4 py-3">
                        <p class="text-apoio text-escult-ink-2">Substituída por {{ $v['porNome'] ?? 'alguém sem registro' }}. É assim que o texto estava:</p>
                        @if ($v['blocos'] === [])
                            <p class="text-apoio italic text-escult-ink-2">Esta versão não tinha texto.</p>
                        @else
                            <div class="max-h-96 overflow-y-auto rounded-xl border border-ava-borda bg-white p-4">
                                <x-aluno.conteudo-da-aula :blocos="$v['blocos']" />
                            </div>
                        @endif
                        <form method="POST" action="{{ $base }}/versoes/{{ $v['id'] }}/restaurar" class="flex justify-end">
                            @csrf
                            <button type="submit" class="{{ $salvar }}">Voltar a esta versão</button>
                        </form>
                    </div>
                </details>
            @empty
                <p class="text-apoio italic text-escult-ink-2">Ainda não há versões anteriores: esta aula não mudou desde que o histórico passou a existir.</p>
            @endforelse
        </section>
    </div>
</x-layouts.instrutor>
