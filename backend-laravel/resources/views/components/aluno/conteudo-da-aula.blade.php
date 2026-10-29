@props(['blocos' => []])

{{--
    O conteúdo didático da aula.

    Traduz `src/components/student/LessonContent.tsx`. O que ele recebe é a
    saída de `App\Support\ConteudoDaAula` — estrutura de dados, nunca HTML.

    ## A regra que não pode cair

    **Nada do que o autor escreve vira marcação.** Todo texto sai escapado pelo
    Blade; o único enriquecimento é o `**negrito**`, montado em
    `x-aluno.texto-da-aula` partindo o texto em pedaços e escapando cada um. É
    por isso que o formato do autor é uma lista fechada de opções, e não estilo
    livre.

    ## O que mudou em relação ao React, e por quê

    O bloco de código perdeu o realce de sintaxe. No React ele carregava o Prism
    sob demanda (~140 kB) e colorizava as palavras-chave. Aqui o código sai em
    `<pre>` com fonte monoespaçada e a indentação intacta — que é o que o aluno
    precisa para ler e copiar. Trazer um colorizador de volta tem custo próprio
    (peso e dependência nova) e não se decide de passagem numa migração de tela.
    Está registrado no plano 18 como pendência consciente.
--}}

@php($classeTamanho = ['pequeno' => 'text-nota', 'normal' => '', 'grande' => 'text-corpo', 'muito-grande' => 'text-cartao'])
@php($classeAlinhamento = ['esquerda' => '', 'centro' => 'text-center', 'direita' => 'text-right', 'justificado' => 'text-justify hyphens-auto'])

