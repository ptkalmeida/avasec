@props(['ativo' => null, 'curso' => null, 'aguardando' => null])

{{--
    Sem o número vindo da página, o menu o calcula. É a mesma armadilha que
    o menu do aluno já pagou com "Cursos concluídos": toda tela nova que
    esquecesse de passar a contagem faria o menu dizer que não há ninguém
    esperando resposta. A forma em linha do `@php` é a única usada neste
    arquivo — misturar as duas formas quebra o compilador do Blade.
--}}
@php($aguardando = $aguardando ?? (\App\Support\CanalDeMensagens::abertoParaInstrutor()
    ? app(\App\Services\CaixaDeMensagens::class)->quantasAguardam(request()->attributes->get('auth_user'))
    : 0))

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

    Cada seção responde pela SUA flag, como na API. Mensagens depende da flag
    das mensagens diretas e da permissão que o administrador controla — e NÃO
    do fórum, que é o mural do curso (ver `App\Support\CanalDeMensagens`).
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

        {{-- Sem contagem aqui: o aviso fica onde o trabalho está (Mensagens), e
             a fila "A fazer" da própria tela `/inst` lista tudo. Um número nos
             dois lugares contaria a mesma pendência duas vezes. --}}
        <x-instrutor.item-do-menu rota="/inst" rotulo="Meus cursos"
            :ativo="$ativo === 'cursos'" icone="cursos" />

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

        @endif

        {{-- Mensagens fica FORA do bloco do curso: a dúvida do aluno vem de uma
             aula, mas quem responde quer ver todas de uma vez, e não uma caixa
             por curso (decisão de 21/10). Dentro do bloco, o item só existia
             depois de escolher um curso — e a caixa é de todos eles. --}}
        @if (\App\Support\CanalDeMensagens::abertoParaInstrutor())
            <x-instrutor.item-do-menu rota="/inst/mensagens" rotulo="Mensagens"
                :ativo="$ativo === 'mensagens'" icone="mensagens" :contagem="$aguardando" />
        @endif

        <div class="mt-auto space-y-1 border-t border-ava-borda pt-2">
            <x-instrutor.item-do-menu rota="/" rotulo="Portal" :ativo="false" icone="portal" />
        </div>
    </nav>
</div>
