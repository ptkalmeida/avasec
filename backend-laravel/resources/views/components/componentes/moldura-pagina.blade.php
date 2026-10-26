@props([
    'sobretitulo',
    'titulo',
    'descricao' => null,
    'centralizado' => false,
])

{{--
    Moldura das páginas dedicadas do portal público — equivalente Blade do
    `PageShell.tsx`. Mesma marcação e mesmas classes, de propósito: durante a
    migração as duas convivem, e qualquer diferença aqui aparece como "a página
    mudou de cara" para quem navega entre uma migrada e uma não migrada.

    O botão "Voltar" não existe aqui, pelo mesmo motivo de lá: quem diz onde a
    pessoa está é o Breadcrumb, renderizado uma vez só abaixo do cabeçalho.
--}}
<div class="bg-white min-h-[70vh]">
    <section class="relative overflow-hidden border-b border-ava-borda bg-gradient-to-b from-white to-ava-faixa/60 px-4 py-10 md:py-14">
        <div aria-hidden="true" class="hidden xl:block absolute right-24 top-8 h-40 w-20 rounded-r-full bg-ava-marinho"></div>
        <div aria-hidden="true" class="hidden xl:block absolute right-[11.5rem] top-16 h-20 w-10 rounded-l-full bg-ava-dourado"></div>
        <div aria-hidden="true" class="hidden xl:block absolute right-8 top-6 h-16 w-16 bg-ava-rosa"></div>
        <div aria-hidden="true" class="hidden xl:block absolute right-10 bottom-8 h-20 w-20 bg-ava-acao"></div>

        <div @class([
            'relative mx-auto max-w-7xl space-y-3',
            'text-center' => $centralizado,
            'text-left' => ! $centralizado,
        ])>
            <p class="filete text-sobretitulo uppercase text-ava-tinta">{{ $sobretitulo }}</p>

            {{-- <h1>, e não <h3>: este é o título DA página. --}}
            <h1 @class([
                'text-secao md:text-pagina text-ava-tinta tracking-tight font-titulo break-words max-w-3xl',
                'mx-auto' => $centralizado,
            ])>{{ $titulo }}</h1>

            @if ($descricao)
                <p @class(['text-corpo text-escult-ink-2 max-w-2xl', 'mx-auto' => $centralizado])>
                    {{ $descricao }}
                </p>
            @endif
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 py-10 space-y-8">
        {{ $slot }}
    </div>
</div>
