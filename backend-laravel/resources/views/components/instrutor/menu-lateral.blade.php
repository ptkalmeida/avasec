@props(['ativo' => null, 'curso' => null, 'pendencias' => 0])

{{--
    Menu lateral da área do instrutor.

    Mesma peça do menu do aluno, e as mesmas regras: rótulo em azul-marinho,
    ativo por fundo e marcador lilás mais `aria-current`, recolhível com o
    estado guardado no navegador.

    ## O curso vem antes da seção

    Grade, avaliações e alunos são sempre de UM curso — é a regra que o painel
    React passou a seguir quando o curso entrou no endereço, e que este menu
    materializa: sem curso escolhido, o menu tem um item só, "Meus cursos". Com
    curso, as seções dele aparecem abaixo. Oferecer "Grade curricular" antes da
    escolha era oferecer a grade de um curso que ninguém escolheu (o painel
    antigo caía no primeiro da lista sozinho).

    ## As seções que não aparecem

    Cada seção responde pela SUA flag, como na API. Mensagens depende de três
    coisas ao mesmo tempo (`forum`, `mensagensDiretas` e a permissão que o admin
    controla), e com `forum` desligada ela não aparece para ninguém.
--}}

<div x-data="{
        recolhido: false,
        init() {
            try { this.recolhido = localStorage.getItem('ava_menu_recolhido') === '1'; } catch (e) {}
            this.$watch('recolhido', (v) => {
                try { localStorage.setItem('ava_menu_recolhido', v ? '1' : '0'); } catch (e) {}
            });
        },
     }"
     class="shrink-0 border-r border-ava-borda bg-white transition-all duration-200"
     :class="recolhido ? 'w-[72px]' : 'w-[264px]'">

    <nav aria-label="Navegação da área do instrutor" class="sticky top-0 flex h-screen flex-col gap-1 overflow-y-auto p-3">

        <div class="mb-2 flex items-center justify-between gap-2 px-1 pt-1">
            <a href="/" class="flex min-w-0 items-center gap-2 rounded-[10px] focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao"
               title="Voltar ao portal inicial">
                <x-publico.simbolo-da-escola classe="h-8 w-8" />
                <span class="whitespace-nowrap font-sans text-sm font-extrabold leading-tight tracking-tight text-ava-tinta"
                      :class="recolhido ? 'sr-only' : ''">
                    Escola Estadual<br>da Cultura RJ
                </span>
            </a>

            <button type="button" @click="recolhido = !recolhido"
                    :aria-expanded="recolhido ? 'false' : 'true'"
                    class="cursor-pointer rounded-[10px] p-2 text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                <span class="sr-only" x-text="recolhido ? 'Expandir o menu' : 'Recolher o menu'">Recolher o menu</span>
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M2 5.75A.75.75 0 0 1 2.75 5h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 5.75Zm0 4.25a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 10Zm0 4.25a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>

        <x-instrutor.item-do-menu rota="/inst" rotulo="Meus cursos"
            :ativo="$ativo === 'cursos'" icone="cursos" :contagem="$pendencias" />

        @if ($curso !== null)
            @php($ref = $curso['slug'] ?? $curso['id'])

            <p x-show="!recolhido" class="mt-3 truncate px-3 text-sobretitulo uppercase text-escult-ink-3" title="{{ $curso['title'] }}">
                {{ $curso['title'] }}
            </p>

            <x-instrutor.item-do-menu :rota="'/inst/curso/'.$ref" rotulo="Gestão do curso"
                :ativo="$ativo === 'gestao'" icone="gestao" />

            @if (config('features.modulosAulas') === true)
                <x-instrutor.item-do-menu :rota="'/inst/curso/'.$ref.'/grade-curricular'" rotulo="Grade curricular"
                    :ativo="$ativo === 'grade'" icone="grade" />
            @endif

            @if (config('features.quizSimples') === true)
                <x-instrutor.item-do-menu :rota="'/inst/curso/'.$ref.'/avaliacoes'" rotulo="Avaliações"
                    :ativo="$ativo === 'avaliacoes'" icone="avaliacoes" />
            @endif

            @if (config('features.dadosGerenciais') === true)
                <x-instrutor.item-do-menu :rota="'/inst/curso/'.$ref.'/alunos'" rotulo="Alunos"
                    :ativo="$ativo === 'alunos'" icone="alunos" />
            @endif

            {{-- Mensagens só existe com as três condições, como na API e como no
                 painel React: a flag do fórum, a das mensagens diretas e a
                 permissão que o admin controla. --}}
            @if (\App\Support\CanalDeMensagens::abertoParaInstrutor())
                <x-instrutor.item-do-menu :rota="'/inst/curso/'.$ref.'/mensagens'" rotulo="Mensagens"
                    :ativo="$ativo === 'mensagens'" icone="mensagens" />
            @endif
        @endif

        <div class="mt-auto space-y-1 border-t border-ava-borda pt-2">
            <x-instrutor.item-do-menu rota="/" rotulo="Portal" :ativo="false" icone="portal" />
        </div>
    </nav>
</div>
