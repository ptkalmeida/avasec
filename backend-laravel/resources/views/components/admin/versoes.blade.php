@props(['versoes' => [], 'acao', 'oque' => 'conteúdo'])

{{--
    Histórico de versões (ADR 021): o que estava no ar ANTES de cada alteração.

    Cada linha mostra quando aquela versão foi substituída e por quem, um resumo
    do que ela dizia, e o botão de voltar a ela. Restaurar não apaga nada: o que
    está no ar agora entra no histórico no mesmo instante, então dá para desfazer
    a própria restauração.

    `$versoes` é uma lista de ['id', 'quando', 'porNome', 'origem', 'resumo'].
    `$acao` é o início do endereço do POST; o id da versão e `/restaurar` vêm depois.
    Fica FORA do formulário de edição (formulário dentro de formulário não existe).
--}}
<section aria-labelledby="titulo-versoes" class="space-y-3 rounded-[10px] border border-slate-200 bg-white p-5 shadow-card">
    <div>
        <h2 id="titulo-versoes" class="text-[0.875rem] font-bold text-slate-900">Versões anteriores</h2>
        <p class="mt-0.5 text-[0.8rem] text-slate-500">
            Cada vez que o {{ $oque }} é salvo, o que estava publicado fica guardado aqui. Voltar a uma versão não apaga nada.
        </p>
    </div>

    @if ($versoes === [])
        <p class="text-[0.85rem] italic text-slate-500">Ainda não há versões anteriores: nada foi alterado desde que o histórico passou a existir.</p>
    @else
        <ol class="divide-y divide-slate-200">
            @foreach ($versoes as $v)
                <li class="flex flex-wrap items-start justify-between gap-3 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-[0.85rem] font-semibold text-slate-900">
                            {{ $v['quando'] }}
                            @if ($v['origem'] === 'restauracao')
                                <x-admin.selo tom="roxo" class="ml-1">Antes de uma restauração</x-admin.selo>
                            @endif
                        </p>
                        <p class="text-[0.8rem] text-slate-500">
                            Substituída por {{ $v['porNome'] ?? 'alguém sem registro' }}
                        </p>
                        <p class="mt-1 break-words text-[0.85rem] text-slate-700">{{ $v['resumo'] }}</p>
                    </div>
                    <details class="shrink-0">
                        <summary class="cursor-pointer rounded-md border border-slate-200 bg-white px-3 py-1.5 text-[0.85rem] font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                            Voltar a esta versão
                        </summary>
                        <form method="POST" action="{{ $acao }}/{{ $v['id'] }}/restaurar" class="mt-2 space-y-2 rounded-md border border-amber-200 bg-amber-50 p-3">
                            @csrf
                            <p class="text-[0.8rem] text-amber-900">O que está no ar agora será guardado no histórico, e esta versão passa a valer.</p>
                            <button type="submit" class="rounded-md bg-primary px-3 py-1.5 text-[0.85rem] font-semibold text-white hover:bg-primary-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                Confirmar: voltar a esta versão
                            </button>
                        </form>
                    </details>
                </li>
            @endforeach
        </ol>
    @endif
</section>
