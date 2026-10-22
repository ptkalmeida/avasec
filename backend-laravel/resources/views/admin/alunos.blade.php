{{--
    `/admin/alunos` — a lista de alunos, com busca e filtros.

    Os filtros são um formulário GET: o endereço guarda a escolha, o F5 volta
    para a mesma lista e dá para mandar o link. Os cartões do topo são atalhos
    para os mesmos filtros.
--}}
@php
    $consulta = static fn (array $mudar): string => '/admin/alunos?'.http_build_query(array_filter(array_merge($filtros, $mudar, ['pagina' => null]), static fn ($v): bool => $v !== null && $v !== ''));
    $pagina_url = static fn (int $n): string => $consulta([]).(str_ends_with($consulta([]), '?') ? '' : '&').'pagina='.$n;
    $situacaoDaConta = ['active' => ['verde', 'Ativa'], 'blocked' => ['vermelho', 'Bloqueada'], 'pending_confirmation' => ['ambar', 'Aguardando confirmação']];
    $campo = 'w-full rounded-md border border-slate-200 bg-white px-3 py-1.5 text-slate-900 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30';
    $rotulo = 'mb-1 block text-[0.7rem] font-bold uppercase tracking-[0.06em] text-slate-500';
    $cabecalho = 'px-3 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-500';
    $temFiltro = $filtros['busca'] !== '' || $filtros['curso'] !== '' || $filtros['matricula'] !== '' || $filtros['conta'] !== '';
