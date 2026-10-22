@props(['titulo', 'contexto' => null, 'nome' => ''])

{{--
    Topbar do back-office (D §1.4.2): faixa branca no topo da área de conteúdo.

    À esquerda, o botão de menu (só abaixo de 992px), o título da página e uma
    linha de contexto. À direita, a área do usuário: avatar com iniciais, nome e
    perfil sempre visíveis, e um menu suspenso com "Meu perfil", "Trocar senha"
    e "Sair" — este último em vermelho, por ser ação de sessão.

    O "Sair" é um formulário POST: um link GET deslogaria quem abrisse a página
    por um `<img src="/sair">` (ver a nota em `routes/web.php`).
--}}
@php
    $partes = preg_split('/\s+/', trim((string) $nome), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $iniciais = mb_strtoupper(
        mb_substr($partes[0] ?? '?', 0, 1).(count($partes) > 1 ? mb_substr($partes[count($partes) - 1], 0, 1) : ''),
    );
@endphp

<header class="sticky top-0 z-20 flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3 lg:px-6">
    <button type="button"
            @click="menuAberto = true"
            :aria-expanded="menuAberto ? 'true' : 'false'"
            aria-controls="menu-lateral"
            class="rounded-md p-2 text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary lg:hidden">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
        <span class="sr-only">Abrir o menu</span>
    </button>

    <div class="min-w-0 flex-1">
        <h1 class="truncate text-[1.2rem] font-bold leading-tight text-slate-900">{{ $titulo }}</h1>
        @if ($contexto !== null && $contexto !== '')
            <p class="truncate text-xs text-slate-500">{{ $contexto }}</p>
        @endif
    </div>

    <div class="relative" x-data="{ aberto: false }" @keydown.escape="aberto = false" @click.outside="aberto = false">
        <button type="button"
                @click="aberto = !aberto"
                :aria-expanded="aberto ? 'true' : 'false'"
                aria-haspopup="menu"
                class="flex items-center gap-3 rounded-md px-2 py-1.5 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-white" aria-hidden="true">{{ $iniciais }}</span>
            <span class="hidden min-w-0 text-left leading-tight sm:block">
                <span class="block max-w-48 truncate text-[0.85rem] font-semibold text-slate-900">{{ $nome }}</span>
                <span class="block text-[0.7rem] text-slate-500">Administrador</span>
            </span>
            <i class="fa-solid fa-chevron-down text-[0.65rem] text-slate-400" aria-hidden="true"></i>
        </button>

        <div x-cloak x-show="aberto" x-transition.opacity role="menu" aria-label="Menu do usuário"
             class="absolute right-0 top-full z-30 mt-2 w-56 overflow-hidden rounded-[10px] border border-slate-200 bg-white py-1 shadow-elevada">
            <a href="/admin/perfil" role="menuitem"
               class="flex items-center gap-3 px-4 py-2 text-[0.85rem] text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none">
                <i class="fa-regular fa-user w-4 text-center text-slate-500" aria-hidden="true"></i> Meu perfil
            </a>
            <a href="/admin/perfil/senha" role="menuitem"
               class="flex items-center gap-3 px-4 py-2 text-[0.85rem] text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none">
                <i class="fa-solid fa-key w-4 text-center text-slate-500" aria-hidden="true"></i> Trocar senha
            </a>
            <form method="POST" action="/sair" class="border-t border-slate-200">
                @csrf
                <button type="submit" role="menuitem"
                        class="flex w-full items-center gap-3 px-4 py-2 text-left text-[0.85rem] font-medium text-red-600 hover:bg-red-50 focus:bg-red-50 focus:outline-none">
                    <i class="fa-solid fa-right-from-bracket w-4 text-center" aria-hidden="true"></i> Sair
                </button>
            </form>
        </div>
    </div>
</header>
