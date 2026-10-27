<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ?? 'Painel administrativo' }} — AVASEC</title>

    {{-- Área autenticada não é para buscador. --}}
    <meta name="robots" content="noindex, nofollow">

    @vite(['backend-laravel/resources/css/admin.css', 'backend-laravel/resources/js/blade.ts'])
    @livewireStyles
</head>
{{--
    Moldura do back-office: Anexo D (Protocolo TI-SECEC), Seção 1, vinculante.

    Três regiões: sidebar (fixa à esquerda), topbar (fixa no topo da ÁREA DE
    CONTEÚDO, não do sistema) e conteúdo. A sidebar é fixa a partir de 992px; abaixo
    disso é off-canvas, aberta por um botão de menu na topbar, com camada
    semitransparente atrás, e fecha ao tocar fora ou em um item (D §1.4.3).

    O estado aberto/fechado é do Alpine (já embutido no Livewire): é só o
    desenho do menu, não dado de negócio.
--}}
<body class="bg-pagina font-sans text-sm text-slate-900 antialiased"
      x-data="{ menuAberto: false }"
      @keydown.escape.window="menuAberto = false">
    <a href="#conteudo"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-3 focus:z-[60] focus:rounded-md focus:bg-primary focus:px-4 focus:py-2 focus:font-semibold focus:text-white">
        Pular para o conteúdo
    </a>

    {{-- Camada atrás da sidebar aberta (só abaixo de 992px). --}}
    <div x-cloak x-show="menuAberto" x-transition.opacity
         @click="menuAberto = false"
         class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" aria-hidden="true"></div>

    <x-admin.menu-lateral :ativo="$ativo ?? null" />

    <div class="flex min-h-screen min-w-0 flex-col lg:pl-64 print:pl-0">
        <x-admin.barra-superior
            :titulo="$cabecalho ?? ($titulo ?? 'Painel administrativo')"
            :contexto="$contexto ?? null"
            :nome="$admin['nome'] ?? ''" />

        <main id="conteudo" class="flex-1 p-4 lg:p-6">
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
</body>
</html>
