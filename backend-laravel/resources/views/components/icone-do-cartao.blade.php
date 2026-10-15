@props(['indice' => 0])

{{--
    Os três ícones dos cartões, em ciclo — equivalentes aos `Video`, `Users` e
    `Award` do lucide-react. Decoração: `aria-hidden`, como já eram no React.

    Embutidos em vez de uma fonte de ícones: são três, e carregar um pacote
    inteiro para três desenhos custaria mais do que o SVG.
--}}
@php
    $svgs = [
        '<path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/>',
        '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        '<path d="m15.477 12.89 1.515 8.526a.5.5 0 0 1-.81.47l-3.58-2.687a1 1 0 0 0-1.197 0l-3.586 2.686a.5.5 0 0 1-.81-.469l1.514-8.526"/><circle cx="12" cy="8" r="6"/>',
    ];
@endphp

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
     class="h-6 w-6" aria-hidden="true">
    {!! $svgs[$indice % count($svgs)] !!}
</svg>
