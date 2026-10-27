@props(['ativo' => null])

{{--
    Cabeçalho institucional do portal público (fase M2 do plano 16).

    Documento D §2.5 torna marca, header mínimo e footer OBRIGATÓRIOS na área
    pública. As telas migradas na M1 saíram sem os dois — o layout dizia isso
    com todas as letras — e esta é a peça que fecha o vão. Por isso ela vem
    antes das telas novas: as cinco telas do redesenho dependem dela.

    A estrutura do menu vem de `config/menu_publico.php`, réplica da fonte que o
    React ainda usa. Não há segundo lugar onde acrescentar item de menu.

    ## Como o item ativo é marcado

    A skill `redesenhar-ava-lms` é explícita: rótulo em azul-marinho e estado
    ativo por **fundo, borda ou marcador** — nunca trocando só a cor da fonte.
    Aqui o ativo ganha fundo lilás claro e um filete lilás embaixo, e o rótulo
    continua marinho. Quem não distingue as duas cores continua vendo o estado,
    porque ele também vai em `aria-current` e na mudança de forma.

    Numa página de dentro de um grupo quem acende é o GRUPO: em Notícias,
    acende "A Escola". É a mesma regra do `entradaAtiva` do React.

    ## Entrar e Cadastre-se

    Os dois modais ainda vivem no React (a sessão do Laravel é item da M2 e
    exige validação humana). Enquanto isso, os botões levam ao SPA com
    `?janela=`, que é a convenção de janela sobreposta que o painel do aluno já
    usa. Nada do fluxo de autenticação muda aqui — só se ganha um jeito de
    chegar nele a partir de uma tela Blade.
--}}

