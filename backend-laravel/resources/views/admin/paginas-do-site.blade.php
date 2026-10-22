{{--
    `/admin/paginas-do-site` — os textos das páginas públicas do portal.

    Os campos vêm do schema do serviço (`$definicao`). Acrescentar, remover e
    mover item devolvem a tela com a lista mudada, SEM gravar: só "Salvar e
    publicar" grava, e a alteração aparece no site na hora.
--}}
@php
    $campoCurto = 'w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-slate-900 focus:border-primary focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/30';
    $rotulo = 'mb-1 block text-[0.8rem] font-semibold text-slate-600';
    $botaoDeIcone = 'rounded-md p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-30';
@endphp
<x-layouts.admin titulo="Páginas do Site" ativo="site_content"
                 contexto="Textos e imagens das páginas públicas do portal.">

    <p class="mb-4 max-w-3xl text-xs leading-relaxed text-slate-500">
        Edite os textos das páginas públicas do portal. Escolha a página, ajuste os campos e clique em
        <strong>Salvar e publicar</strong> — a alteração aparece no site imediatamente. Se um campo ficar em branco,
        o site mostra o texto original de fábrica.
    </p>

    @if (session('avisoDoSite'))
        <div role="status" class="mb-4 flex items-center gap-2 rounded-[10px] border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ session('avisoDoSite') }}
        </div>
    @endif

    <nav aria-label="Páginas do site" class="mb-6 grid grid-cols-2 gap-2.5 md:grid-cols-4">
        @foreach ($esquema as $chave => $def)
            <a href="/admin/paginas-do-site?pagina={{ $chave }}"
               @if ($chave === $pagina) aria-current="page" @endif
               class="rounded-[10px] border px-3.5 py-3 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary
                      {{ $chave === $pagina ? 'border-primary bg-primary/10' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                <span class="block text-[0.8rem] font-bold leading-snug {{ $chave === $pagina ? 'text-primary' : 'text-slate-800' }}">{{ $def['label'] }}</span>
                <span class="mt-0.5 block text-xs text-slate-500">{{ $quantosItens[$chave] }} {{ mb_strtolower($def['itemsLabel']) }}</span>
            </a>
        @endforeach
    </nav>

    <form method="POST" action="/admin/paginas-do-site/{{ $pagina }}" class="space-y-5">
        @csrf
        {{-- O primeiro botão do formulário é o que Enter aciona: tem de ser o de salvar. --}}
        <button type="submit" name="acao" value="salvar" class="sr-only" tabindex="-1" aria-hidden="true">Salvar e publicar</button>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-5">
            <div>
                <h2 class="text-[0.95rem] font-bold text-slate-900">{{ $definicao['label'] }}</h2>
                @if ($atualizadoEm !== null)
                    <p class="text-xs text-slate-500">Última alteração em {{ \Carbon\CarbonImmutable::parse($atualizadoEm)->setTimezone(\App\Support\Fuso::exibicao())->format('d/m/Y H:i') }}</p>
                @else
                    <p class="text-xs text-slate-500">Ainda não foi alterada: vale o texto de fábrica.</p>
                @endif
            </div>
            <a href="/{{ $pagina }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 text-[0.8rem] font-semibold text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Ver esta página no site
            </a>
        </div>

        @if ($errors->any())
            <div role="alert" class="rounded-[10px] border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">
                @foreach ($errors->all() as $erro)
                    <p>{{ $erro }}</p>
                @endforeach
            </div>
        @endif

        {{-- Topo da página --}}
        <section aria-labelledby="titulo-topo" class="space-y-4 rounded-[10px] border border-slate-200 bg-white p-5 shadow-card">
            <h3 id="titulo-topo" class="text-[0.875rem] font-bold text-slate-900">Topo da página</h3>
            @foreach ($definicao['header'] as $campo)
                <div>
                    <label for="topo-{{ $campo['key'] }}" class="{{ $rotulo }}">{{ $campo['label'] }}</label>
                    @if ($campo['type'] === 'textarea')
                        <textarea id="topo-{{ $campo['key'] }}" name="topo[{{ $campo['key'] }}]" rows="3" maxlength="{{ $campo['maxLength'] }}" class="{{ $campoCurto }}">{{ $topo[$campo['key']] }}</textarea>
                    @else
                        <input id="topo-{{ $campo['key'] }}" name="topo[{{ $campo['key'] }}]" type="text" maxlength="{{ $campo['maxLength'] }}" value="{{ $topo[$campo['key']] }}" class="{{ $campoCurto }}">
                    @endif
                </div>
            @endforeach
        </section>

        {{-- Itens --}}
        <section aria-labelledby="titulo-itens" class="space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h3 id="titulo-itens" class="text-[0.875rem] font-bold text-slate-900">
                    {{ $definicao['itemsLabel'] }}
                    <span class="font-normal text-slate-500">({{ count($itens) }} de {{ $definicao['maxItems'] }})</span>
                </h3>
                <button type="submit" name="acao" value="adicionar" formnovalidate
                        class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Adicionar
                </button>
            </div>

            @forelse ($itens as $i => $item)
                <div class="space-y-4 rounded-[10px] border border-slate-200 bg-white p-5 shadow-card">
                    <input type="hidden" name="itens[{{ $i }}][id]" value="{{ $item['id'] }}">

                    <div class="flex items-center justify-between border-b border-slate-200 pb-2.5">
                        <span class="text-[0.8rem] font-bold text-slate-500">{{ $i + 1 }}º de {{ count($itens) }}</span>
                        <div class="flex items-center gap-1">
                            <button type="submit" name="acao" value="subir:{{ $i }}" formnovalidate @disabled($i === 0) class="{{ $botaoDeIcone }}" title="Mover para cima">
                                <i class="fa-solid fa-arrow-up" aria-hidden="true"></i><span class="sr-only">Mover o item {{ $i + 1 }} para cima</span>
                            </button>
                            <button type="submit" name="acao" value="descer:{{ $i }}" formnovalidate @disabled($i === count($itens) - 1) class="{{ $botaoDeIcone }}" title="Mover para baixo">
                                <i class="fa-solid fa-arrow-down" aria-hidden="true"></i><span class="sr-only">Mover o item {{ $i + 1 }} para baixo</span>
                            </button>
                            <button type="submit" name="acao" value="remover:{{ $i }}" formnovalidate class="{{ $botaoDeIcone }} !text-red-500 hover:!bg-red-50" title="Remover este item">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i><span class="sr-only">Remover o item {{ $i + 1 }}</span>
                            </button>
                        </div>
                    </div>

                    @foreach ($definicao['item'] as $campo)
                        <div>
                            <label for="item-{{ $i }}-{{ $campo['key'] }}" class="{{ $rotulo }}">{{ $campo['label'] }}</label>
                            @if ($campo['type'] === 'textarea')
                                <textarea id="item-{{ $i }}-{{ $campo['key'] }}" name="itens[{{ $i }}][{{ $campo['key'] }}]" rows="3" maxlength="{{ $campo['maxLength'] }}" class="{{ $campoCurto }}">{{ $item[$campo['key']] }}</textarea>
                            @else
                                <input id="item-{{ $i }}-{{ $campo['key'] }}" name="itens[{{ $i }}][{{ $campo['key'] }}]" type="text" maxlength="{{ $campo['maxLength'] }}" value="{{ $item[$campo['key']] }}" class="{{ $campoCurto }}">
                            @endif
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="rounded-[10px] border border-dashed border-slate-300 bg-slate-50 p-10 text-center text-sm text-slate-500">
                    Nenhum item cadastrado. Clique em <strong>Adicionar</strong> para criar o primeiro.
                </div>
            @endforelse
        </section>

        <div class="flex justify-end border-t border-slate-200 pt-4">
            <button type="submit" name="acao" value="salvar"
                    class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Salvar e publicar
            </button>
        </div>
    </form>
</x-layouts.admin>
