{{--
    Ficha do curso no catálogo: o que ele é, antes de a pessoa se matricular.

    As quatro regras e o Termo de Ciência são os mesmos do modal do React,
    palavra por palavra — eles descrevem o regulamento acadêmico, não um texto
    de interface, e reescrevê-los mudaria o que a pessoa aceitou.

    O que mudou: o termo passou a ser validado **no servidor**. No React ele só
    desabilitava o botão, e botão desabilitado não é regra — é sugestão.
--}}
<x-layouts.aluno :titulo="$curso['titulo']" ativo="catalogo" :concluidosDoMenu="$concluidos">

    @php($capa = $curso['imagem_capa'] ?: ($curso['miniatura'] ?? ''))

    <div class="mx-auto max-w-4xl space-y-6">

        <nav aria-label="Trilha de navegação" class="text-apoio text-escult-ink-3">
            <a href="/aluno/catalogo" class="underline-offset-[3px] hover:text-ava-acao hover:underline">Catálogo</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <span class="text-ava-tinta">{{ $curso['titulo'] }}</span>
        </nav>

        <article class="space-y-5 rounded-2xl border border-ava-borda bg-white p-5 md:p-6">
            <div class="space-y-2">
                @if (! empty($curso['categoria']))
                    <span class="inline-block rounded-full bg-ava-lilas-fundo px-2.5 py-1 text-nota font-semibold uppercase tracking-wide text-ava-lilas">
                        {{ $curso['categoria'] }}
                    </span>
                @endif
                <h1 class="font-titulo text-2xl leading-tight tracking-tight text-ava-tinta md:text-3xl">{{ $curso['titulo'] }}</h1>
            </div>

            @if ($capa !== '')
                <img src="{{ $capa }}" alt="" aria-hidden="true" loading="lazy" referrerpolicy="no-referrer"
                     class="max-h-64 w-full rounded-xl border border-ava-borda object-cover">
            @endif

            <p class="text-corpo leading-relaxed text-escult-ink-2">{{ $curso['descricao'] }}</p>

            @if ($aulas !== [])
                <section x-data="{ todas: false }" class="space-y-2">
                    <h2 class="font-titulo text-cartao text-ava-tinta">Grade do curso</h2>

                    <ol class="space-y-1">
                        @foreach ($aulas as $i => $aula)
                            <li @if ($i >= 5) x-show="todas" x-cloak @endif
                                class="flex items-start gap-3 rounded-[10px] px-3 py-2">
                                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-ava-borda text-[11px] font-bold text-escult-ink-3" aria-hidden="true">
                                    {{ $i + 1 }}
                                </span>
                                <span class="text-apoio leading-snug text-ava-tinta">{{ $aula['titulo'] }}</span>
                            </li>
                        @endforeach
                    </ol>

                    @if (count($aulas) > 5)
                        <button type="button" @click="todas = !todas"
                                class="cursor-pointer rounded-[10px] border border-ava-borda px-4 py-2 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                            <span x-show="!todas">Ver a grade completa ({{ count($aulas) }} aulas)</span>
                            <span x-show="todas" x-cloak>Ver menos</span>
                        </button>
                    @endif
                </section>
            @endif
        </article>

        <section class="space-y-4 rounded-2xl border border-ava-borda bg-white p-5 md:p-6">
            <div>
                <h2 class="font-titulo text-cartao text-ava-tinta">Antes de concluir sua matrícula</h2>
                <p class="text-sobretitulo uppercase text-escult-ink-3">Regulamento acadêmico</p>
            </div>

            <ul role="list" class="space-y-2.5">
                @foreach ([
                    'Para emissão do certificado, é necessário cumprir a frequência mínima do curso (70% por padrão) e ser aprovado em todas as avaliações do curso.',
                    'O aluno deve acompanhar as aulas e realizar as atividades obrigatórias, quando houver.',
                    'Após a confirmação, o curso ficará disponível para início imediato.',
                    'O certificado será liberado conforme os critérios de conclusão do curso.',
                ] as $regra)
                    <li class="flex items-start gap-2 text-rotulo leading-relaxed text-escult-ink-2">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-ava-lilas" aria-hidden="true"></span>
                        <span>{{ $regra }}</span>
                    </li>
                @endforeach
            </ul>

            @error('matricula')
                <div role="alert" tabindex="-1" x-init="$el.focus()"
                     class="rounded-xl border border-ava-rosa/40 bg-ava-rosa/5 p-4 text-apoio leading-relaxed text-ava-tinta">
                    {{ $message }}
                </div>
            @enderror

            <form method="POST" action="/aluno/catalogo/{{ $curso['slug'] ?? $curso['id'] }}/matricula" class="space-y-4">
                @csrf

                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-ava-borda bg-ava-faixa/60 p-3.5">
                    <input type="checkbox" name="ciente" value="1" required
                           class="mt-0.5 h-4.5 w-4.5 cursor-pointer rounded accent-ava-acao">
                    <span class="text-left">
                        <strong class="block text-rotulo font-bold text-ava-tinta">Termo de Ciência</strong>
                        <span class="mt-0.5 block text-apoio leading-normal text-escult-ink-2">
                            Li e estou ciente das regras para matrícula e certificação.
                        </span>
                    </span>
                </label>

                @error('ciente')
                    <p role="alert" class="text-apoio text-ava-rosa">{{ $message }}</p>
                @enderror

                <button type="submit"
                        class="w-full cursor-pointer rounded-[10px] bg-ava-acao px-6 py-3.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                    Confirmar matrícula
                </button>

                <p class="text-center text-apoio text-escult-ink-2">
                    Comece seus estudos imediatamente após a confirmação.
                </p>
            </form>
        </section>
    </div>
</x-layouts.aluno>
