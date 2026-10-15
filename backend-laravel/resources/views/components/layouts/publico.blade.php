<!DOCTYPE html>
<html lang="pt-BR" class="tema-ava">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ?? 'AVASEC' }} — Escola Estadual da Cultura</title>
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
<body class="bg-white font-sans text-ava-tinta antialiased">
    {{--
        Cabeçalho e rodapé institucionais ainda vivem no React (fase M2). Enquanto
        isso, as telas migradas saem sem eles — e é por isso que a M1 começa pelas
        páginas de conteúdo, onde a ausência é visível e inofensiva, e não pela
        home, onde seria a tela inteira.
    --}}
    <main>
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
