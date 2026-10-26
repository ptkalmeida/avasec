{{--
    `/admin/equipe` — a equipe pedagógica: os gestores de curso e as trilhas de
    cada um. Só leitura, com busca por nome.
--}}
<x-layouts.admin titulo="Equipe Pedagógica" ativo="professors"
                 contexto="Cadastro, vínculo e permissões de professores e gestores.">
    <section aria-labelledby="titulo-equipe" class="space-y-4 rounded-[10px] border border-slate-200 bg-white p-4 shadow-card lg:p-5">
        <div class="flex flex-col gap-3 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 id="titulo-equipe" class="text-[0.875rem] font-bold text-slate-900">
                Gestores ativos no AVA ({{ $total }})
            </h2>

            <form method="GET" action="/admin/equipe" role="search" class="flex w-full gap-2 sm:w-auto">
                <label for="busca-gestor" class="sr-only">Buscar gestor por nome</label>
                <div class="relative flex-1 sm:w-64">
                    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-2.5 top-2.5 text-xs text-slate-400" aria-hidden="true"></i>
                    <input id="busca-gestor" name="busca" type="search" value="{{ $busca }}" maxlength="100"
                           placeholder="Buscar gestor por nome..."
                           class="w-full rounded-md border border-slate-200 bg-slate-50 py-1.5 pl-8 pr-3 text-slate-700 focus:border-primary focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/30">
                </div>
                <button type="submit"
                        class="rounded-md border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                    Buscar
                </button>
                @if ($busca !== '')
                    <a href="/admin/equipe" class="rounded-md px-3 py-1.5 text-sm font-medium text-slate-500 hover:bg-slate-50">Limpar</a>
                @endif
            </form>
        </div>

        @if ($gestores === [])
            <p class="py-6 text-center text-slate-500">
                {{ $busca !== '' ? 'Nenhum gestor com esse nome.' : 'Nenhum gestor cadastrado.' }}
            </p>
        @else
            <ul class="grid grid-cols-1 gap-3.5 md:grid-cols-2">
                @foreach ($gestores as $gestor)
                    <li class="rounded-[10px] border border-slate-200 bg-slate-50/50 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <strong class="text-[0.85rem] font-bold text-slate-900">{{ $gestor['nome'] }}</strong>
                            @if ($gestor['situacao'] === 'blocked')
                                <x-admin.selo tom="vermelho">Bloqueado</x-admin.selo>
                            @elseif ($gestor['situacao'] === 'pending_confirmation')
                                <x-admin.selo tom="ambar">Pendente</x-admin.selo>
                            @endif
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Trilhas sob gestão: {{ count($gestor['cursos']) }}</p>

                        <div class="mt-2.5 flex flex-wrap gap-1.5 border-t border-slate-200 pt-2.5">
                            @forelse ($gestor['cursos'] as $curso)
                                <span class="rounded-md border border-slate-200 bg-white px-2 py-0.5 text-xs text-slate-700">{{ $curso['titulo'] }}</span>
                            @empty
                                <span class="text-xs italic text-slate-500">Nenhuma disciplina vinculada</span>
                            @endforelse
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.admin>
