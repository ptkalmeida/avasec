@props(['ativo' => null, 'curso' => null, 'concluidos' => null, 'aulas' => [], 'aulaAtual' => null])

{{--
    O menu inteiro vem pronto de App\Services\MenuDoAluno, o mesmo que desenha o menu das
    telas estreitas. Sem a lista de concluídos vinda da página, ele a calcula — em vez de
    usar uma lista vazia e afirmar "Nenhum curso concluído ainda" para quem concluiu. A forma
    em linha do `@php` é a única usada neste arquivo: misturar as duas formas da diretiva
    quebra o compilador do Blade.
--}}
@php($m = app(\App\Services\MenuDoAluno::class)->montar(request()->attributes->get('auth_user'), $curso, $aulaAtual, $concluidos))

{{--
    Menu lateral da área do aluno — a peça que a skill `redesenhar-ava-lms` pede em TODA área
    autenticada, recolhível e com submenus.

    ## As regras que ele materializa

    - rótulos em **azul-marinho**, sempre;
    - item ativo por **fundo e marcador lilás** + `aria-current`, nunca pela cor da fonte
      sozinha;
    - a lista de aulas aparece **uma única vez por tela**. Na tela do curso ela é a coluna da
      direita (opção A aprovada) e este expansor não é desenhado; dentro de uma aula ela se
      muda para cá, e some da área principal, que passa a ser só o conteúdo. Quem decide é a
      tela, passando ou não `$aulas` — o expansor não existe sem elas;
    - "Cursos concluídos" é **submenu**, não seção da página.

    ## Decisões de 27/10/2026 (ADR 026), que alteram o desenho de 19/10

    O menu passou a mostrar TODOS os cursos em andamento, e não só um "Meu curso": quem tem
    matrícula múltipla trocava de curso voltando ao painel. "Avaliações" é item do menu, sob o
    curso em tela, e saiu da página do curso (um caminho só, sem controle duplicado).
    "Continuar estudando" leva à primeira aula pendente. "Painel" virou "Início", "Catálogo"
    virou "Descobrir cursos", e "Ajuda" e "Suporte pedagógico" viraram "Ajuda e suporte", um
    item com duas abas.

    ## Itens que são função mudando de lugar, não função nova

    "Descobrir cursos" e o suporte entraram porque a skill removeu da tela os dois lugares onde
    essas funções viviam — o bloco "Disciplinas acadêmicas disponíveis" do painel e o cartão de
    suporte no rodapé da aula. Decisão de 19/10: tirar do lugar, não tirar a função.

    ## O estado de recolhido

    Fica em `localStorage`, lido antes da primeira pintura por `x-init`. É preferência de
    conforto do próprio navegador — não é dado do aluno, não vai para o servidor e não precisa
    sobreviver a uma troca de máquina.
--}}

