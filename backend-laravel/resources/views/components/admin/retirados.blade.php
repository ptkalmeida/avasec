@props(['id', 'titulo', 'itens', 'explicacao', 'vazio', 'acao'])

{{--
    Seção "Retirados" das telas do administrador (Cursos, Alunos, Equipe).

    Nada é apagado (ADR 12): o que sai do ar fica guardado, com quem tirou, quando e
    por quê. Até esta seção existir, nenhuma tela mostrava isso — o que saía do ar
    virava um limbo, preservado no banco e sem como voltar pela interface.

    `itens` vem de `App\Services\RetiradosDoAdmin`. `acao` é o prefixo da rota que
    reativa (`/admin/cursos`, `/admin/alunos`, `/admin/equipe`); o item acrescenta
    `/{id}/reativar`. As classes `lg:col-*` só valem quando a seção está numa grade
    (Cursos e Alunos); na Equipe elas não fazem nada.
--}}
<section aria-labelledby="{{ $id }}" class="min-w-0 space-y-4 rounded-[10px] border border-slate-200 bg-white p-4 shadow-card lg:col-span-8 lg:col-start-5 lg:p-5">
    <div class="border-b border-slate-200 pb-3">
        <h2 id="{{ $id }}" class="text-[0.875rem] font-bold text-slate-900">{{ $titulo }} ({{ count($itens) }})</h2>
        <p class="mt-0.5 text-xs text-slate-500">{{ $explicacao }}</p>
    </div>

    @forelse ($itens as $item)
        <article class="flex flex-col gap-3 rounded-[10px] border border-slate-200 bg-slate-50/50 p-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-1">
                <strong class="block text-[0.85rem] font-bold text-slate-900">{{ $item['titulo'] }}</strong>

                @if ($item['detalhes'] !== [])
                    <p class="text-xs text-slate-500">{{ implode(' · ', $item['detalhes']) }}</p>
                @endif

                <p class="text-xs text-slate-600">
                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                    Retirado
                    @if ($item['retiradoEm'] !== null) em {{ $item['retiradoEm'] }} @endif
                    por {{ $item['retiradoPor'] ?? '—' }}
                </p>

                <p class="text-xs text-slate-600">
                    Motivo: <span class="{{ $item['motivo'] === null ? 'italic text-slate-400' : '' }}">{{ $item['motivo'] ?? 'não informado' }}</span>
                </p>
            </div>

            <form method="POST" action="{{ $acao }}/{{ $item['id'] }}/reativar" class="shrink-0">
                @csrf
                <button type="submit" aria-label="Reativar {{ $item['titulo'] }}"
                        class="inline-flex items-center gap-1.5 rounded-md border border-emerald-200 bg-white px-3 py-1.5 text-sm font-medium text-emerald-700 hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reativar
                </button>
            </form>
        </article>
    @empty
        <p class="py-4 text-center text-slate-500">{{ $vazio }}</p>
    @endforelse
</section>
