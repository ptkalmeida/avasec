{{--
    Biblioteca do aluno — materiais complementares para baixar ou abrir.

    Substitui o `StudentLibraryPanel.tsx`. Duas diferenças de propósito:

    - item cadastrado sem endereço que leve a algum lugar (os seis de
      demonstração têm `#`) aparece com "ainda não disponível", e não com um
      "Baixar arquivo" que não baixa nada;
    - só aparece o que está no ar para o aluno (escada de `Visibilidade`); o
      React mostrava também item restrito ou em rascunho, porque a API não
      filtra.

    O React caía numa lista de demonstração guardada no navegador quando a API
    falhava. Aqui não há o que cair: a lista vem do banco, ou não vem.
--}}
<x-layouts.aluno titulo="Biblioteca" ativo="biblioteca">

    <div class="mx-auto max-w-5xl space-y-8">

        <div class="space-y-2">
            <h1 class="font-titulo text-3xl tracking-tight text-ava-tinta">Biblioteca</h1>
            <p class="text-apoio leading-relaxed text-escult-ink-2">Materiais complementares dos cursos, para baixar ou consultar.</p>
        </div>

        @if ($itens === [])
            <div class="rounded-2xl border border-dashed border-ava-borda bg-ava-faixa/50 p-8 text-center">
                <p class="text-corpo font-semibold text-ava-tinta">Ainda não há material na biblioteca.</p>
                <p class="mt-1 text-apoio text-escult-ink-2">Quando a coordenação publicar algum, ele aparece aqui.</p>
            </div>
        @else
            <ul role="list" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($itens as $item)
                    @php($arquivo = $item['type'] === 'pdf')
                    <li class="flex flex-col gap-3 rounded-2xl border border-ava-borda bg-white p-5 shadow-3xs">
                        <div class="flex items-start justify-between gap-3">
                            <span class="inline-flex items-center gap-1.5 text-sobretitulo uppercase text-escult-ink-2">
                                @if ($arquivo)
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-rose-700" aria-hidden="true">
                                        <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>
                                    </svg>
                                    PDF
                                @elseif ($item['type'] === 'video')
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-ava-acao" aria-hidden="true">
                                        <polygon points="6 3 20 12 6 21 6 3"/>
                                    </svg>
                                    Vídeo
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-ava-acao" aria-hidden="true">
                                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                                    </svg>
                                    Link
                                @endif
                            </span>
                            <span class="rounded border border-ava-borda bg-ava-faixa px-2 py-0.5 text-sobretitulo uppercase text-escult-ink-2 break-words">{{ $item['category'] }}</span>
                        </div>

                        <h2 class="font-titulo text-lg leading-tight text-ava-tinta break-words">{{ $item['title'] }}</h2>
                        @if ($item['description'] !== null)
                            <p class="text-apoio leading-relaxed text-escult-ink-2 break-words">{{ $item['description'] }}</p>
                        @endif

                        <div class="mt-auto pt-2">
                            @if ($item['href'] !== null)
                                <a href="{{ $item['href'] }}" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex w-full items-center justify-center gap-2 rounded-[10px] border border-ava-borda bg-white px-4 py-2.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                                    {{ $arquivo ? 'Baixar arquivo' : 'Abrir' }}
                                    <span class="sr-only">{{ $item['title'] }} (abre em nova aba)</span>
                                </a>
                            @else
                                <p class="rounded-[10px] bg-ava-faixa px-4 py-2.5 text-center text-apoio text-escult-ink-2">Arquivo ainda não disponível</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.aluno>
