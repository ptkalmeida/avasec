{{--
    "O Projeto Pedagógico" — terceira tela migrada (fase M1 do plano 16).

    Substitui `src/components/pages/ProjetoPage.tsx`. Conteúdo do mesmo
    `SitePageContentService`; muda só quem monta o HTML.

    Duas diferenças em relação às telas anteriores, e as duas são do original:

    1. O texto de abertura NÃO vai para a moldura. Lá em cima ficam só o
       sobretítulo e o título; a descrição entra na coluna de texto, ao lado da
       imagem, porque é ela que o leitor lê junto dos pilares.
    2. O alinhamento é à esquerda (a moldura sem `centralizado`), ao contrário
       de "O que é o AVA" e "Dúvidas".

    A numeração dos pilares vem da ORDEM, não do conteúdo: o admin edita título
    e descrição, nunca o número. Era assim no React (`index + 1`) e continua.
--}}
<x-layouts.publico ativo="A Escola" :titulo="$pagina['title']" :descricao="$pagina['description']">
    <x-componentes.moldura-pagina
        :sobretitulo="$pagina['eyebrow']"
        :titulo="$pagina['title']"
    >
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
            {{--
                Imagem decorativa. O contorno deslocado do desenho antigo saiu:
                com `w-full` e `left-4` ele passava 16px da tela em 320px. No
                lugar ficou o par geométrico da skill, dentro da caixa.
            --}}
            <div class="lg:col-span-5 relative flex items-center justify-center">
                <div class="relative w-full max-w-[22rem] aspect-square shrink-0">
                    <div aria-hidden="true" class="absolute -top-0 -left-0 h-16 w-16 rounded-br-full bg-ava-dourado z-10"></div>
                    <div aria-hidden="true" class="absolute bottom-0 right-0 h-16 w-16 bg-ava-rosa z-10"></div>
                    <div class="w-full h-full rounded-2xl overflow-hidden shadow-xl relative bg-ava-marinho">
                        {{--
                            `referrerpolicy="no-referrer"`: o endereço da imagem é
                            editável pelo admin e costuma apontar para fora. Sem
                            isto, o navegador conta ao servidor de destino de qual
                            página do portal a pessoa veio.
                        --}}
                        <img
                            src="{{ $pagina['imageUrl'] }}"
                            alt="Escola Estadual da Cultura RJ"
                            class="w-full h-full object-cover filter brightness-95"
                            referrerpolicy="no-referrer"
                        >
                    </div>
                </div>
            </div>

            {{-- Texto de abertura e os pilares do plano político-pedagógico. --}}
            <div class="lg:col-span-7 space-y-6 text-left">
                <p class="text-corpo text-escult-ink-2 leading-relaxed">{{ $pagina['description'] }}</p>

                <div class="space-y-4 pt-2">
                    @foreach ($pagina['items'] as $i => $pilar)
                        <div class="flex gap-4 rounded-2xl border border-ava-borda bg-white p-4.5">
                            <div class="h-11 w-11 rounded-full bg-ava-icone-fundo flex items-center justify-center text-ava-acao text-lg font-black shrink-0">
                                {{ $i + 1 }}
                            </div>
                            <div class="space-y-1 min-w-0">
                                <strong class="text-rotulo text-ava-tinta font-bold block">{{ $pilar['title'] }}</strong>
                                <p class="text-apoio text-escult-ink-2 leading-relaxed">{{ $pilar['description'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </x-componentes.moldura-pagina>
</x-layouts.publico>
