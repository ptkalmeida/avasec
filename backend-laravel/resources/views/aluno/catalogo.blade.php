{{--
    Catálogo do aluno — a nova casa da matrícula.

    Ela vivia no painel, na seção "Disciplinas acadêmicas disponíveis", que a
    skill removeu de lá. Decisão de 19/10: tirar do lugar sem tirar a função.

    Mostra só o que o aluno ainda NÃO tem. Listar um curso em que ele já está
    seria um cartão prometendo uma matrícula que o servidor recusaria.
--}}
<x-layouts.aluno titulo="Catálogo" ativo="catalogo" :concluidosDoMenu="$concluidos">

    <div class="mx-auto max-w-5xl space-y-6">

        <div class="space-y-2">
            <h1 class="font-titulo text-3xl tracking-tight text-ava-tinta">Catálogo</h1>
            <p class="text-apoio leading-relaxed text-escult-ink-2">
                {{ count($cursos) === 1 ? '1 curso disponível' : count($cursos).' cursos disponíveis' }} para matrícula.
            </p>
        </div>

        @unless ($podeMatricular)
            {{-- Dito ANTES de a pessoa escolher, e não depois de ela tentar: o
                 erro do servidor chegaria tarde, já com a expectativa criada. --}}
            <div role="status" class="rounded-xl border border-ava-lilas-borda bg-ava-lilas-fundo p-4 text-apoio leading-relaxed text-ava-tinta">
                Você já tem um curso em andamento. Para começar outro, conclua o atual ou fale com a
                coordenação — a matrícula em mais de um curso ao mesmo tempo depende de liberação.
            </div>
        @endunless

        @if ($cursos === [])
            <div class="rounded-2xl border border-dashed border-ava-borda bg-ava-faixa/50 p-8 text-center">
                <p class="text-corpo text-escult-ink-2">Não há cursos disponíveis para matrícula agora.</p>
            </div>
        @else
            <ul role="list" aria-label="Cursos disponíveis" class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                @foreach ($cursos as $curso)
                    @php($capa = $curso['coverImage'] ?: ($curso['thumbnail'] ?? ''))
                    <li class="flex flex-col overflow-hidden rounded-2xl border border-ava-borda bg-white shadow-3xs">
                        <div class="h-36 overflow-hidden bg-ava-marinho">
                            @if ($capa !== '')
                                <img src="{{ $capa }}" alt="" aria-hidden="true" loading="lazy"
                                     referrerpolicy="no-referrer" class="h-full w-full object-cover">
                            @endif
                        </div>

                        <div class="flex flex-1 flex-col justify-between gap-3 p-5">
                            <div class="space-y-2">
                                @if (! empty($curso['category']))
                                    <span class="inline-block rounded-full bg-ava-lilas-fundo px-2.5 py-1 text-nota font-semibold uppercase tracking-wide text-ava-lilas">
                                        {{ $curso['category'] }}
                                    </span>
                                @endif
                                <h2 class="font-titulo text-cartao leading-snug text-ava-tinta">{{ $curso['title'] }}</h2>
                                <p class="line-clamp-3 text-apoio leading-relaxed text-escult-ink-2">{{ $curso['description'] }}</p>
                            </div>

                            <a href="/aluno/catalogo/{{ $curso['slug'] ?? $curso['id'] }}"
                               class="inline-flex items-center justify-center gap-1.5 rounded-[10px] border border-ava-acao px-5 py-2.5 text-rotulo font-semibold text-ava-acao transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                                <span>Ver e escolher</span>
                                <span aria-hidden="true">→</span>
                                <span class="sr-only">— {{ $curso['title'] }}</span>
                            </a>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.aluno>
