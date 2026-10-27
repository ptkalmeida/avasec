{{--
    "Notícias & Novidades" — a última entrada do grupo "A Escola" a sair do React.

    Substitui `src/components/pages/NoticiasPage.tsx`. O conteúdo vem do mesmo
    `SitePageContentService`, com a mesma chave e os mesmos padrões de fábrica:
    migrar a tela não muda o que ela diz (invariante 1 do plano 16).

    A busca do cabeçalho já apontava para cá desde que ele virou Blade, e até
    hoje o link caía no React. Agora o termo chega pela URL e a página filtra,
    mostrando o estado "sem resultados" com a saída para limpar o filtro.
--}}
<x-layouts.publico ativo="A Escola" :titulo="$pagina['titulo']" :descricao="$pagina['descricao']">
    <x-componentes.moldura-pagina
        :sobretitulo="$pagina['eyebrow']"
        :titulo="$pagina['titulo']"
        :descricao="$pagina['descricao']"
    >
        {{--
            `foco` é o cartão em destaque, movido pelas setas. Em Alpine, e não
            em Livewire: mover um realce não é assunto do servidor, e cada
            clique viraria uma ida à rede para trocar uma borda de lugar.

            Vale dizer o que estas setas fazem e o que não fazem: elas NÃO
            paginam nem rolam — os cartões já estão todos na tela. Era assim no
            React, e ficou assim para a migração não mudar o comportamento no
            mesmo passo em que muda a tecnologia. Está registrado no plano 18
            como ponto a decidir.
        --}}
        <div x-data="{ foco: 0, total: {{ max(count($noticias), 1) }} }" class="space-y-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <span class="filete text-sobretitulo uppercase text-ava-tinta">Portal de Notícias AVASEC</span>

                @if (count($noticias) > 1)
                    <div class="flex gap-1.5">
                        <button type="button" aria-label="Destacar a notícia anterior"
                                x-on:click="foco = (foco - 1 + total) % total"
                                class="h-10 w-10 rounded-full bg-white border border-ava-borda hover:bg-ava-faixa flex items-center justify-center text-ava-acao transition-colors shadow-3xs cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                                <path d="m15 18-6-6 6-6"/>
                            </svg>
                        </button>
                        <button type="button" aria-label="Destacar a próxima notícia"
                                x-on:click="foco = (foco + 1) % total"
                                class="h-10 w-10 rounded-full bg-white border border-ava-borda hover:bg-ava-faixa flex items-center justify-center text-ava-acao transition-colors shadow-3xs cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                                <path d="m9 18 6-6-6-6"/>
                            </svg>
                        </button>
                    </div>
                @endif
            </div>

            @if ($noticias === [])
                <div class="rounded-2xl border border-dashed border-ava-borda p-12 text-center bg-ava-faixa/50">
                    <h2 class="text-ava-tinta font-titulo text-cartao mb-2">Sem resultados</h2>
                    <p class="text-escult-ink-2 text-apoio leading-relaxed max-w-md mx-auto">
                        Nenhuma notícia coincide com a sua busca por
                        "<strong class="text-ava-tinta font-bold">{{ $busca }}</strong>".
                    </p>
                    <a href="/noticias"
                       class="mt-4 inline-block px-5 py-2.5 bg-ava-acao text-white text-rotulo font-semibold rounded-[10px] hover:bg-ava-acao-escuro transition-colors font-sans">
                        Limpar filtro de busca
                    </a>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-left">
                    @foreach ($noticias as $noticia)
                        <article
                            x-bind:class="foco % total === {{ $loop->index }} ? 'border-ava-acao ring-1 ring-ava-acao' : 'border-ava-borda'"
                            class="bg-white rounded-2xl border border-ava-borda transition-all duration-300 overflow-hidden shadow-2xs hover:shadow-md flex flex-col justify-between">
                            @if (! empty($noticia['imagem']))
                                <div class="h-44 overflow-hidden relative">
                                    {{--
                                        `alt` vazio e `aria-hidden`: a imagem repete o
                                        título que vem logo abaixo, e anunciá-la faria o
                                        leitor de tela dizer a mesma frase duas vezes.
                                    --}}
                                    <img src="{{ $noticia['imagem'] }}" alt="" aria-hidden="true" loading="lazy"
                                         referrerpolicy="no-referrer"
                                         class="w-full h-full object-cover hover:scale-105 transition-transform duration-500">
                                    @if (! empty($noticia['tag']))
                                        <span class="absolute top-3 left-3 bg-white text-ava-acao text-nota font-semibold uppercase tracking-wide px-2.5 py-1 rounded-full shadow-sm max-w-[calc(100%-1.5rem)] truncate">
                                            {{ $noticia['tag'] }}
                                        </span>
                                    @endif
                                </div>
                            @endif

                            <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                                <div class="space-y-2">
                                    <span class="text-apoio text-escult-ink-2 font-bold block">{{ $noticia['data'] ?? '' }}</span>
                                    <h2 class="text-cartao text-ava-tinta leading-snug line-clamp-2 font-titulo">{{ $noticia['titulo'] }}</h2>
                                    <p class="text-apoio text-escult-ink-2 leading-relaxed line-clamp-3">{{ $noticia['descricao'] ?? '' }}</p>
                                </div>

                                {{--
                                    A leitura completa exige conta, e era isso que o
                                    botão do React dizia ao abrir o modal de login.
                                    Para quem já entrou não há "mais" a mostrar — não
                                    existe página de notícia — e por isso o convite
                                    some em vez de levar a pessoa de volta ao painel.
                                --}}
                                @if (empty($usuarioLogado))
                                    <a href="/entrar"
                                       class="w-full text-center py-2.5 rounded-[10px] bg-white hover:bg-ava-acao hover:text-white transition-colors text-ava-acao border border-ava-acao text-rotulo font-semibold block">
                                        Entrar para ler o informativo
                                    </a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </x-componentes.moldura-pagina>
</x-layouts.publico>
