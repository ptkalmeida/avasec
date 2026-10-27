{{--
    "O que é o AVA" — primeira tela migrada para Blade (fase M1 do plano 16).

    Substitui `src/components/pages/AvaPage.tsx`. O conteúdo é o mesmo serviço
    que alimentava o React; o que muda é só quem monta o HTML.

    Os ícones vieram do `lucide-react` e aqui são SVG embutido. Não é perda: na
    versão React eles já eram decoração cíclica (`ICONES[index % 3]`), nunca
    campo editável, e `aria-hidden` desde lá.
--}}
<x-layouts.publico ativo="A Escola" :titulo="$pagina['titulo']" :descricao="$pagina['descricao']">
    <x-componentes.moldura-pagina
        :sobretitulo="$pagina['eyebrow']"
        :titulo="$pagina['titulo']"
        :descricao="$pagina['descricao']"
        centralizado
    >
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @foreach ($pagina['items'] as $i => $item)
                <div class="bg-white rounded-2xl p-6.5 border border-ava-borda shadow-3xs hover:shadow-md transition-all space-y-4 text-left">
                    <div class="h-14 w-14 rounded-full bg-ava-icone-fundo text-ava-acao flex items-center justify-center">
                        <x-icone-do-cartao :indice="$i" />
                    </div>
                    <h4 class="text-cartao text-ava-tinta font-titulo">{{ $item['titulo'] }}</h4>
                    <p class="text-apoio text-escult-ink-2 leading-relaxed">{{ $item['descricao'] }}</p>
                </div>
            @endforeach
        </div>
    </x-componentes.moldura-pagina>
</x-layouts.publico>