<div class="text-apoio leading-[1.75] text-escult-ink-2">
    @foreach ($blocos as $bloco)
        @switch($bloco['tipo'])

            @case('secao')
                {{-- `scroll-mt` para o cabeçalho fixo não cobrir o alvo do salto. --}}
                <h2 id="{{ $bloco['id'] }}" class="mb-4 mt-9 flex scroll-mt-28 items-baseline gap-2.5 font-titulo text-base font-black text-ava-tinta first:mt-0 md:text-lg">
                    <span class="shrink-0 text-sm text-ava-lilas">{{ $bloco['indice'] }}.</span>
                    <span><x-aluno.texto-da-aula :texto="$bloco['texto']" /></span>
                </h2>
                @break

            @case('subsecao')
                <h3 id="{{ $bloco['id'] }}"
                    class="mb-2.5 mt-7 scroll-mt-28 text-sm font-extrabold text-ava-acao {{ $classeAlinhamento[$bloco['alinhamento'] ?? 'esquerda'] }}">
                    <x-aluno.texto-da-aula :texto="$bloco['texto']" />
                </h3>
                @break

            @case('paragrafo')
                <p class="mb-3.5 {{ $classeTamanho[$bloco['tamanho'] ?? 'normal'] }} {{ $classeAlinhamento[$bloco['alinhamento'] ?? 'esquerda'] }}">
                    <x-aluno.texto-da-aula :texto="$bloco['texto']" />
                </p>
                @break

            @case('listaNumerada')
                <ol class="mb-4 list-decimal space-y-1.5 pl-5 marker:font-bold marker:text-ava-lilas">
                    @foreach ($bloco['itens'] as $item)
                        <li><x-aluno.texto-da-aula :texto="$item" /></li>
                    @endforeach
                </ol>
                @break

            @case('listaMarcadores')
                <ul class="mb-4 list-disc space-y-1.5 pl-5 marker:text-ava-lilas">
                    @foreach ($bloco['itens'] as $item)
                        <li><x-aluno.texto-da-aula :texto="$item" /></li>
                    @endforeach
                </ul>
                @break

            @case('codigo')
                <figure class="my-5 space-y-2">
                    @if ($bloco['legenda'] !== null)
                        <figcaption class="text-apoio font-bold text-ava-acao">{{ $bloco['legenda'] }}</figcaption>
                    @endif
                    <div class="overflow-hidden rounded-xl border border-ava-borda bg-ava-faixa">
                        @if ($bloco['linguagem'] !== null)
                            <div class="border-b border-ava-borda px-4 py-2 text-nota font-semibold uppercase tracking-wide text-escult-ink-3">
                                {{ $bloco['linguagem'] }}
                            </div>
                        @endif
                        {{-- Rolagem na caixa, e não quebra de linha: código quebrado
                             no meio muda de significado ao ser copiado. --}}
                        <pre class="overflow-x-auto p-4 text-apoio leading-relaxed"><code class="font-mono">{{ $bloco['codigo'] }}</code></pre>
                    </div>
                </figure>
                @break

            @case('imagem')
                {{--
                    Clicar na imagem a abre ampliada, ajustada à tela (plano 20).

                    O ampliado é um `<dialog>` nativo aberto com `showModal()`: o navegador dá o
                    `Esc`, o foco preso dentro da janela, o fundo escurecido e o retorno do foco
                    à imagem ao fechar — nada disso foi reescrito aqui.

                    A imagem é um LINK para o arquivo: sem JavaScript (ou onde o Alpine não
                    estiver), o clique abre a imagem em outra aba em vez de não fazer nada. Com o
                    Alpine, `.prevent` troca isso pela janela. O Alpine existe em todos os
                    layouts (vem com o Livewire) e na prévia do editor, que o Alpine injeta.

                    O `<img>` ampliado usa a MESMA URL da miniatura: o navegador reaproveita o
                    arquivo já baixado. `lazy` num elemento escondido só carrega ao abrir.

                    `alt` vem do autor e pode ser vazio de propósito (imagem decorativa): o link
                    precisa de nome mesmo assim, por isso o `aria-label`.
                --}}
                <figure class="my-5 space-y-2" x-data>
                    <a href="{{ $bloco['url'] }}" target="_blank" rel="noopener noreferrer"
                       aria-label="{{ $bloco['alt'] !== '' ? 'Ampliar imagem: '.$bloco['alt'] : 'Ampliar imagem' }}"
                       @click.prevent="$refs.ampliada.showModal()"
                       class="block cursor-zoom-in rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ava-acao">
                        <img src="{{ $bloco['url'] }}" alt="{{ $bloco['alt'] }}" loading="lazy"
                             referrerpolicy="no-referrer"
                             class="w-full rounded-xl border border-ava-borda bg-ava-faixa">
                    </a>
                    @if ($bloco['legenda'] !== null)
                        <figcaption class="text-apoio font-bold text-ava-acao">{{ $bloco['legenda'] }}</figcaption>
                    @endif
                    {{-- Clique no fundo (o próprio <dialog>, fora do conteúdo) fecha. --}}
                    <dialog x-ref="ampliada"
                            @click="if ($event.target === $el) $el.close()"
                            aria-label="{{ $bloco['alt'] !== '' ? $bloco['alt'] : 'Imagem ampliada' }}"
                            class="m-auto max-h-[94vh] max-w-[96vw] overflow-auto rounded-xl border border-ava-borda bg-white p-2 backdrop:bg-black/80">
                        <form method="dialog" class="mb-2 flex justify-end">
                            <button type="submit"
                                    class="rounded-lg bg-ava-acao px-4 py-2 text-apoio font-bold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ava-acao">
                                Fechar
                            </button>
                        </form>
                        <img src="{{ $bloco['url'] }}" alt="{{ $bloco['alt'] }}" loading="lazy"
                             referrerpolicy="no-referrer"
                             class="mx-auto max-h-[80vh] max-w-full object-contain">
                        @if ($bloco['legenda'] !== null)
                            <p class="mt-2 text-center text-apoio font-bold text-ava-acao">{{ $bloco['legenda'] }}</p>
                        @endif
                    </dialog>
                </figure>
                @break

            @case('video')
                <figure class="my-5 space-y-2">
                    <x-aluno.video :url="$bloco['url']" :titulo="$bloco['titulo'] !== '' ? $bloco['titulo'] : 'Vídeo da aula'" />
                    @if ($bloco['legenda'] !== null)
                        <figcaption class="text-apoio font-bold text-ava-acao">{{ $bloco['legenda'] }}</figcaption>
                    @endif
                </figure>
                @break

        @endswitch
    @endforeach
</div>
