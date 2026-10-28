{{--
    `/inst` — os cursos sob responsabilidade do instrutor, e o que há para fazer.

    Substitui o seletor "Curso ativo" do painel React. Uma lista em vez de um
    `<select>`: o instrutor tem poucos cursos, e a lista mostra de uma vez o que
    o seletor escondia — quantas aulas, quantos alunos e a vigência, que é o
    que decide se o curso ainda está no ar.

    O que NÃO veio: o botão "Cadastrar Novo Curso" abre um formulário que ainda
    é React; ele entra na fatia seguinte, junto com a Gestão do Curso.
--}}
<x-layouts.instrutor titulo="Meus cursos" ativo="cursos">

    <div class="mx-auto max-w-5xl space-y-8">

        <div class="space-y-2">
            <h1 class="font-titulo text-3xl tracking-tight text-ava-tinta">Meus cursos</h1>
            <p class="text-apoio leading-relaxed text-escult-ink-2">
                Grade, avaliações e alunos são sempre de um curso. Escolha por onde começar.
            </p>
        </div>

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
                <p class="mt-1 text-apoio text-escult-ink-2">Peça à coordenação para lhe atribuir um curso.</p>
            </div>
        @else
            <ul role="list" class="space-y-4">
                @foreach ($cursos as $curso)
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
                                <h2 class="font-titulo text-lg leading-tight text-ava-tinta break-words">{{ $curso['titulo'] }}</h2>
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
        @endif
    </div>
</x-layouts.instrutor>
