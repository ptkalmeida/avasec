<!DOCTYPE html>
<html lang="pt-BR" class="tema-ava">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ?? 'AVASEC' }} — Escola Estadual da Cultura RJ</title>
    @if (!empty($descricao))
        <meta name="description" content="{{ $descricao }}">
    @endif

    {{--
        `tema-ava` no <html> liga a variante `ava:` do Tailwind, declarada em
        src/index.css. Sem ela, as regras `ava:*` ficam inertes — foi assim que
        o Perfil do admin passou a rolar na horizontal em 320px, porque as
        regras de quebra do cabeçalho só valiam sob essa classe.
    --}}
    @vite(['backend-laravel/resources/css/blade.css', 'backend-laravel/resources/js/blade.ts'])

    {{--
        O Livewire injeta os próprios assets sozinho; estas diretivas tornam o
        ponto de injeção EXPLÍCITO, para que o <head> diga o que carrega em vez
        de depender de um comportamento implícito do pacote.
    --}}
    @livewireStyles
</head>
<body class="flex min-h-screen flex-col bg-white font-sans text-ava-tinta antialiased">
    {{--
        Cabeçalho e rodapé institucionais passaram a existir em Blade (M2, em
        19/10/2026). Antes disso as telas migradas saíam sem eles, e era por isso
        que a M1 começou pelas páginas de conteúdo — onde a ausência era visível
        e inofensiva — e não pela home, onde seria a tela inteira.

        `$ativo` é o rótulo da entrada do menu que deve acender. A página passa
        o rótulo do GRUPO quando ela vive dentro de um: em Notícias, acende
        "A Escola".
    --}}
    <x-publico.cabecalho :ativo="$ativo ?? null" />

    <main id="conteudo" class="flex-1">
        {{ $slot }}
    </main>

    <x-publico.rodape />

    @livewireScripts
</body>
</html>
