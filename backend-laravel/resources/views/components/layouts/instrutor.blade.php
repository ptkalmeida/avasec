<!DOCTYPE html>
<html lang="pt-BR" class="tema-ava">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ?? 'Área do instrutor' }} — AVASEC</title>

    {{-- Área autenticada não é para buscador. --}}
    <meta name="robots" content="noindex, nofollow">

    @vite(['backend-laravel/resources/css/blade.css', 'backend-laravel/resources/js/blade.ts'])
    @livewireStyles
</head>
<body class="bg-white font-sans text-ava-tinta antialiased">
    <a href="#conteudo"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-3 focus:z-50 focus:rounded-[10px] focus:bg-ava-acao focus:px-4 focus:py-2 focus:text-rotulo focus:font-semibold focus:text-white">
        Pular para o conteúdo
    </a>

    {{-- Mesma moldura da área do aluno: menu lateral em toda tela autenticada,
         fora do `<main>` porque é navegação do ambiente, e barra horizontal
         abaixo de `lg`, onde não cabe a coluna de 264px ao lado do conteúdo. --}}
    <div class="flex min-h-screen">
        <div class="hidden lg:block">
            <x-instrutor.menu-lateral
                :ativo="$ativo ?? null"
                :curso="$cursoDoMenu ?? null"
                :aguardando="$aguardandoNoMenu ?? null" />
        </div>

        <div class="flex min-w-0 flex-1 flex-col">
            <x-instrutor.barra-estreita
                :nome="$instrutor['name'] ?? ''"
                :curso="$cursoDoMenu ?? null" />

            <main id="conteudo" class="flex-1 px-4 py-6 md:px-8 md:py-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
