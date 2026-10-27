{{--
    "Orientações Gerais" — quarta tela migrada (fase M1 do plano 16).

    Substitui `src/components/pages/OrientacoesPage.tsx`. Conteúdo do mesmo
    `SitePageContentService`; muda só quem monta o HTML.

    A numeração romana vem da ORDEM, não do conteúdo — o admin edita título e
    descrição da diretriz, nunca o algarismo. O esquema da página permite até 12
    diretrizes (`maxItems`), e a lista cobre exatamente esse teto; passando dele,
    cai no número arábico, como no React. Não é caso esperado: é a saída que
    evita uma diretriz sem rótulo se o teto mudar e alguém esquecer deste ponto.
--}}
@php
    $romanos = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
@endphp

<x-layouts.publico ativo="Ajuda" :titulo="$pagina['titulo']" :descricao="$pagina['descricao']">
    <x-componentes.moldura-pagina
        :sobretitulo="$pagina['eyebrow']"
        :titulo="$pagina['titulo']"
        :descricao="$pagina['descricao']"
        centralizado
    >
        {{--
            `text-left` aqui dentro: a moldura está centralizada (título e texto
            de abertura ao centro), mas os cartões leem alinhados à esquerda.
        --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 text-left">
            @foreach ($pagina['items'] as $i => $diretriz)
                <div class="bg-white rounded-2xl p-5 border border-ava-borda shadow-3xs hover:shadow-md transition-all space-y-3">
                    <span class="text-sobretitulo text-ava-tinta uppercase bg-ava-icone-fundo rounded-full px-3 py-1 inline-block">
                        Diretriz {{ $romanos[$i] ?? $i + 1 }}
                    </span>
                    <h4 class="text-cartao text-ava-tinta font-titulo">{{ $diretriz['titulo'] }}</h4>
                    <p class="text-rotulo text-escult-ink-2 leading-relaxed">{{ $diretriz['descricao'] }}</p>
                </div>
            @endforeach
        </div>
    </x-componentes.moldura-pagina>
</x-layouts.publico>
