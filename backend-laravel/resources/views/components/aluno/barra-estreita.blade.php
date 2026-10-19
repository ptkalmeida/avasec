@props(['nome' => ''])

{{--
    Topo da área do aluno.

    Em telas largas carrega só a identificação de quem está logado e a saída. Em
    telas estreitas ele ganha o botão que abre a navegação, porque abaixo de
    `lg` o menu lateral não cabe ao lado do conteúdo.

    **Sem foto de perfil**: a skill é explícita — identifica-se a pessoa por
    texto. Não é economia de layout; é que não existe foto de usuário no
    sistema, e um avatar com inicial colorida pareceria haver.

    O botão de saída diz "Portal", e não "Sair", porque é o que ele faz: leva ao
    portal, onde o "Sair" de verdade está. Encerrar a sessão daqui exigiria um
    segundo caminho de logout — e o dia em que o JWT der lugar à sessão do
    Laravel, os dois teriam de mudar juntos. É assim que fica sessão órfã.
    Rótulo que promete o que não faz é pior que um clique a mais.
--}}

<header x-data="{ navAberta: false }" class="border-b border-ava-borda bg-white">
    <div class="flex items-center justify-between gap-3 px-4 py-3 md:px-8">

        <button type="button" @click="navAberta = !navAberta"
                :aria-expanded="navAberta ? 'true' : 'false'"
                aria-controls="nav-estreita"
                class="cursor-pointer rounded-[10px] border border-ava-borda p-2.5 text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao lg:hidden">
            <span class="sr-only">Abrir a navegação da área do aluno</span>
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M2 5.75A.75.75 0 0 1 2.75 5h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 5.75Zm0 4.25a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 10Zm0 4.25a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd" />
            </svg>
        </button>

        <div class="min-w-0 text-right lg:text-left">
            <span class="block truncate text-rotulo font-semibold text-ava-tinta">{{ $nome }}</span>
            <span class="block text-nota text-escult-ink-3">Aluno credenciado</span>
        </div>

        <a href="/"
           class="shrink-0 rounded-[10px] border border-ava-borda px-4 py-2 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
            Portal
        </a>
    </div>

    <div id="nav-estreita" x-show="navAberta" x-cloak class="border-t border-ava-borda px-2 py-2 lg:hidden">
        <nav aria-label="Navegação da área do aluno (telas estreitas)" class="grid grid-cols-2 gap-1">
            @foreach ([
                ['/aluno', 'Painel'],
                ['/aluno/catalogo', 'Catálogo'],
                ['/aluno/certificados', 'Certificados'],
                ['/aluno/biblioteca', 'Biblioteca'],
                ['/aluno/mensagens', 'Suporte pedagógico'],
                ['/aluno/duvidas', 'Ajuda'],
                ['/aluno/perfil', 'Perfil'],
            ] as [$rota, $rotulo])
                <a href="{{ $rota }}"
                   class="rounded-[10px] px-3 py-2.5 text-rotulo font-semibold text-ava-tinta hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                    {{ $rotulo }}
                </a>
            @endforeach
        </nav>
    </div>
</header>
