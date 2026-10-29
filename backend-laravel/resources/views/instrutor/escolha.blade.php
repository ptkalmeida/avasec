{{--
    `/inst` — os cursos sob responsabilidade do instrutor, e o que há para fazer.

    Substitui o seletor "Curso ativo" do painel React. Uma lista em vez de um
    `<select>`: o instrutor tem poucos cursos, e a lista mostra de uma vez o que
    o seletor escondia — quantas aulas, quantos alunos e a vigência, que é o
    que decide se o curso ainda está no ar.

    Dois grupos: os cursos ATIVOS e os INATIVOS (retirados do ar). O retirado não some: fica
    à parte, com quando e por que saiu, e com o botão "Reativar" (ADR 029). Antes ele
    desaparecia da tela sem explicação, e só o admin o devolvia.

    O botão "Cadastrar novo curso" ficou de fora quando esta tela foi migrada (20/10) e
    só voltou depois: o gestor ficou sem como criar disciplina. Leva a `/inst/cursos/novo`.
--}}
@php
    $ativos = array_values(array_filter($cursos, static fn (array $c): bool => ! $c['inativo']));
    $inativos = array_values(array_filter($cursos, static fn (array $c): bool => $c['inativo']));
@endphp
<x-layouts.instrutor titulo="Meus cursos" ativo="cursos">

    <div class="mx-auto max-w-5xl space-y-8">

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <h1 class="font-titulo text-3xl tracking-tight text-ava-tinta">Meus cursos</h1>
                <p class="text-apoio leading-relaxed text-escult-ink-2">
                    Grade, avaliações e alunos são sempre de um curso. Escolha por onde começar.
                </p>
            </div>
            <a href="/inst/cursos/novo"
               class="inline-flex items-center rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                Cadastrar novo curso
            </a>
        </div>

        @if (session('avisoDoCurso'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-apoio font-semibold text-emerald-900">
                {{ session('avisoDoCurso') }}
            </div>
        @endif
        @if ($errors->has('reativar'))
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-apoio font-semibold text-rose-800">
                {{ $errors->first('reativar') }}
            </div>
        @endif

        {{-- A fazer. Fila vazia é ausência de fila: nada aparece. --}}
        @if ($aFazer !== [])
            <section aria-labelledby="titulo-a-fazer" class="space-y-2 rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <h2 id="titulo-a-fazer" class="text-sobretitulo uppercase text-amber-900">A fazer</h2>
                <ul role="list" class="space-y-1.5">
                    @foreach ($aFazer as $item)
                        <li>
                            <a href="{{ $item['rota'] }}" class="text-corpo font-semibold text-amber-900 underline underline-offset-[3px] hover:text-amber-800">
                                {{ $item['texto'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($cursos === [])
            <div class="rounded-2xl border border-dashed border-ava-borda bg-ava-faixa/50 p-8 text-center">
                <p class="text-corpo font-semibold text-ava-tinta">Você ainda não tem curso sob sua responsabilidade.</p>
                <p class="mt-1 text-apoio text-escult-ink-2">Cadastre um novo curso ou peça à coordenação para lhe atribuir um.</p>
            </div>
        @endif

        {{-- Grupo 1: cursos ativos. --}}
        @if ($ativos !== [])
            <section aria-labelledby="titulo-ativos" class="space-y-4">
                <h2 id="titulo-ativos" class="font-titulo text-xl tracking-tight text-ava-tinta">
                    Cursos ativos <span class="text-apoio font-normal text-escult-ink-2">({{ count($ativos) }})</span>
                </h2>
                <ul role="list" class="space-y-4">
                    @foreach ($ativos as $curso)
                        @php($ref = $curso['slug'] ?? $curso['id'])
                        <li class="rounded-2xl border border-ava-borda bg-white shadow-3xs transition-colors hover:border-ava-acao/40">
                            <a href="/inst/curso/{{ $ref }}"
                               class="flex flex-col gap-3 rounded-2xl p-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">

                                {{-- Os estados vêm por TEXTO, não só por cor. --}}
                                @if ($curso['vigenciaVencida'] || ! $curso['publicado'])
                                    <div class="flex flex-wrap gap-2">
                                        @if ($curso['vigenciaVencida'])
                                            <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-sobretitulo uppercase text-amber-900">Vigência encerrada</span>
                                        @endif
                                        @if (! $curso['publicado'])
                                            <span class="rounded-full bg-ava-faixa px-2.5 py-0.5 text-sobretitulo uppercase text-escult-ink-2">Não publicado</span>
                                        @endif
                                    </div>
                                @endif

                                <div class="min-w-0 space-y-1">
                                    @if ($curso['categoria'] !== null)
                                        <p class="text-sobretitulo uppercase text-escult-ink-3">{{ $curso['categoria'] }}</p>
                                    @endif
                                    <h3 class="font-titulo text-lg leading-tight text-ava-tinta break-words">{{ $curso['titulo'] }}</h3>
                                </div>

                                <dl class="flex flex-wrap gap-x-6 gap-y-1 text-apoio text-escult-ink-2">
                                    <div>
                                        <dt class="inline">Aulas</dt>
                                        <dd class="inline font-semibold text-ava-tinta">{{ $curso['aulas'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="inline">Alunos</dt>
                                        <dd class="inline font-semibold text-ava-tinta">{{ $curso['alunos'] }}</dd>
                                    </div>
                                    @if ($curso['vigencia_ate'] !== null)
                                        <div>
                                            <dt class="inline">{{ $curso['vigenciaVencida'] ? 'Venceu em' : 'Vigência até' }}</dt>
                                            <dd class="inline font-semibold text-ava-tinta">{{ $curso['vigencia_ate'] }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @elseif ($inativos !== [])
            <p class="rounded-2xl border border-dashed border-ava-borda bg-ava-faixa/50 p-5 text-apoio text-escult-ink-2">
                Nenhum curso ativo no momento. Reative um dos cursos inativos abaixo ou cadastre um novo.
            </p>
        @endif

        {{-- Grupo 2: cursos inativos (retirados do ar), à parte, com o botão de reativar. --}}
        @if ($inativos !== [])
            <section aria-labelledby="titulo-inativos" class="space-y-4">
                <div class="space-y-1">
                    <h2 id="titulo-inativos" class="font-titulo text-xl tracking-tight text-ava-tinta">
                        Cursos inativos <span class="text-apoio font-normal text-escult-ink-2">({{ count($inativos) }})</span>
                    </h2>
                    <p class="text-apoio leading-relaxed text-escult-ink-2">
                        Retirados do ar. Nada foi apagado: aulas, alunos e histórico continuam guardados.
                        Ao reativar, o curso volta como estava.
                    </p>
                </div>
                <ul role="list" class="space-y-4">
                    @foreach ($inativos as $curso)
                        <li class="rounded-2xl border border-dashed border-ava-borda bg-ava-faixa/40 p-5">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0 space-y-2">
                                    <span class="inline-block rounded-full bg-rose-50 px-2.5 py-0.5 text-sobretitulo uppercase text-rose-800">Inativo</span>
                                    <div class="space-y-1">
                                        @if ($curso['categoria'] !== null)
                                            <p class="text-sobretitulo uppercase text-escult-ink-3">{{ $curso['categoria'] }}</p>
                                        @endif
                                        <h3 class="font-titulo text-lg leading-tight text-ava-tinta break-words">{{ $curso['titulo'] }}</h3>
                                    </div>
                                    <dl class="flex flex-wrap gap-x-6 gap-y-1 text-apoio text-escult-ink-2">
                                        <div>
                                            <dt class="inline">Aulas</dt>
                                            <dd class="inline font-semibold text-ava-tinta">{{ $curso['aulas'] }}</dd>
                                        </div>
                                        <div>
                                            <dt class="inline">Alunos</dt>
                                            <dd class="inline font-semibold text-ava-tinta">{{ $curso['alunos'] }}</dd>
                                        </div>
                                        @if ($curso['retiradoEm'] !== null)
                                            <div>
                                                <dt class="inline">Retirado em</dt>
                                                <dd class="inline font-semibold text-ava-tinta">{{ $curso['retiradoEm'] }}</dd>
                                            </div>
                                        @endif
                                    </dl>
                                    @if ($curso['motivoDaRetirada'] !== null)
                                        <p class="text-apoio text-escult-ink-2">
                                            <span class="font-semibold text-ava-tinta">Motivo:</span> {{ $curso['motivoDaRetirada'] }}
                                        </p>
                                    @endif
                                </div>

                                <form method="POST" action="/inst/cursos/{{ $curso['id'] }}/reativar" class="shrink-0">
                                    @csrf
                                    <button type="submit"
                                            aria-label="Reativar o curso {{ $curso['titulo'] }}"
                                            class="cursor-pointer rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                                        Reativar curso
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.instrutor>
