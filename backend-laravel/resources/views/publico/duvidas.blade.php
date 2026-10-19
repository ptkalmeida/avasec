{{--
    "Dúvidas Frequentes" — segunda tela migrada (fase M1 do plano 16).

    Substitui `src/components/pages/DuvidasPage.tsx`. O cabeçalho da página é
    estático e fica em Blade puro; o acordeão, que é a parte interativa, é um
    componente Livewire — divisão que segue a decisão de 15/10/2026.
--}}
<x-layouts.publico ativo="Ajuda" :titulo="$pagina['title']" :descricao="$pagina['description']">
    <x-componentes.moldura-pagina
        :sobretitulo="$pagina['eyebrow']"
        :titulo="$pagina['title']"
        :descricao="$pagina['description']"
        centralizado
    >
        <livewire:publico.duvidas-frequentes />
    </x-componentes.moldura-pagina>
</x-layouts.publico>
