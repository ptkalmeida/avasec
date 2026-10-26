{{--
    `/inst/curso/{ref}/aulas/nova` — o formulário da aula nova.

    Substitui o modal "Nova Aula de Fixação" do painel React. A aula nasce com
    título, carga e texto de estudo (o React exigia os três; o vídeo é
    opcional) e entra no FIM da grade, com a posição calculada no servidor.

    O texto de estudo aqui é um campo simples, no formato reduzido que o editor
    entende. Depois de criada, a aula abre na página dela, onde o texto se edita
    bloco a bloco — o que o modal tentava fazer num cantinho de tela.
--}}
<x-layouts.instrutor :titulo="'Nova aula — '.$curso['title']" ativo="grade" :cursoDoMenu="$curso">

    <div class="mx-auto max-w-3xl space-y-6">

        <nav aria-label="Trilha de navegação" class="text-apoio text-escult-ink-3">
            <a href="/inst" class="underline-offset-[3px] hover:text-ava-acao hover:underline">Meus cursos</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <a href="/inst/curso/{{ $ref }}" class="underline-offset-[3px] hover:text-ava-acao hover:underline">{{ $curso['title'] }}</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <a href="/inst/curso/{{ $ref }}/grade-curricular" class="underline-offset-[3px] hover:text-ava-acao hover:underline">Grade curricular</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <span class="text-ava-tinta">Nova aula</span>
        </nav>

        <h1 class="font-titulo text-3xl leading-tight tracking-tight text-ava-tinta">Nova aula</h1>

        @if ($errors->has('aula'))
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-apoio font-semibold text-rose-800">
                {{ $errors->first('aula') }}
            </div>
        @endif

        <form method="POST" action="/inst/curso/{{ $ref }}/aulas" class="space-y-5 rounded-2xl border border-ava-borda bg-white p-4 shadow-3xs md:p-6">
            @csrf

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="min-w-0 space-y-1.5 sm:col-span-2">
                    <label for="titulo" class="block text-rotulo font-semibold text-ava-tinta">Título da aula</label>
                    <input id="titulo" name="titulo" type="text" required maxlength="191"
                           value="{{ old('titulo') }}" placeholder="Ex.: Usando State e Effect Hooks"
                           class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                </div>
                <div class="min-w-0 space-y-1.5">
                    <label for="carga" class="block text-rotulo font-semibold text-ava-tinta">Carga</label>
                    <input id="carga" name="carga" type="text" required maxlength="30"
                           value="{{ old('carga', '15 min') }}" placeholder="20 min"
                           class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="video" class="block text-rotulo font-semibold text-ava-tinta">
                    Vídeo principal <span class="font-normal text-escult-ink-2">(opcional, abre no topo da aula)</span>
                </label>
                <p id="dica-video" class="text-apoio text-escult-ink-2">
                    Abra o vídeo no YouTube, copie o endereço da barra do navegador e cole aqui.
                </p>
                <input id="video" name="video" type="text" inputmode="url" aria-describedby="dica-video"
                       value="{{ old('video') }}" placeholder="https://youtu.be/…"
                       class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
            </div>

            <div class="space-y-1.5">
                <label for="texto" class="block text-rotulo font-semibold text-ava-tinta">Texto de estudo</label>
                <p id="dica-texto" class="text-apoio text-escult-ink-2">
                    Uma linha por parágrafo. <code class="rounded bg-ava-faixa px-1">## Título</code> abre uma seção,
                    <code class="rounded bg-ava-faixa px-1">### Título</code> um subtítulo,
                    <code class="rounded bg-ava-faixa px-1">- item</code> e <code class="rounded bg-ava-faixa px-1">1. item</code> fazem listas,
                    e <code class="rounded bg-ava-faixa px-1">**negrito**</code> destaca. Imagens e vídeos entram depois, na página da aula.
                </p>
                <textarea id="texto" name="texto" rows="12" required maxlength="20000" aria-describedby="dica-texto"
                          class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 font-mono text-apoio leading-relaxed text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">{{ old('texto') }}</textarea>
            </div>

            <div class="flex flex-wrap justify-end gap-2">
                <a href="/inst/curso/{{ $ref }}/grade-curricular"
                   class="inline-flex items-center rounded-[10px] border border-ava-borda bg-white px-5 py-2.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                    Cancelar
                </a>
                <button type="submit"
                        class="cursor-pointer rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                    Adicionar aula
                </button>
            </div>
        </form>
    </div>
</x-layouts.instrutor>