<header class="sticky top-0 z-50 border-b border-ava-borda bg-white">
    <a href="#conteudo"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-3 focus:z-50 focus:rounded-[10px] focus:bg-ava-acao focus:px-4 focus:py-2 focus:text-rotulo focus:font-semibold focus:text-white">
        Pular para o conteúdo
    </a>

    <div x-data="{ menuMovel: false, grupoAberto: null }"
         @keydown.escape.window="grupoAberto = null; menuMovel = false"
         class="mx-auto max-w-7xl px-4 md:px-8">

        <div class="flex items-center justify-between gap-2 py-3 sm:gap-4">

            {{-- Marca. O logotipo é a porta de volta ao início; por isso "Início"
                 não ocupa vaga no menu. --}}
            <a href="/" title="Voltar ao portal inicial"
               class="flex shrink-0 select-none items-center gap-2 rounded-[10px] focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                <x-publico.marca-avasec />
            </a>

            <nav class="hidden items-center gap-1 lg:flex" aria-label="Navegação principal">
                @foreach (config('menu_publico') as $entrada)
                    @php($estaAtiva = $ativo === $entrada['rotulo'])

                    @if ($entrada['tipo'] === 'link')
                        <a href="{{ $entrada['rota'] }}"
                           @if ($estaAtiva) aria-current="page" @endif
                           class="flex items-center whitespace-nowrap rounded-[10px] px-4 py-2.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao
                                  {{ $estaAtiva ? 'bg-ava-lilas-fundo shadow-[inset_0_-3px_0_0_var(--color-ava-lilas)]' : '' }}">
                            {{ $entrada['rotulo'] }}
                        </a>
                    @else
                        <div class="relative" @click.outside="grupoAberto === '{{ $entrada['id'] }}' && (grupoAberto = null)">
                            {{-- Abre no CLIQUE, não no hover: hover exige mira e mão
                                 firme, e o público declarado é de adultos com pouca
                                 familiaridade digital. --}}
                            <button type="button"
                                    @click="grupoAberto = (grupoAberto === '{{ $entrada['id'] }}' ? null : '{{ $entrada['id'] }}')"
                                    :aria-expanded="grupoAberto === '{{ $entrada['id'] }}' ? 'true' : 'false'"
                                    aria-haspopup="true"
                                    @if ($estaAtiva) aria-current="page" @endif
                                    class="flex cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-[10px] px-4 py-2.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao
                                           {{ $estaAtiva ? 'bg-ava-lilas-fundo shadow-[inset_0_-3px_0_0_var(--color-ava-lilas)]' : '' }}">
                                <span>{{ $entrada['rotulo'] }}</span>
                                <svg class="h-4 w-4 text-escult-ink-3 transition-transform"
                                     :class="grupoAberto === '{{ $entrada['id'] }}' ? 'rotate-180' : ''"
                                     viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </button>

                            <div x-show="grupoAberto === '{{ $entrada['id'] }}'" x-cloak x-transition.opacity.duration.150ms
                                 class="absolute left-0 top-[calc(100%+8px)] z-50 w-[280px] rounded-[14px] border border-ava-borda bg-white p-2 shadow-xl">
                                @foreach ($entrada['itens'] as $item)
                                    <a href="{{ $item['rota'] }}"
                                       class="block rounded-[10px] px-3.5 py-3 transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                                        <span class="block text-rotulo font-semibold leading-snug text-ava-tinta">{{ $item['rotulo'] }}</span>
                                        <span class="mt-0.5 block text-apoio leading-snug text-escult-ink-3">{{ $item['descricao'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </nav>

            {{-- Busca a partir de 1280px, e não de 768px: medido no React em
                 1024, os filhos do cabeçalho somavam 1170px numa barra de 1009 e
                 a página inteira rolava na horizontal. O menu alcança tudo o que
                 a busca alcança, então ela é a de menor custo para ceder espaço. --}}
            <div class="hidden xl:block">
                <livewire:publico.busca-do-portal />
            </div>

            <div class="flex shrink-0 items-center gap-2">
                {{-- Atribuição em linha, com parênteses — a mesma forma usada
                     no alto deste arquivo. A forma de BLOCO não pode aparecer
                     aqui: quando as duas convivem no mesmo arquivo, o
                     fechamento do bloco casa com a primeira abertura em linha,
                     e da metade do arquivo em diante a view sai como texto cru,
                     com erro de sintaxe no fim do compilado. Custou um 500 em
                     todas as telas públicas de uma vez. --}}
                @php($painelDoPapel = ['student' => '/aluno', 'instructor' => '/inst', 'admin' => '/admin'])
                @php($painel = $painelDoPapel[$usuarioLogado['papel'] ?? ''] ?? null)

                @if ($painel !== null)
                    {{-- Quem já entrou não vê "Entrar": vê o caminho de volta ao
                         painel dele, como no cabeçalho do React. Sem isto, a
                         pessoa logada leria "Entrar" e acharia que a sessão caiu. --}}
                    <a href="{{ $painel }}"
                       class="whitespace-nowrap rounded-[10px] bg-ava-acao px-[22px] py-3 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                        Meu painel
                    </a>

                    {{-- Formulário, nunca link: sair por GET deixaria um
                         `<img src="/sair">` deslogar quem abrisse a página. --}}
                    <form method="POST" action="/sair" class="hidden sm:block">
                        @csrf
                        <button type="submit"
                                class="cursor-pointer whitespace-nowrap rounded-[10px] border border-ava-borda bg-white px-[18px] py-3 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                            Sair
                        </button>
                    </form>
                @else
                    <a href="/entrar"
                       class="whitespace-nowrap rounded-[10px] bg-ava-acao px-[22px] py-3 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                        Entrar
                    </a>
                    <a href="/cadastro"
                       class="hidden whitespace-nowrap rounded-[10px] border border-ava-acao bg-white px-[18px] py-3 text-rotulo font-semibold text-ava-acao transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao sm:block">
                        Cadastre-se
                    </a>
                @endif

                <button type="button"
                        @click="menuMovel = !menuMovel"
                        :aria-expanded="menuMovel ? 'true' : 'false'"
                        aria-controls="menu-movel"
                        class="cursor-pointer rounded-[10px] border border-ava-borda p-2.5 text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao lg:hidden">
                    <span class="sr-only">Abrir o menu de navegação</span>
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path x-show="!menuMovel" fill-rule="evenodd" d="M2 5.75A.75.75 0 0 1 2.75 5h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 5.75Zm0 4.25a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 10Zm0 4.25a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd" />
                        <path x-show="menuMovel" x-cloak fill-rule="evenodd" d="M5.22 5.22a.75.75 0 0 1 1.06 0L10 8.94l3.72-3.72a.75.75 0 1 1 1.06 1.06L11.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06L10 11.06l-3.72 3.72a.75.75 0 0 1-1.06-1.06L8.94 10 5.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        </div>

        {{-- Menu em telas estreitas: lista aberta, sem suspensão sobre a página.
             Um painel que cobre o conteúdo é onde a pessoa clica atrás dele sem
             entender; aqui ele empurra a página para baixo. --}}
        <div id="menu-movel" x-show="menuMovel" x-cloak x-transition.opacity.duration.150ms class="border-t border-ava-borda py-3 lg:hidden">
            <nav aria-label="Navegação principal (telas estreitas)" class="space-y-1">
                @foreach (config('menu_publico') as $entrada)
                    @if ($entrada['tipo'] === 'link')
                        <a href="{{ $entrada['rota'] }}"
                           @if ($ativo === $entrada['rotulo']) aria-current="page" @endif
                           class="block rounded-[10px] px-3.5 py-3 text-rotulo font-semibold text-ava-tinta hover:bg-ava-faixa
                                  {{ $ativo === $entrada['rotulo'] ? 'bg-ava-lilas-fundo shadow-[inset_3px_0_0_0_var(--color-ava-lilas)]' : '' }}">
                            {{ $entrada['rotulo'] }}
                        </a>
                    @else
                        <div class="pt-2">
                            <span class="block px-3.5 pb-1 text-sobretitulo uppercase text-escult-ink-3">{{ $entrada['rotulo'] }}</span>
                            @foreach ($entrada['itens'] as $item)
                                <a href="{{ $item['rota'] }}"
                                   class="block rounded-[10px] px-3.5 py-3 text-rotulo font-semibold text-ava-tinta hover:bg-ava-faixa">
                                    {{ $item['rotulo'] }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </nav>
        </div>
    </div>
</header>
