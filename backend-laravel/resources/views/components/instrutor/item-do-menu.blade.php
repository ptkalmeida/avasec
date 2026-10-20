@props(['rota', 'rotulo', 'ativo' => false, 'icone' => null, 'contagem' => null])

{{--
    Um item do menu lateral do INSTRUTOR: a moldura compartilhada
    (`componentes/item-de-menu`, onde mora a regra do item ativo) mais o ícone
    desta área.
--}}

<x-componentes.item-de-menu :rota="$rota" :rotulo="$rotulo" :ativo="$ativo" :contagem="$contagem">
    @switch($icone)
        @case('cursos')
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3 2 6.5 10 10l8-3.5L10 3Z"/><path d="M4 9.1v3.4c0 .6.3 1.1.9 1.4 1.3.7 3.1 1.1 5.1 1.1s3.8-.4 5.1-1.1c.6-.3.9-.8.9-1.4V9.1l-6 2.6-6-2.6Z"/></svg>
            @break
        @case('gestao')
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4.25A1.25 1.25 0 0 1 4.25 3h11.5A1.25 1.25 0 0 1 17 4.25v2.5A1.25 1.25 0 0 1 15.75 8H4.25A1.25 1.25 0 0 1 3 6.75v-2.5ZM3 11.25A1.25 1.25 0 0 1 4.25 10h5.5A1.25 1.25 0 0 1 11 11.25v4.5A1.25 1.25 0 0 1 9.75 17h-5.5A1.25 1.25 0 0 1 3 15.75v-4.5ZM13.25 10A1.25 1.25 0 0 0 12 11.25v4.5A1.25 1.25 0 0 0 13.25 17h2.5A1.25 1.25 0 0 0 17 15.75v-4.5A1.25 1.25 0 0 0 15.75 10h-2.5Z"/></svg>
            @break
        @case('grade')
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M4 3h12a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm1.75 3.5a.75.75 0 0 0 0 1.5h8.5a.75.75 0 0 0 0-1.5h-8.5Zm0 3a.75.75 0 0 0 0 1.5h8.5a.75.75 0 0 0 0-1.5h-8.5Zm0 3a.75.75 0 0 0 0 1.5h5a.75.75 0 0 0 0-1.5h-5Z"/></svg>
            @break
        @case('avaliacoes')
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M6 2.5A1.5 1.5 0 0 0 4.5 4v12A1.5 1.5 0 0 0 6 17.5h8a1.5 1.5 0 0 0 1.5-1.5V7.5L11 2.5H6Zm4.5 1.3 3.7 3.7H10.5V3.8ZM7.6 11.2a.75.75 0 0 1 1.06 0l.84.84 2.34-2.34a.75.75 0 1 1 1.06 1.06l-2.87 2.87a.75.75 0 0 1-1.06 0L7.6 12.26a.75.75 0 0 1 0-1.06Z"/></svg>
            @break
        @case('alunos')
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M7.5 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm0 1.5c-2.6 0-4.75 1.5-4.75 3.4V16h9.5v-2.1c0-1.9-2.15-3.4-4.75-3.4ZM14 9.5a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5Zm-.3 1.5c1.9.1 3.55 1.35 3.55 2.9V16h-3.5v-2.1c0-1.1-.4-2.1-1.1-2.9h1.05Z"/></svg>
            @break
        @case('mensagens')
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 5.5A1.5 1.5 0 0 1 4.5 4h11A1.5 1.5 0 0 1 17 5.5v7a1.5 1.5 0 0 1-1.5 1.5H8.6l-3.1 2.4a.75.75 0 0 1-1.2-.6V14H4.5A1.5 1.5 0 0 1 3 12.5v-7Z"/></svg>
            @break
        @case('portal')
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2 2 8.2V17h5.5v-4.5h5V17H18V8.2L10 2Z"/></svg>
            @break
    @endswitch
</x-componentes.item-de-menu>
