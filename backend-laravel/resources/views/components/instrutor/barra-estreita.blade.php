@props(['nome' => '', 'curso' => null])

{{--
    Topo da área do instrutor — irmão do da área do aluno.

    Em telas largas carrega a identificação e a saída; abaixo de `lg` ganha o
    botão que abre a navegação, porque ali o menu lateral não cabe ao lado do
    conteúdo. A lista é a MESMA do menu lateral, com as mesmas condições: duas
    listas para o mesmo menu divergem, e foi o que aconteceu na área do aluno —
    o suporte continuou apontando para a tela fechada aqui depois de corrigido
    lá. O teste confere as duas.

    "Sair" é formulário, não link: encerrar sessão por GET deixaria um
    `<img src="/sair">` deslogar quem abrisse a página.
--}}

@php($ref = $curso === null ? null : ($curso['slug'] ?? $curso['id']))

<header x-data="{ navAberta: false }" class="border-b border-ava-borda bg-white">
    <div class="flex items-center justify-between gap-3 px-4 py-3 md:px-8">

        <button type="button" @click="navAberta = !navAberta"
                :aria-expanded="navAberta ? 'true' : 'false'"
                aria-controls="nav-estreita-instrutor"
                class="cursor-pointer rounded-[10px] border border-ava-borda p-2.5 text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao lg:hidden">
            <span class="sr-only">Abrir a navegação da área do instrutor</span>
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M2 5.75A.75.75 0 0 1 2.75 5h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 5.75Zm0 4.25a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 10Zm0 4.25a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd" />
            </svg>
        </button>

        <div class="min-w-0">
            <p class="truncate text-rotulo font-semibold text-ava-tinta">{{ $nome }}</p>
            <p class="text-apoio text-escult-ink-3">Gestor de curso</p>
        </div>

        <form method="POST" action="/sair" class="shrink-0">
            @csrf
            <button type="submit"
                    class="cursor-pointer rounded-[10px] border border-ava-borda px-4 py-2 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                Sair
            </button>
        </form>
    </div>

    <div id="nav-estreita-instrutor" x-show="navAberta" x-cloak class="border-t border-ava-borda px-2 py-2 lg:hidden">
        <nav aria-label="Navegação da área do instrutor (telas estreitas)" class="grid grid-cols-2 gap-1">
            @foreach (array_values(array_filter([
                ['/inst', 'Meus cursos'],
                $ref === null ? null : ['/inst/curso/'.$ref, 'Gestão do curso'],
                $ref !== null && config('features.modulosAulas') === true ? ['/inst/curso/'.$ref.'/grade-curricular', 'Grade curricular'] : null,
                $ref !== null && config('features.quizSimples') === true ? ['/inst/curso/'.$ref.'/avaliacoes', 'Avaliações'] : null,
                $ref !== null && config('features.dadosGerenciais') === true ? ['/inst/curso/'.$ref.'/alunos', 'Alunos'] : null,
                $ref !== null && \App\Support\CanalDeMensagens::abertoParaInstrutor() ? ['/inst/curso/'.$ref.'/mensagens', 'Mensagens'] : null,
                ['/', 'Portal'],
            ])) as [$rota, $rotulo])
                <a href="{{ $rota }}"
                   class="rounded-[10px] px-3 py-2.5 text-rotulo font-semibold text-ava-tinta hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                    {{ $rotulo }}
                </a>
            @endforeach
        </nav>
    </div>
</header>
