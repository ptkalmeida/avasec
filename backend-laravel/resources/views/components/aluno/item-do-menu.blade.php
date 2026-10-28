@props(['rota', 'rotulo', 'ativo' => false, 'icone' => null, 'detalhe' => null])

{{--
    Um item do menu lateral do ALUNO: a moldura compartilhada mais o ícone
    desta área.

    A moldura (incluindo "como se mostra o item ativo") mora em
    `componentes/item-de-menu`, desde que o instrutor ganhou menu próprio —
    duas cópias daquela regra viram duas regras parecidas. Aqui fica só a
    escolha do desenho, que é o que de fato muda entre as áreas.
--}}

<x-componentes.item-de-menu :rota="$rota" :rotulo="$rotulo" :ativo="$ativo" :detalhe="$detalhe">
        @switch($icone)
            @case('painel')
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4.25A1.25 1.25 0 0 1 4.25 3h4A1.25 1.25 0 0 1 9.5 4.25v4A1.25 1.25 0 0 1 8.25 9.5h-4A1.25 1.25 0 0 1 3 8.25v-4Zm7.5 0A1.25 1.25 0 0 1 11.75 3h4A1.25 1.25 0 0 1 17 4.25v4a1.25 1.25 0 0 1-1.25 1.25h-4A1.25 1.25 0 0 1 10.5 8.25v-4ZM3 11.75A1.25 1.25 0 0 1 4.25 10.5h4a1.25 1.25 0 0 1 1.25 1.25v4A1.25 1.25 0 0 1 8.25 17h-4A1.25 1.25 0 0 1 3 15.75v-4Zm7.5 0a1.25 1.25 0 0 1 1.25-1.25h4A1.25 1.25 0 0 1 17 11.75v4A1.25 1.25 0 0 1 15.75 17h-4a1.25 1.25 0 0 1-1.25-1.25v-4Z"/></svg>
                @break
            @case('curso')
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3 2 6.5 10 10l8-3.5L10 3Z"/><path d="M4 9.1v3.4c0 .6.3 1.1.9 1.4 1.3.7 3.1 1.1 5.1 1.1s3.8-.4 5.1-1.1c.6-.3.9-.8.9-1.4V9.1l-6 2.6-6-2.6Z"/></svg>
                @break
            @case('catalogo')
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4.5A1.5 1.5 0 0 1 4.5 3H9v14H4.5A1.5 1.5 0 0 1 3 15.5v-11ZM11 3h4.5A1.5 1.5 0 0 1 17 4.5v11a1.5 1.5 0 0 1-1.5 1.5H11V3Z"/></svg>
                @break
            @case('certificado')
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9Zm-3 9.7V18l3-1.6 3 1.6v-6.3a6 6 0 0 1-6 0Z"/></svg>
                @break
            @case('biblioteca')
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M4 3h3v14H4V3Zm4.5 0h3v14h-3V3ZM13 3.6l2.9-.8 3 11.3-2.9.8L13 3.6Z"/></svg>
                @break
            @case('suporte')
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a7 7 0 0 0-7 7v4.5A2.5 2.5 0 0 0 5.5 16H7v-5H5V9a5 5 0 0 1 10 0v2h-2v5h1.5A2.5 2.5 0 0 0 17 13.5V9a7 7 0 0 0-7-7Z"/></svg>
                @break
            @case('ajuda')
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.4-5.4a.9.9 0 1 1-1.8 0 .9.9 0 0 1 1.8 0Zm-.4-7.2a2.6 2.6 0 0 0-2.6 2.4.8.8 0 1 0 1.6.1c0-.5.5-.9 1-.9s1 .4 1 .9c0 .4-.2.6-.7.9-.6.4-1.1.9-1.1 1.8a.8.8 0 0 0 1.6 0c0-.2.1-.4.5-.6.6-.4 1.3-1 1.3-2.1A2.6 2.6 0 0 0 10 5.4Z" clip-rule="evenodd"/></svg>
                @break
            @case('perfil')
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm0 1.5c-3 0-5.5 1.7-5.5 3.8V17h11v-1.7c0-2.1-2.5-3.8-5.5-3.8Z"/></svg>
                @break
            @case('continuar')
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.3 3.7A1 1 0 0 0 4.8 4.6v10.8a1 1 0 0 0 1.5.9l9-5.4a1 1 0 0 0 0-1.7l-9-5.5Z"/></svg>
                @break
            @case('avaliacao')
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3.5A1.5 1.5 0 0 1 5.5 2h9A1.5 1.5 0 0 1 16 3.5v13a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 4 16.5v-13Zm8.2 4.7a.75.75 0 1 0-1.1-1L9 9.3 8 8.3a.75.75 0 0 0-1.1 1l1.5 1.5c.3.3.8.3 1.1 0l2.7-2.6Z" clip-rule="evenodd"/></svg>
                @break
        @endswitch
</x-componentes.item-de-menu>
