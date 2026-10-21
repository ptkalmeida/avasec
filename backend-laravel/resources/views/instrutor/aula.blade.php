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
                                  :editavel="false">
            <p class="mb-3 text-sobretitulo uppercase text-escult-ink-2">Como o aluno vê</p>
            @if ($blocos['blocos'] === [])
                <p class="text-apoio italic text-escult-ink-2">Esta aula ainda não tem material escrito.</p>
            @else
                <x-aluno.conteudo-da-aula :blocos="$blocos['blocos']" />
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
                        @php($endereco = \App\Support\SafeUrl::sanitize($doc['url'] ?? null))
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
    </div>
</x-layouts.instrutor>
