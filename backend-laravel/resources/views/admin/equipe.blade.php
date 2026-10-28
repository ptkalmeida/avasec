{{--
    `/admin/equipe` — a equipe pedagógica: os gestores de curso e as trilhas de
    cada um, com busca por nome. A coordenação cria gestor (já escolhendo os
    cursos), troca os cursos depois e retira da equipe; quem foi retirado fica na
    seção de baixo, com o botão de reativar. Um curso pode ter vários gestores.
--}}
@php
    $campo = 'w-full rounded-md border border-slate-200 bg-white px-3 py-1.5 text-slate-900 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30';
    $rotulo = 'mb-1 block text-xs font-semibold text-slate-600';
    $erros = $errors->getBag('novoGestor');
@endphp
<x-layouts.admin titulo="Equipe Pedagógica" ativo="professors"
                 contexto="Cadastro, vínculo e permissões de professores e gestores.">
    @if (session('senhaDefinida'))
        @php($definida = session('senhaDefinida'))
        <div role="status" class="mb-4 rounded-[10px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <p class="flex items-center gap-2 font-semibold"><i class="fa-solid fa-key" aria-hidden="true"></i> Senha de {{ $definida['nome'] }}</p>
            <p class="mt-1">Repasse esta senha por um canal seguro. Ela não será mostrada de novo.</p>
            <p class="mt-2"><code class="rounded bg-white px-2 py-1 font-mono text-base font-bold text-slate-900">{{ $definida['senha'] }}</code></p>
        </div>
    @endif

    @if (session('avisoDaEquipe'))
        <div role="status" class="mb-4 flex items-center gap-2 rounded-[10px] border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ session('avisoDaEquipe') }}
        </div>
    @endif

    {{-- Criar o acesso de um gestor --}}
    <details class="mb-4 rounded-[10px] border border-slate-200 bg-white p-4 shadow-card lg:p-5" @if ($erros->any()) open @endif>
        <summary class="flex cursor-pointer list-none items-center gap-2 text-[0.875rem] font-bold text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
            <i class="fa-solid fa-user-plus text-primary" aria-hidden="true"></i> Adicionar gestor
        </summary>

        <form method="POST" action="/admin/equipe" class="mt-3 space-y-3">
            @csrf
            @if ($erros->any())
                <div role="alert" class="rounded-md border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">
                    <ul class="list-inside list-disc">
                        @foreach ($erros->all() as $erro)
                            <li>{{ $erro }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-3 md:grid-cols-3">
                <div>
                    <label for="novo-gestor-nome" class="{{ $rotulo }}">Nome completo</label>
                    <input id="novo-gestor-nome" name="nome" type="text" required maxlength="150" value="{{ old('nome') }}" class="{{ $campo }}">
                </div>
                <div>
                    <label for="novo-gestor-email" class="{{ $rotulo }}">E-mail</label>
                    <input id="novo-gestor-email" name="email" type="email" required maxlength="254" value="{{ old('email') }}" class="{{ $campo }}">
                </div>
                <div>
                    <label for="novo-gestor-senha" class="{{ $rotulo }}">Senha inicial (opcional)</label>
                    <input id="novo-gestor-senha" name="senha" type="text" maxlength="128" autocomplete="off" placeholder="Em branco: o sistema gera uma" class="{{ $campo }}">
                </div>
            </div>

            <fieldset>
                <legend class="{{ $rotulo }}">Cursos que este gestor vai gerenciar (pode ser nenhum; um curso pode ter vários gestores)</legend>
                @if ($cursosDisponiveis === [])
                    <p class="text-xs italic text-slate-500">Ainda não há cursos.</p>
                @else
                    <div class="grid max-h-48 gap-1 overflow-y-auto rounded-md border border-slate-200 bg-slate-50/50 p-2 sm:grid-cols-2">
                        @foreach ($cursosDisponiveis as $c)
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="cursos[]" value="{{ $c['id'] }}" @checked(in_array($c['id'], (array) old('cursos', []), true))>
                                {{ $c['titulo'] }}
                            </label>
                        @endforeach
                    </div>
                @endif
            </fieldset>

            <button type="submit" class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-white hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                Criar acesso do gestor
            </button>
        </form>
    </details>

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
                    @php($meusCursos = array_column($gestor['cursos'], 'id'))
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

                        <details class="mt-3 rounded-md border border-slate-200 bg-white p-3">
                            <summary class="flex cursor-pointer list-none items-center gap-2 text-[0.8rem] font-semibold text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Editar cursos
                            </summary>
                            <form method="POST" action="/admin/equipe/{{ $gestor['id'] }}/cursos" class="mt-2 space-y-2">
                                @csrf
                                <p class="text-xs text-slate-600">Marque os cursos que {{ $gestor['nome'] }} gerencia. Um curso pode ter mais de um gestor.</p>
                                <div class="grid max-h-44 gap-1 overflow-y-auto rounded-md border border-slate-200 bg-slate-50/50 p-2">
                                    @foreach ($cursosDisponiveis as $c)
                                        <label class="flex items-center gap-2 text-sm text-slate-700">
                                            <input type="checkbox" name="cursos[]" value="{{ $c['id'] }}" @checked(in_array($c['id'], $meusCursos, true))>
                                            {{ $c['titulo'] }}
                                        </label>
                                    @endforeach
                                </div>
                                <button type="submit" class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-white hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">Salvar cursos</button>
                            </form>
                        </details>

                        {{-- Alterar o login (e-mail) e a senha: a senha nova aparece UMA vez, no topo, para repassar. --}}
                        @php($errosAcesso = $errors->getBag('acessoGestor'.$gestor['id']))
                        <details class="mt-2 rounded-md border border-slate-200 bg-white p-3" @if ($errosAcesso->any()) open @endif>
                            <summary class="flex cursor-pointer list-none items-center gap-2 text-[0.8rem] font-semibold text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                                <i class="fa-solid fa-key" aria-hidden="true"></i> Alterar login e senha
                            </summary>
                            <form method="POST" action="/admin/equipe/{{ $gestor['id'] }}/acesso" class="mt-2 space-y-2">
                                @csrf
                                @if ($errosAcesso->any())
                                    <div role="alert" class="rounded-md border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">
                                        <ul class="list-inside list-disc">
                                            @foreach ($errosAcesso->all() as $erro)
                                                <li>{{ $erro }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                <p class="text-xs text-slate-600">O login de {{ $gestor['nome'] }} é o e-mail. Ao salvar, quem estava com a conta aberta precisa entrar de novo.</p>
                                <label for="acesso-email-{{ $gestor['id'] }}" class="{{ $rotulo }}">E-mail (login)</label>
                                <input id="acesso-email-{{ $gestor['id'] }}" name="email" type="email" required maxlength="254" value="{{ old('email', $gestor['email'] ?? '') }}" class="{{ $campo }}">
                                <label for="acesso-senha-{{ $gestor['id'] }}" class="{{ $rotulo }}">Nova senha (opcional)</label>
                                <input id="acesso-senha-{{ $gestor['id'] }}" name="senha" type="text" maxlength="128" autocomplete="off" placeholder="Em branco: a senha atual continua" class="{{ $campo }}">
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <input type="checkbox" name="gerar_senha" value="1"> Gerar uma senha nova (mostrada uma única vez)
                                </label>
                                <button type="submit" class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-white hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">Salvar acesso</button>
                            </form>
                        </details>

                        {{-- Retirar da equipe: duas etapas, sem JavaScript --}}
                        <details class="mt-2 rounded-md border border-red-100 bg-red-50/50 p-3">
                            <summary class="flex cursor-pointer list-none items-center gap-2 text-[0.8rem] font-semibold text-red-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300">
                                <i class="fa-solid fa-user-slash" aria-hidden="true"></i> Retirar da equipe
                            </summary>
                            <form method="POST" action="/admin/equipe/{{ $gestor['id'] }}/retirar" class="mt-2 space-y-2">
                                @csrf
                                <p class="text-xs text-slate-600">
                                    {{ $gestor['nome'] }} deixa de entrar na plataforma. Nada é apagado: o cadastro e os cursos continuam guardados,
                                    e os cursos ficam sem gestor ativo até você escolher outra pessoa ou reativá-la.
                                </p>
                                <label for="motivo-{{ $gestor['id'] }}" class="{{ $rotulo }}">Motivo (opcional)</label>
                                <input id="motivo-{{ $gestor['id'] }}" name="motivo" type="text" maxlength="500" class="{{ $campo }}">
                                <button type="submit" class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400 focus-visible:ring-offset-2">Sim, retirar da equipe</button>
                            </form>
                        </details>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($semGestor !== [])
        <section aria-labelledby="titulo-sem-gestor" class="mt-6 rounded-[10px] border border-amber-200 bg-amber-50/50 p-4 lg:p-5">
            <h2 id="titulo-sem-gestor" class="text-[0.875rem] font-bold text-slate-900">Cursos sem gestor ativo ({{ count($semGestor) }})</h2>
            <p class="mt-1 text-xs text-slate-600">Só a coordenação edita estes cursos. Para atribuí-los, use "Editar cursos" no gestor escolhido.</p>
            <div class="mt-2.5 flex flex-wrap gap-1.5">
                @foreach ($semGestor as $curso)
                    <span class="rounded-md border border-amber-200 bg-white px-2 py-0.5 text-xs text-slate-700">{{ $curso['titulo'] }}</span>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Quem foi retirado da equipe continua guardado — e aparece, separado dos ativos. --}}
    <div class="mt-6">
        <x-admin.retirados id="titulo-retirados" titulo="Gestores retirados da equipe" :itens="$retirados" acao="/admin/equipe"
                           explicacao="Nada foi apagado: o cadastro e o vínculo com as disciplinas estão guardados. Ao reativar, a conta volta ativa e a pessoa entra com um login novo."
                           vazio="Nenhum gestor foi retirado da equipe." />
    </div>
</x-layouts.admin>
