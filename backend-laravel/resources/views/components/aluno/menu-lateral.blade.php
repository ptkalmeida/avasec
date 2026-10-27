@props(['ativo' => null, 'curso' => null, 'concluidos' => null, 'aulas' => [], 'aulaAtual' => null])

{{--
    Sem a lista vinda da página, o menu a calcula — em vez de usar uma lista
    vazia e afirmar "Nenhum curso concluído ainda" para quem concluiu. Ver
    App\Services\MenuDoAluno. A forma em linha é a única usada neste arquivo:
    misturar as duas formas da diretiva quebra o compilador do Blade.
--}}
@php($concluidos = $concluidos ?? app(\App\Services\MenuDoAluno::class)->concluidos(request()->attributes->get('auth_user')))

{{--
    Menu lateral da área do aluno — a peça que a skill `redesenhar-ava-lms` pede
    em TODA área autenticada, recolhível e com submenus.

    ## As regras que ele materializa

    - rótulos em **azul-marinho**, sempre;
    - item ativo por **fundo e marcador lilás** + `aria-current`, nunca pela cor
      da fonte sozinha;
    - a lista de aulas aparece **uma única vez por tela**. Na tela do curso ela é
      a coluna da direita (opção A aprovada) e este expansor não é desenhado;
      dentro de uma aula ela se muda para cá, e some da área principal, que
      passa a ser só o conteúdo. Quem decide é a tela, passando ou não `$aulas`
      — o expansor não existe sem elas;
    - "Cursos concluídos" é **submenu**, não seção da página.

    ## Itens que são função mudando de lugar, não função nova

    "Catálogo" e "Suporte pedagógico" entraram porque a skill removeu da tela os
    dois lugares onde essas funções viviam — o bloco "Disciplinas acadêmicas
    disponíveis" do painel e o cartão de suporte no rodapé da aula. Decisão de
    19/10: tirar do lugar, não tirar a função.

    ## O estado de recolhido

    Fica em `localStorage`, lido antes da primeira pintura por `x-init`. É
    preferência de conforto do próprio navegador — não é dado do aluno, não vai
    para o servidor e não precisa sobreviver a uma troca de máquina.
--}}

<div x-data="{
        recolhido: false,
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

        <div class="mb-2 flex items-center justify-between gap-2 px-1 pt-1">
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

        <x-aluno.item-do-menu rota="/aluno" rotulo="Painel" :ativo="$ativo === 'painel'" icone="painel" />

        @if ($curso !== null)
            <x-aluno.item-do-menu
                :rota="'/aluno/curso/'.($curso['slug'] ?? $curso['id'])"
                rotulo="Meu curso"
                :ativo="$ativo === 'curso'"
                icone="curso" />

            {{-- O expansor só existe quando a TELA entrega as aulas — e só a
                 tela da aula entrega. Na tela do curso a lista é a coluna da
                 direita, e desenhá-la aqui também seria a lista duas vezes na
                 mesma tela, que é o controle duplicado que a skill proíbe.

                 Já vem aberto quando há uma aula em tela: fechado, esconderia
                 justamente onde a pessoa está. --}}
            @if ($aulas !== [])
            <div x-show="!recolhido">
                <button type="button" @click="aulasAbertas = !aulasAbertas"
                        :aria-expanded="aulasAbertas ? 'true' : 'false'"
                        aria-controls="lista-de-aulas"
                        class="flex w-full cursor-pointer items-center justify-between gap-2 rounded-[10px] px-3 py-2.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
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
                            <a href="/aluno/curso/{{ $curso['slug'] ?? $curso['id'] }}/aula/{{ $aula['id'] }}"
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
        @endif

        <x-aluno.item-do-menu rota="/aluno/catalogo" rotulo="Catálogo" :ativo="$ativo === 'catalogo'" icone="catalogo" />

        {{-- Submenu, e não seção da página: decisão da skill. --}}
        <div x-show="!recolhido">
            <button type="button" @click="concluidosAbertos = !concluidosAbertos"
                    :aria-expanded="concluidosAbertos ? 'true' : 'false'"
                    aria-controls="lista-de-concluidos"
                    class="flex w-full cursor-pointer items-center justify-between gap-2 rounded-[10px] px-3 py-2.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                <span>Cursos concluídos</span>
                <span class="flex items-center gap-1.5">
                    <span class="text-nota font-semibold text-escult-ink-3">{{ count($concluidos) }}</span>
                    <svg class="h-4 w-4 text-escult-ink-3 transition-transform" :class="concluidosAbertos ? 'rotate-180' : ''"
                         viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                </span>
            </button>

            <ul id="lista-de-concluidos" x-show="concluidosAbertos" x-cloak class="mt-1 space-y-0.5 pl-2">
                @forelse ($concluidos as $concluido)
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
        </div>

        <x-aluno.item-do-menu rota="/aluno/certificados" rotulo="Certificados" :ativo="$ativo === 'certificados'" icone="certificado" />
        {{-- `bibliotecaDigital` é a flag da biblioteca, e está desligada:
             fora do MVP por decisão de 21/10. No React a tela dependia de
             `materiaisComplementares`, que é a do MATERIAL DA AULA — desligar
             a biblioteca por ela levaria os anexos das aulas junto. --}}
        @if (config('features.bibliotecaDigital') === true)
            <x-aluno.item-do-menu rota="/aluno/biblioteca" rotulo="Biblioteca" :ativo="$ativo === 'biblioteca'" icone="biblioteca" />
        @endif

        <div class="mt-auto space-y-1 border-t border-ava-borda pt-2">
            {{--
                O suporte leva à página de ENVIAR a dúvida ao gestor. Apontava
                para `/aluno/mensagens`, que é a tela de LER a conversa — e essa
                está fechada para o aluno enquanto a flag `forum` estiver
                desligada: o aluno clicava e lia "seção não disponível".

                Dentro de uma aula, a aula vai junto, e o gestor recebe o título
                dela no assunto, como no cartão antigo. Com mensagens desligadas
                o item some, em vez de levar a uma página que só diz que não dá.
            --}}
            @if (config('features.mensagensDiretas') === true)
                <x-aluno.item-do-menu
                    :rota="'/aluno/suporte'.($aulaAtual !== null ? '?aula='.urlencode((string) $aulaAtual) : '')"
                    rotulo="Suporte pedagógico" :ativo="$ativo === 'suporte'" icone="suporte" />
            @endif
            <x-aluno.item-do-menu rota="/aluno/duvidas" rotulo="Ajuda" :ativo="$ativo === 'ajuda'" icone="ajuda" />
            <x-aluno.item-do-menu rota="/aluno/perfil" rotulo="Perfil" :ativo="$ativo === 'perfil'" icone="perfil" />
        </div>
    </nav>
</div>
