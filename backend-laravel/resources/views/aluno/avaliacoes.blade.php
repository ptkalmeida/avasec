{{--
    Avaliações do curso — a lista, com a situação de cada uma.

    Substitui a lista do `AvaliacoesPage.tsx`. "Aprovada" segue o certificado:
    basta uma tentativa aprovada (LearningService::avaliacoesDoCursoParaAluno).
    A nota mostrada é a da tentativa mais recente.
--}}
<x-layouts.aluno titulo="Avaliações — {{ $curso['titulo'] }}" ativo="avaliacoes" :cursoDoMenu="$curso">

    @php
        $aprovadas = count(array_filter($avaliacoes, static fn (array $a): bool => $a['aprovada']));
    @endphp

    <div class="mx-auto max-w-3xl space-y-8">

        <nav aria-label="Trilha de navegação" class="text-apoio text-escult-ink-3">
            <a href="/aluno" class="underline-offset-[3px] hover:text-ava-acao hover:underline">Início</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <a href="/aluno/curso/{{ $ref }}" class="underline-offset-[3px] hover:text-ava-acao hover:underline">{{ $curso['titulo'] }}</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <span class="text-ava-tinta">Avaliações</span>
        </nav>

        <div class="space-y-2">
            <h1 class="font-titulo text-3xl tracking-tight text-ava-tinta">Avaliações</h1>
            <p class="text-apoio leading-relaxed text-escult-ink-2">
                {{ $curso['titulo'] }}.
                @if ($avaliacoes !== [])
                    Para o certificado, é preciso ser aprovado em todas, com pelo menos {{ $minimo }}% de acertos.
                    Você pode refazer quantas vezes quiser.
                @endif
            </p>
        </div>

        @if ($avaliacoes === [])
            <div class="rounded-2xl border border-dashed border-ava-borda bg-ava-faixa/50 p-8 text-center">
                <p class="text-corpo font-semibold text-ava-tinta">Este curso não tem avaliações.</p>
                <p class="mt-1 text-apoio text-escult-ink-2">O certificado depende só da frequência.</p>
            </div>
        @else
            <p class="text-rotulo font-semibold text-ava-tinta">
                {{ $aprovadas }} de {{ count($avaliacoes) }} {{ count($avaliacoes) === 1 ? 'avaliação aprovada' : 'avaliações aprovadas' }}
            </p>

            <ul role="list" class="space-y-4">
                @foreach ($avaliacoes as $avaliacao)
                    <li class="flex flex-col justify-between gap-4 rounded-2xl border border-ava-borda bg-white p-5 shadow-3xs sm:flex-row sm:items-center">
                        <div class="min-w-0 space-y-2">
                            {{-- Situação por TEXTO e forma, não só por cor. --}}
                            @if ($avaliacao['aprovada'])
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-sobretitulo uppercase text-emerald-800">
                                    <span aria-hidden="true">✓</span> Aprovada
                                </span>
                            @elseif ($avaliacao['ultima'] !== null)
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-sobretitulo uppercase text-amber-900">
                                    Ainda não aprovada
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-ava-faixa px-2.5 py-0.5 text-sobretitulo uppercase text-escult-ink-2">
                                    Não feita
                                </span>
                            @endif

                            <h2 class="font-titulo text-lg leading-tight text-ava-tinta break-words">{{ $avaliacao['titulo'] }}</h2>

                            <p class="text-apoio text-escult-ink-2">
                                {{ $avaliacao['questoes'] === 1 ? '1 questão' : $avaliacao['questoes'].' questões' }}
                                @if ($avaliacao['ultima'] !== null)
                                    · última tentativa: <strong class="font-semibold text-ava-tinta">{{ $avaliacao['ultima']['percentual_nota'] }}%</strong>
                                    em {{ $avaliacao['ultima']['enviado_em_texto'] }}
                                    · {{ $avaliacao['tentativas'] === 1 ? '1 tentativa' : $avaliacao['tentativas'].' tentativas' }}
                                @endif
                            </p>
                        </div>

                        @if ($avaliacao['questoes'] > 0)
                            <a href="/aluno/curso/{{ $ref }}/avaliacoes/{{ rawurlencode($avaliacao['id']) }}"
                               class="inline-flex shrink-0 items-center justify-center rounded-[10px] px-5 py-2.5 text-rotulo font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2
                                      {{ $avaliacao['aprovada'] ? 'border border-ava-borda bg-white text-ava-tinta hover:bg-ava-faixa' : 'bg-ava-acao text-white hover:bg-ava-acao-escuro' }}">
                                {{ $avaliacao['ultima'] === null ? 'Começar' : 'Refazer' }}
                                <span class="sr-only">{{ $avaliacao['titulo'] }}</span>
                            </a>
                        @else
                            <p class="shrink-0 text-apoio text-escult-ink-3">Ainda sem questões</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <a href="/aluno/curso/{{ $ref }}"
           class="inline-flex items-center gap-1.5 text-rotulo font-semibold text-ava-acao underline-offset-[3px] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
            <span aria-hidden="true">←</span>
            <span>Voltar ao curso</span>
        </a>
    </div>
</x-layouts.aluno>
