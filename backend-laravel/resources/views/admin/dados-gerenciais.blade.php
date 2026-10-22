{{--
    `/admin/dados-gerenciais` — as cinco bases em CSV, para Power BI e Excel.

    A prévia e o arquivo saem da MESMA fonte: o que a tela mostra é o que o
    download traz. (No painel React a prévia era montada no navegador, com
    valores de reserva inventados que o arquivo nunca teve.)
--}}
<x-layouts.admin titulo="Dados Gerenciais" ativo="export_bi"
                 contexto="Exportação de bases para uso gerencial e BI.">

    @if (session('avisoDeDados'))
        <div role="status" class="mb-4 flex items-center gap-2 rounded-[10px] border border-amber-100 bg-amber-50 px-3 py-2 text-sm text-amber-700">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> {{ session('avisoDeDados') }}
        </div>
    @endif

    <section aria-labelledby="titulo-bases" class="space-y-5 rounded-[10px] border border-slate-200 bg-white p-5 shadow-card">
        <div class="flex flex-col justify-between gap-4 border-b border-slate-200 pb-4 md:flex-row md:items-center">
            <div>
                <h2 id="titulo-bases" class="flex items-center gap-2 text-[0.875rem] font-bold text-slate-900">
                    <i class="fa-solid fa-database text-primary" aria-hidden="true"></i>
                    Integração de dados e exportação de bases para BI
                </h2>
                <p class="mt-1 text-xs text-slate-500">
                    Estruturas de dados otimizadas para alimentação e modelagem em Power BI, Excel e relatórios analíticos de gestão.
                </p>
            </div>

            {{-- "Baixar todas" são cinco downloads em sequência (o navegador bloqueia os que chegam juntos). --}}
            <button type="button"
                    x-data="{ baixando: false, bases: @js(array_keys($bases)) }"
                    @click="baixando = true;
                            bases.forEach((b, i) => setTimeout(() => {
                                const a = document.createElement('a');
                                a.href = '/admin/dados-gerenciais/baixar/' + b + '?lote=1';
                                a.download = '';
                                document.body.appendChild(a); a.click(); a.remove();
                            }, i * 450));
                            setTimeout(() => baixando = false, bases.length * 450 + 500)"
                    :disabled="baixando"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:opacity-60">
                <i class="fa-solid fa-download" aria-hidden="true"></i>
                <span x-text="baixando ? 'Baixando…' : 'Baixar todas as 5 bases (.csv)'">Baixar todas as 5 bases (.csv)</span>
            </button>
        </div>

        <nav aria-label="Bases de dados" class="flex gap-1 overflow-x-auto border-b border-slate-200">
            @foreach ($bases as $chave => $b)
                <a href="/admin/dados-gerenciais?base={{ $chave }}"
                   @if ($chave === $base) aria-current="page" @endif
                   class="whitespace-nowrap border-b-2 px-4 py-2 text-[0.85rem] font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary
                          {{ $chave === $base ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                    {{ $b['rotulo'] }}
                </a>
            @endforeach
        </nav>

        <div class="flex flex-col justify-between gap-3 rounded-[10px] border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center">
            <div class="space-y-1">
                <p class="text-[0.7rem] font-bold uppercase tracking-[0.06em] text-primary">Visualizando a estrutura de dados</p>
                <h3 class="text-[0.875rem] font-bold text-slate-900">
                    {{ $definicao['titulo'] }}
                    <span class="font-normal text-slate-500">({{ $total }} {{ $total === 1 ? 'registro' : 'registros' }} no total)</span>
                </h3>
                <p class="max-w-2xl text-xs text-slate-500">{{ $definicao['descricao'] }}</p>
            </div>
            @if ($total > 0)
                <a href="/admin/dados-gerenciais/baixar/{{ $base }}"
                   class="inline-flex shrink-0 items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                    <i class="fa-solid fa-download" aria-hidden="true"></i> Exportar esta base (CSV)
                </a>
            @else
                <span class="inline-flex shrink-0 items-center gap-2 rounded-md bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-500" aria-disabled="true">
                    <i class="fa-solid fa-download" aria-hidden="true"></i> Sem registros para exportar
                </span>
            @endif
        </div>

        <div class="overflow-hidden rounded-[10px] border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-2.5">
                <span class="text-[0.7rem] font-bold uppercase tracking-[0.06em] text-slate-500">Visualização prévia (primeiros {{ count($previa) }} registros)</span>
                <span class="text-xs text-slate-500">Colunas: {{ count($colunas) }}</span>
            </div>

            @if ($previa === [])
                <p class="p-8 text-center text-xs font-medium text-slate-500">Nenhum registro encontrado nesta base atualmente.</p>
            @else
                <div class="relative overflow-x-auto">
                    <table class="w-full text-left text-[0.85rem] text-slate-700">
                        <thead class="border-b border-slate-200 bg-[#FAFAFA]">
                            <tr>
                                @foreach ($colunas as $coluna)
                                    <th scope="col" class="whitespace-nowrap px-3 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ $coluna }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @foreach ($previa as $linha)
                                <tr>
                                    @foreach ($colunas as $coluna)
                                        @php($valor = $linha[$coluna] ?? null)
                                        <td class="whitespace-nowrap px-3 py-2 font-medium text-slate-800">
                                            @if ($valor === null || $valor === '')
                                                <span class="text-slate-300">—</span>
                                            @elseif (str_starts_with($coluna, 'id_') || str_contains($coluna, 'codigo'))
                                                <code class="rounded bg-slate-100 px-1 py-0.5 font-mono text-xs text-blue-700">{{ $valor }}</code>
                                            @else
                                                {{ $valor }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
</x-layouts.admin>
