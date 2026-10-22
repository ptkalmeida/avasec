@props(['ativo' => null])

{{--
    Sidebar do back-office (D §1.4.1): fundo escuro, marca no topo, itens
    agrupados com rótulo em maiúsculas, um ícone FontAwesome por item, item da
    rota ativa em azul sobre fundo translúcido.

    Os itens vêm de `App\Support\MenuDoAdmin`, que respeita as flags de produto.
    Abaixo de 992px a sidebar sai da tela (`-translate-x-full`) e o botão da
    topbar a traz de volta; em telas largas ela é sempre visível.
--}}
<aside id="menu-lateral"
       class="fixed inset-y-0 left-0 z-40 flex w-64 print:hidden flex-col bg-sidebar text-slate-300 transition-[transform,visibility] duration-200 lg:visible lg:translate-x-0"
       :class="menuAberto ? 'visible translate-x-0 shadow-elevada' : 'invisible -translate-x-full'">

    <div class="flex items-center gap-3 border-b border-white/10 px-4 py-4">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary text-white" aria-hidden="true">
            <i class="fa-solid fa-graduation-cap"></i>
        </span>
        <div class="min-w-0 leading-tight">
            <p class="truncate text-[0.95rem] font-bold text-white">AVASEC</p>
            <p class="truncate text-[0.7rem] text-slate-400">Escola Estadual da Cultura</p>
        </div>
    </div>

    <nav aria-label="Navegação do painel administrativo" class="flex-1 overflow-y-auto px-3 py-3">
        @foreach (\App\Support\MenuDoAdmin::grupos() as $grupo)
            @if ($grupo['titulo'] !== '')
                <p class="mb-1 mt-4 px-3 text-[0.65rem] font-bold uppercase tracking-[0.08em] text-slate-500">{{ $grupo['titulo'] }}</p>
            @endif

            <ul class="space-y-0.5">
                @foreach ($grupo['itens'] as $item)
                    @php($atual = $ativo === $item['id'])
                    <li>
                        <a href="{{ $item['href'] }}"
                           @click="menuAberto = false"
                           @if ($atual) aria-current="page" @endif
                           class="flex items-center gap-3 rounded-md px-3 py-2 text-[0.85rem] font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary
                                  {{ $atual ? 'bg-primary/15 text-primary' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                            <i class="fa-solid {{ $item['icone'] }} w-4 shrink-0 text-center" aria-hidden="true"></i>
                            <span class="min-w-0">{{ $item['rotulo'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endforeach
    </nav>

    <div class="border-t border-white/10 px-4 py-3 text-[0.7rem] text-slate-500">
        <a href="/" class="inline-flex items-center gap-2 hover:text-slate-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Ir ao portal
        </a>
    </div>
</aside>