<div x-data="{
        recolhido: false,
        cursosAbertos: true,
        aulasAbertas: {{ $aulaAtual !== null ? 'true' : 'false' }},
        concluidosAbertos: false,
        init() {
            try { this.recolhido = localStorage.getItem('ava_menu_recolhido') === '1'; } catch (e) {}
            this.$watch('recolhido', (v) => {
                try { localStorage.setItem('ava_menu_recolhido', v ? '1' : '0'); } catch (e) {}
            });
        },
     }"
     class="shrink-0 border-r border-ava-borda bg-white transition-all duration-200"
     :class="recolhido ? 'w-[72px]' : 'w-[264px]'">

    <nav aria-label="Navegação da área do aluno" class="sticky top-0 flex h-screen flex-col gap-1 overflow-y-auto p-3">

        {{-- Recolhido, o menu tem 72px (48 de largura útil): o símbolo e o botão não cabem lado a lado
             e se sobrepunham. Empilhados, cada um fica no seu lugar. --}}
        <div class="mb-2 flex items-center gap-2 px-1 pt-1"
             :class="recolhido ? 'flex-col justify-center' : 'justify-between'">
            {{--
                A marca do topo é o nome da escola (decisão de 20/10/2026: o
                "AVASEC" saiu do topo). Recolhido, fica só o símbolo — o nome
                continua sendo o nome acessível do link, pelo `sr-only`, para o
                leitor de tela não anunciar um link sem nome.
            --}}
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

        @foreach ($m['itens'] as $item)
            @if ($item['grupo'] === 'principal')
                <x-aluno.item-do-menu :rota="$item['rota']" :rotulo="$item['rotulo']"
                                      :ativo="$ativo === $item['chave']" :icone="$item['icone']" />

                {{-- Logo depois do "Início": o atalho para a próxima aula e, abaixo, os cursos. --}}
                @if ($item['chave'] === 'painel')
                    {{-- Sem repetir: dentro da própria aula-alvo o atalho levaria à página em que a pessoa
                         já está, e com um curso só não existe grupo "Meus cursos" envolvendo um item. --}}
                    @if ($m['continuar'] !== null && ! ($aulaAtual !== null && str_ends_with($m['continuar']['rota'], '/aula/'.$aulaAtual)))
                        <x-aluno.item-do-menu :rota="$m['continuar']['rota']" rotulo="Continuar estudando"
                                              :detalhe="$m['continuar']['aula']" icone="continuar" />
                    @endif

                    @php($unico = count($m['cursos']) === 1)

                    <div x-show="!recolhido">
                        @unless ($unico)
                        <button type="button" @click="cursosAbertos = !cursosAbertos"
                                :aria-expanded="cursosAbertos ? 'true' : 'false'"
                                aria-controls="lista-de-cursos"
                                class="flex w-full cursor-pointer items-center justify-between gap-2 rounded-[10px] px-3 py-2.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                            <span>Meus cursos</span>
                            <span class="flex items-center gap-1.5">
                                <span class="text-nota font-semibold text-escult-ink-3">{{ count($m['cursos']) }}</span>
                                <svg class="h-4 w-4 text-escult-ink-3 transition-transform" :class="cursosAbertos ? 'rotate-180' : ''"
                                     viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </button>
                        @endunless

                        <ul id="lista-de-cursos" @if ($unico) class="space-y-0.5" @else x-show="cursosAbertos" x-cloak class="mt-1 space-y-0.5 pl-2" @endif>
                            @forelse ($m['cursos'] as $c)
                                <li>
                                    {{-- `page` só na tela do curso. Dentro de uma aula ou das avaliações o
                                         curso é o ATUAL, mas a página é outra: `true` diz isso sem mentir. --}}
                                    <a href="{{ $c['rota'] }}"
                                       @if ($c['atual'] && $ativo === 'curso' && $aulaAtual === null) aria-current="page" @elseif ($c['atual']) aria-current="true" @endif
                                       class="block rounded-[10px] px-3 py-2 transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao
                                              {{ $c['atual'] ? 'bg-ava-lilas-fundo font-semibold shadow-[inset_3px_0_0_0_var(--color-ava-lilas)]' : '' }}">
                                        <span class="block text-apoio leading-snug text-ava-tinta">{{ $c['titulo'] }}</span>
                                        @if ($c['progresso'] !== '')
                                            <span class="block text-nota font-normal text-escult-ink-3">{{ $c['progresso'] }}</span>
                                        @endif
                                    </a>

                                    {{-- Sob o curso em tela: "Aulas do curso" (só a tela da aula entrega as aulas;
                                         na tela do curso a lista é a coluna da direita, e desenhá-la aqui também
                                         seria a lista duas vezes na mesma tela) e "Avaliações". Já aberto quando há
                                         uma aula em tela: fechado, esconderia justamente onde a pessoa está. --}}
                                    @if ($c['atual'])
                                        <div class="mt-0.5 space-y-0.5 pl-2">
                                            @if ($aulas !== [])
                                                <div>
                                                    <button type="button" @click="aulasAbertas = !aulasAbertas"
                                                            :aria-expanded="aulasAbertas ? 'true' : 'false'"
                                                            aria-controls="lista-de-aulas"
                                                            class="flex w-full cursor-pointer items-center justify-between gap-2 rounded-[10px] px-3 py-2 text-apoio font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                                                        <span>Aulas do curso</span>
                                                        <span class="flex items-center gap-1.5">
                                                            <span class="text-nota font-semibold text-escult-ink-3">{{ count($aulas) }}</span>
                                                            <svg class="h-4 w-4 text-escult-ink-3 transition-transform" :class="aulasAbertas ? 'rotate-180' : ''"
                                                                 viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                <path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                                            </svg>
                                                        </span>
                                                    </button>

                                                    <ul id="lista-de-aulas" x-show="aulasAbertas" x-cloak class="mt-1 space-y-0.5 pl-2">
                                                        @foreach ($aulas as $i => $aula)
                                                            @php($eAtual = $aulaAtual === $aula['id'])
                                                            <li>
                                                                <a href="{{ $c['rota'] }}/aula/{{ $aula['id'] }}"
                                                                   @if ($eAtual) aria-current="page" @endif
                                                                   class="flex items-start gap-2 rounded-[10px] px-3 py-2 text-apoio text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao
                                                                          {{ $eAtual ? 'bg-ava-lilas-fundo font-semibold shadow-[inset_3px_0_0_0_var(--color-ava-lilas)]' : '' }}">
                                                                    {{-- Estado de conclusão inequívoco, e não só por cor:
                                                                         o marcador muda de FORMA (✓ contra número). --}}
                                                                    <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-[10px] font-bold
                                                                                 {{ ($aula['concluida'] ?? false) ? 'bg-ava-lilas text-white' : 'border border-ava-borda text-escult-ink-3' }}"
                                                                          aria-hidden="true">
                                                                        {{ ($aula['concluida'] ?? false) ? '✓' : $i + 1 }}
                                                                    </span>
                                                                    <span class="min-w-0 leading-snug">
                                                                        {{ $aula['titulo'] }}
                                                                        @if ($aula['concluida'] ?? false)
                                                                            <span class="sr-only">(aula concluída)</span>
                                                                        @endif
                                                                    </span>
                                                                </a>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif

                                            <x-aluno.item-do-menu :rota="$c['avaliacoes']" rotulo="Avaliações"
                                                                  :ativo="$ativo === 'avaliacoes'" icone="avaliacao" />
                                        </div>
                                    @endif
                                </li>
                            @empty
                                <li class="px-3 py-2 text-apoio text-escult-ink-3">Você ainda não tem curso em andamento.</li>
                            @endforelse

                            {{-- Submenu, e não seção da página: decisão da skill. Fica dentro de "Meus cursos". --}}
                            @if ($m['concluidos'] !== [])
                            <li>
                                <button type="button" @click="concluidosAbertos = !concluidosAbertos"
                                        :aria-expanded="concluidosAbertos ? 'true' : 'false'"
                                        aria-controls="lista-de-concluidos"
                                        class="flex w-full cursor-pointer items-center justify-between gap-2 rounded-[10px] px-3 py-2 text-apoio font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                                    <span>Cursos concluídos</span>
                                    <span class="flex items-center gap-1.5">
                                        <span class="text-nota font-semibold text-escult-ink-3">{{ count($m['concluidos']) }}</span>
                                        <svg class="h-4 w-4 text-escult-ink-3 transition-transform" :class="concluidosAbertos ? 'rotate-180' : ''"
                                             viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </button>

                                <ul id="lista-de-concluidos" x-show="concluidosAbertos" x-cloak class="mt-1 space-y-0.5 pl-2">
                                    @forelse ($m['concluidos'] as $concluido)
                                        <li>
                                            <a href="/aluno/curso/{{ $concluido['slug'] ?? $concluido['id'] }}"
                                               class="block rounded-[10px] px-3 py-2 text-apoio leading-snug text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                                                {{ $concluido['titulo'] }}
                                            </a>
                                        </li>
                                    @empty
                                        <li class="px-3 py-2 text-apoio text-escult-ink-3">Nenhum curso concluído ainda.</li>
                                    @endforelse
                                </ul>
                            </li>
                            @endif
                        </ul>
                    </div>
                @endif
            @endif
        @endforeach

        <div class="mt-auto space-y-1 border-t border-ava-borda pt-2">
            @foreach ($m['itens'] as $item)
                @if ($item['grupo'] === 'rodape')
                    {{-- "Ajuda e suporte" acende nas duas abas (dúvidas e conversa com o gestor). --}}
                    <x-aluno.item-do-menu :rota="$item['rota']" :rotulo="$item['rotulo']"
                                          :ativo="$ativo === $item['chave'] || ($item['chave'] === 'ajuda' && $ativo === 'suporte')"
                                          :icone="$item['icone']" />
                @endif
            @endforeach
        </div>
    </nav>
</div>
