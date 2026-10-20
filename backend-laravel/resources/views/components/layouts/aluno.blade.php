<!DOCTYPE html>
<html lang="pt-BR" class="tema-ava">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ?? 'Área do aluno' }} — AVASEC</title>

    {{-- Área autenticada não é para buscador: ela só responde a quem tem sessão,
         e um robô indexaria a tela de login no lugar dela. --}}
    <meta name="robots" content="noindex, nofollow">

    @vite(['backend-laravel/resources/css/blade.css', 'backend-laravel/resources/js/blade.ts'])
    @livewireStyles
</head>
<body class="bg-white font-sans text-ava-tinta antialiased">
    <a href="#conteudo"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-3 focus:z-50 focus:rounded-[10px] focus:bg-ava-acao focus:px-4 focus:py-2 focus:text-rotulo focus:font-semibold focus:text-white">
        Pular para o conteúdo
    </a>

    {{--
        Menu lateral em TODA tela autenticada, como a skill exige. Ele fica fora
        do `<main>` de propósito: é navegação do ambiente, não conteúdo da
        página, e repetir a mesma lista dentro do conteúdo é o controle
        duplicado que a skill proíbe.

        Abaixo de `lg` ele vira uma barra horizontal rolável no topo — a tela
        não tem largura para uma coluna de 264px e o conteúdo de leitura ao
        lado. Nenhum item some; o que muda é o arranjo.
    --}}
    <div class="flex min-h-screen">
        <div class="hidden lg:block">
            <x-aluno.menu-lateral
                :ativo="$ativo ?? null"
                :curso="$cursoDoMenu ?? null"
                :concluidos="$concluidosDoMenu ?? null"
                :aulas="$aulasDoMenu ?? []"
                :aulaAtual="$aulaAtual ?? null" />
        </div>

        <div class="flex min-w-0 flex-1 flex-col">
            <x-aluno.barra-estreita :nome="$aluno['name'] ?? ''" />

            <main id="conteudo" class="flex-1 px-4 py-6 md:px-8 md:py-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
