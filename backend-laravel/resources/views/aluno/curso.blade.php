{{--
    Navegação do curso — tela 4 do redesenho (opção A aprovada).

    Duas colunas: à esquerda a capa e as ações; à direita "Aulas do curso". A
    lista aparece **uma vez por tela**, e é esta a tela em que ela vive na área
    principal — dentro de uma aula ela se muda para o menu lateral, para o
    conteúdo ficar com a largura de leitura.

    Removidos por decisão da skill: a descrição sob a imagem, a frequência e o
    "frequência cumprida", o nome do gestor/instrutor e o selo "Online".

    O botão principal diz "Iniciar aula" — era "Iniciar primeira aula" — e vai
    para a primeira aula NÃO concluída quando há uma, que é o que a pessoa
    espera de um curso começado. "Trocar de Curso" foi preservado.
--}}
<x-layouts.aluno
    :titulo="$curso['titulo']"
    ativo="curso"
    :cursoDoMenu="$curso"
    :concluidosDoMenu="$concluidos">

    @php
        $capa = $curso['imagem_capa'] ?: ($curso['miniatura'] ?? '');
        $proxima = collect($aulas)->firstWhere('concluida', false) ?? ($aulas[0] ?? null);
        $ref = $curso['slug'] ?? $curso['id'];
    @endphp

    <div class="mx-auto max-w-6xl space-y-6">

        <nav aria-label="Trilha de navegação" class="text-apoio text-escult-ink-3">
            <a href="/aluno" class="underline-offset-[3px] hover:text-ava-acao hover:underline">Painel</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <span class="text-ava-tinta">{{ $curso['titulo'] }}</span>
        </nav>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">

            <section class="space-y-5 rounded-2xl border border-ava-borda bg-white p-5 md:p-6">
                <div class="space-y-2">
                    <p class="text-sobretitulo uppercase text-escult-ink-3">Trilha de estudos</p>
                    <h1 class="font-titulo text-2xl leading-tight tracking-tight text-ava-tinta md:text-3xl">
                        {{ $curso['titulo'] }}
                    </h1>
                    @if (! empty($curso['categoria']))
                        <span class="inline-block rounded-full bg-ava-lilas-fundo px-2.5 py-1 text-nota font-semibold uppercase tracking-wide text-ava-lilas">
                            {{ $curso['categoria'] }}
                        </span>
                    @endif
                </div>

                {{-- A capa escolhida pela gestão, e a MESMA que o painel mostra
                     para este curso. Sem capa, nenhum quadro quebrado: some. --}}
                @if ($capa !== '')
                    <img src="{{ $capa }}" alt="" aria-hidden="true" loading="lazy" referrerpolicy="no-referrer"
                         class="max-h-72 w-full rounded-xl border border-ava-borda object-cover">
                @endif

                <div class="space-y-1.5">
                    <div class="h-2 w-full overflow-hidden rounded-full bg-ava-faixa"
                         role="progressbar" aria-valuenow="{{ $curso['percentual'] }}" aria-valuemin="0" aria-valuemax="100"
                         aria-label="Progresso no curso">
                        <div class="h-full rounded-full bg-ava-lilas" style="width: {{ $curso['percentual'] }}%"></div>
                    </div>
                    <p class="text-apoio text-escult-ink-2">
                        {{ $curso['aulasConcluidas'] }} de {{ $curso['aulasNoCurso'] }} aulas concluídas
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    @if ($proxima !== null)
                        <a href="/aluno/curso/{{ $ref }}/aula/{{ $proxima['id'] }}"
                           class="rounded-[10px] bg-ava-acao px-6 py-3 text-center text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                            Iniciar aula
                        </a>
                    @endif
                    <a href="/aluno"
                       class="rounded-[10px] border border-ava-borda bg-white px-6 py-3 text-center text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                        Trocar de Curso
                    </a>
                </div>

                {{-- Avaliação é o que decide a certificação: o caminho até ela
                     fica à vista na tela do curso. --}}
                <a href="/aluno/curso/{{ $ref }}/avaliacoes"
                   class="inline-flex items-center gap-1.5 text-rotulo font-semibold text-ava-acao underline-offset-[3px] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                    <span>Avaliações do curso</span>
                    <span aria-hidden="true">→</span>
                </a>
            </section>

            <section x-data="{ todas: false }" class="rounded-2xl border border-ava-borda bg-white p-5 md:p-6">
                <div class="mb-4 flex items-baseline justify-between gap-3">
                    <h2 class="font-titulo text-cartao text-ava-tinta">Aulas do curso</h2>
                    <span class="text-apoio font-semibold text-escult-ink-3">
                        {{ $curso['aulasConcluidas'] }}/{{ $curso['aulasNoCurso'] }}
                    </span>
                </div>

                <ol class="space-y-1">
                    @foreach ($aulas as $i => $aula)
                        <li @if ($i >= 6) x-show="todas" x-cloak @endif>
                            <a href="/aluno/curso/{{ $ref }}/aula/{{ $aula['id'] }}"
                               class="flex items-start gap-3 rounded-[10px] px-3 py-2.5 transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                                {{-- Conclusão por FORMA, não só por cor: ✓ contra número. --}}
                                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold
                                             {{ $aula['concluida'] ? 'bg-ava-lilas text-white' : 'border border-ava-borda text-escult-ink-3' }}"
                                      aria-hidden="true">
                                    {{ $aula['concluida'] ? '✓' : $i + 1 }}
                                </span>
                                <span class="min-w-0 text-apoio leading-snug text-ava-tinta">
                                    {{ $aula['titulo'] }}
                                    @if ($aula['concluida'])
                                        <span class="sr-only">(aula concluída)</span>
                                    @endif
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ol>

                @if (count($aulas) > 6)
                    <button type="button" @click="todas = !todas"
                            class="mt-3 w-full cursor-pointer rounded-[10px] border border-ava-borda px-4 py-2.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                        <span x-show="!todas">Ver todas as aulas ({{ count($aulas) }})</span>
                        <span x-show="todas" x-cloak>Ver menos</span>
                    </button>
                @endif
            </section>
        </div>
    </div>
</x-layouts.aluno>