@endphp
<x-layouts.admin titulo="Alunos" ativo="students"
                 contexto="Cadastro, matrícula e situação acadêmica dos alunos.">

    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-3">
        @foreach ([
            ['', 'fa-users', $resumo['total'], 'Total geral', 'Alunos cadastrados'],
            ['com', 'fa-user-check', $resumo['comMatricula'], 'Com matrícula ativa', 'Cursando agora'],
            ['sem', 'fa-user-xmark', $resumo['semMatricula'], 'Sem matrícula', 'Aguardando vínculo'],
        ] as [$valorDoFiltro, $icone, $numero, $titulo, $nota])
            <a href="{{ $consulta(['matricula' => $valorDoFiltro]) }}"
               @if ($filtros['matricula'] === $valorDoFiltro) aria-current="true" @endif
               class="rounded-[10px] border bg-white p-4 shadow-card transition-colors hover:border-slate-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary
                      {{ $filtros['matricula'] === $valorDoFiltro ? 'border-primary' : 'border-slate-200' }}">
                <span class="flex items-center justify-between">
                    <span class="text-[0.7rem] font-bold uppercase tracking-[0.06em] text-slate-500">{{ $titulo }}</span>
                    <i class="fa-solid {{ $icone }} text-primary" aria-hidden="true"></i>
                </span>
                <span class="mt-1 block text-[1.85rem] font-extrabold leading-none text-slate-900">{{ $numero }}</span>
                <span class="mt-1 block text-xs text-slate-500">{{ $nota }}</span>
            </a>
        @endforeach
    </div>

    @if (session('avisoDosAlunos'))
        <div role="status" class="mt-4 flex items-center gap-2 rounded-[10px] border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ session('avisoDosAlunos') }}
        </div>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-12">

        {{-- Matricular um novo aluno --}}
        <section aria-labelledby="titulo-matricular" class="h-fit space-y-4 rounded-[10px] border border-slate-200 bg-white p-5 shadow-card lg:col-span-4">
            <div>
                <h2 id="titulo-matricular" class="text-[0.875rem] font-bold text-slate-900">Matricular novo aluno</h2>
                <p class="mt-0.5 text-xs text-slate-500">Cria a conta e a matrícula no curso escolhido.</p>
            </div>

            @if ($errors->any())
                <div role="alert" class="rounded-[10px] border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">
                    <p class="mb-1 flex items-center gap-2 font-semibold"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Não foi possível matricular:</p>
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $erro)
                            <li>{{ $erro }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="/admin/alunos" class="space-y-3">
                @csrf
                <div>
                    <label for="n-nome" class="{{ $rotulo }}">Nome completo</label>
                    <input id="n-nome" name="name" type="text" required maxlength="150" value="{{ old('name') }}" placeholder="Ex.: Clara Ribeiro" class="{{ $campo }}">
                </div>
                <div>
                    <label for="n-email" class="{{ $rotulo }}">E-mail acadêmico</label>
                    <input id="n-email" name="email" type="email" required maxlength="254" value="{{ old('email') }}" placeholder="Ex.: clara.ribeiro@lms.edu" class="{{ $campo }}">
                </div>
                <div>
                    <label for="n-cpf" class="{{ $rotulo }}">CPF <span class="font-normal normal-case text-slate-500">— é com ele que o aluno faz login</span></label>
                    <input id="n-cpf" name="cpf" type="text" required inputmode="numeric" maxlength="20" value="{{ old('cpf') }}" placeholder="000.000.000-00" class="{{ $campo }}">
                </div>
                <div>
                    <label for="n-curso" class="{{ $rotulo }}">Curso de matrícula</label>
                    <select id="n-curso" name="curso" required class="{{ $campo }}">
                        <option value="">Selecionar disciplina...</option>
                        @foreach ($cursosParaMatricula as $c)
                            <option value="{{ $c['id'] }}" @selected(old('curso') === $c['id'])>{{ $c['titulo'] }}@if ($c['gestor'] !== null) — {{ $c['gestor'] }}@endif</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Cursos com a vigência vencida não aparecem: o sistema recusa matrícula nova neles.</p>
                </div>
                <div>
                    <label for="n-senha" class="{{ $rotulo }}">Senha inicial (opcional)</label>
                    <input id="n-senha" name="senha" type="text" maxlength="128" autocomplete="off" placeholder="Em branco, o sistema gera uma" class="{{ $campo }}">
                    <p class="mt-1 text-xs text-slate-500">Pelo menos 8 caracteres, com ao menos uma letra e um número.</p>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div class="col-span-2">
                        <label for="n-municipio" class="{{ $rotulo }}">Município</label>
                        <input id="n-municipio" name="municipio" type="text" maxlength="120" value="{{ old('municipio') }}" placeholder="Ex.: Recife" class="{{ $campo }}">
                    </div>
                    <div>
                        <label for="n-uf" class="{{ $rotulo }}">UF</label>
                        <input id="n-uf" name="uf" type="text" maxlength="2" value="{{ old('uf') }}" placeholder="PE" class="{{ $campo }} uppercase">
                    </div>
                </div>
                <div>
                    <label for="n-area" class="{{ $rotulo }}">Área de interesse</label>
                    <input id="n-area" name="areaInteresse" type="text" maxlength="120" value="{{ old('areaInteresse') }}" placeholder="Ex.: Economia Criativa & IA" class="{{ $campo }}">
                </div>
                <button type="submit" class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                    Matricular aluno
                </button>
            </form>
        </section>

        <section aria-labelledby="titulo-lista" class="min-w-0 space-y-4 rounded-[10px] border border-slate-200 bg-white p-4 shadow-card lg:col-span-8 lg:p-5">
            <h2 id="titulo-lista" class="text-[0.875rem] font-bold text-slate-900">Alunos ({{ $resultado['total'] }})</h2>

            <form method="GET" action="/admin/alunos" role="search" class="grid grid-cols-1 gap-3 border-b border-slate-200 pb-4 sm:grid-cols-2 xl:grid-cols-3">
                <div class="sm:col-span-2">
                    <label for="f-busca" class="{{ $rotulo }}">Buscar</label>
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-2.5 top-2.5 text-xs text-slate-400" aria-hidden="true"></i>
                        <input id="f-busca" name="busca" type="search" value="{{ $filtros['busca'] }}" maxlength="100" placeholder="Nome ou e-mail..." class="{{ $campo }} pl-8">
                    </div>
                </div>
                <div>
                    <label for="f-curso" class="{{ $rotulo }}">Curso</label>
                    <select id="f-curso" name="curso" class="{{ $campo }}">
                        <option value="">Todos</option>
                        @foreach ($cursosDoFiltro as $c)
                            <option value="{{ $c['id'] }}" @selected($filtros['curso'] === $c['id'])>{{ $c['titulo'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="f-matricula" class="{{ $rotulo }}">Matrícula</label>
                    <select id="f-matricula" name="matricula" class="{{ $campo }}">
                        <option value="">Todas</option>
                        <option value="com" @selected($filtros['matricula'] === 'com')>Com matrícula ativa</option>
                        <option value="sem" @selected($filtros['matricula'] === 'sem')>Sem matrícula</option>
                        <option value="concluiu" @selected($filtros['matricula'] === 'concluiu')>Já concluíram curso</option>
                    </select>
                </div>
                <div>
                    <label for="f-conta" class="{{ $rotulo }}">Conta</label>
                    <select id="f-conta" name="conta" class="{{ $campo }}">
                        <option value="">Todas</option>
                        <option value="active" @selected($filtros['conta'] === 'active')>Ativa</option>
                        <option value="pending_confirmation" @selected($filtros['conta'] === 'pending_confirmation')>Aguardando confirmação</option>
                        <option value="blocked" @selected($filtros['conta'] === 'blocked')>Bloqueada</option>
                    </select>
                </div>
                <div>
                    <label for="f-ordem" class="{{ $rotulo }}">Ordenar</label>
                    <select id="f-ordem" name="ordem" class="{{ $campo }}">
                        <option value="nome" @selected($filtros['ordem'] === 'nome')>Nome (A a Z)</option>
                        <option value="nome-desc" @selected($filtros['ordem'] === 'nome-desc')>Nome (Z a A)</option>
                        <option value="recentes" @selected($filtros['ordem'] === 'recentes')>Cadastro mais recente</option>
                    </select>
                </div>
                <div class="flex items-end gap-2 sm:col-span-2 xl:col-span-3">
                    <button type="submit" class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">Filtrar</button>
                    @if ($temFiltro)
                        <a href="/admin/alunos" class="rounded-md border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpar filtros</a>
                    @endif
                </div>
            </form>

            <div class="relative overflow-x-auto">
                <table class="w-full text-[0.85rem]">
                    <thead>
                        <tr class="bg-[#FAFAFA] text-left">
                            <th scope="col" class="{{ $cabecalho }}">Aluno</th>
                            <th scope="col" class="{{ $cabecalho }}">Matrícula</th>
                            <th scope="col" class="{{ $cabecalho }} text-center">Progresso médio</th>
                            <th scope="col" class="{{ $cabecalho }} text-center">Conta</th>
                            <th scope="col" class="{{ $cabecalho }} text-right"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($resultado['linhas'] as $l)
                            @php([$tom, $texto] = $situacaoDaConta[$l['conta']] ?? ['neutro', $l['conta']])
                            <tr>
                                <td class="px-3 py-2">
                                    <a href="/admin/alunos/{{ $l['id'] }}" class="font-bold text-slate-900 hover:text-primary focus:outline-none focus-visible:underline">{{ $l['nome'] }}</a>
                                    <span class="block text-xs text-slate-500">{{ $l['email'] ?? '—' }}</span>
                                </td>
                                <td class="px-3 py-2">
                                    @if ($l['cursos'] === [])
                                        <x-admin.selo tom="neutro">Sem matrícula</x-admin.selo>
                                    @else
                                        <ul class="space-y-0.5">
                                            @foreach ($l['cursos'] as $titulo)
                                                <li class="text-slate-700">{{ $titulo }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                    @if ($l['concluidos'] > 0)
                                        <span class="mt-0.5 block text-xs text-slate-500">{{ $l['concluidos'] }} {{ $l['concluidos'] === 1 ? 'curso concluído' : 'cursos concluídos' }}</span>
                                    @endif
                                    @if ($l['multipla'])
                                        <x-admin.selo tom="roxo" class="mt-1">Matrícula múltipla</x-admin.selo>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-center font-semibold text-primary">{{ $l['progresso'] }}%</td>
                                <td class="px-3 py-2 text-center"><x-admin.selo :tom="$tom">{{ $texto }}</x-admin.selo></td>
                                <td class="px-3 py-2 text-right">
                                    <a href="/admin/alunos/{{ $l['id'] }}" class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                                        <i class="fa-regular fa-user" aria-hidden="true"></i> Ficha
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">
                                {{ $temFiltro ? 'Nenhum aluno com esses filtros.' : 'Nenhum aluno cadastrado.' }}
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($resultado['paginas'] > 1)
                <nav aria-label="Páginas" class="flex items-center justify-between border-t border-slate-200 pt-3 text-[0.85rem] text-slate-600">
                    <span>Página {{ $pagina }} de {{ $resultado['paginas'] }}</span>
                    <span class="flex gap-2">
                        @if ($pagina > 1)
                            <a href="{{ $pagina_url($pagina - 1) }}" class="rounded-md border border-slate-200 px-3 py-1.5 font-medium hover:bg-slate-50">Anterior</a>
                        @endif
                        @if ($pagina < $resultado['paginas'])
                            <a href="{{ $pagina_url($pagina + 1) }}" class="rounded-md border border-slate-200 px-3 py-1.5 font-medium hover:bg-slate-50">Próxima</a>
                        @endif
                    </span>
                </nav>
            @endif
        </section>
    </div>
</x-layouts.admin>
