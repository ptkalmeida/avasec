{{--
    `/admin/cursos` — "Cursos & Trilhas": prover um curso e cuidar dos que existem.

    Cada ação é um formulário que grava na hora e volta para o MESMO curso
    (`?curso=` abre o bloco dele, e o endereço funciona sem JavaScript). O que o
    painel React gravava ao mexer num campo agora tem botão: nada muda sem a
    pessoa pedir.
--}}
@php
    $visibilidade = [1 => ['verde', 'Publicado'], 2 => ['ambar', 'Restrito'], 3 => ['neutro', 'Rascunho']];
    $rotuloDoCampo = 'mb-1 block text-[0.7rem] font-bold uppercase tracking-[0.06em] text-slate-500';
    $campo = 'w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-slate-900 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30';
    $botao = 'rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2';
    $botaoContorno = 'rounded-md border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary';
@endphp
<x-layouts.admin titulo="Cursos & Trilhas" ativo="courses"
                 contexto="Cursos, aulas e material didático da grade.">

    @if (session('avisoDosCursos'))
        <div role="status" class="mb-4 flex items-center gap-2 rounded-[10px] border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ session('avisoDosCursos') }}
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-[10px] border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">
            <p class="mb-1 flex items-center gap-2 font-semibold"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Não foi possível salvar:</p>
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">

        {{-- Prover um curso --}}
        <section aria-labelledby="titulo-prover" class="h-fit space-y-4 rounded-[10px] border border-slate-200 bg-white p-5 shadow-card lg:col-span-4">
            <div>
                <h2 id="titulo-prover" class="text-[0.875rem] font-bold text-slate-900">Prover disciplina</h2>
                <p class="mt-0.5 text-xs text-slate-500">Cadastre um novo curso na plataforma letiva.</p>
            </div>

            <form method="POST" action="/admin/cursos" class="space-y-3">
                @csrf

                <div>
                    <label for="novo-titulo" class="{{ $rotuloDoCampo }}">Título do curso</label>
                    <input id="novo-titulo" name="title" type="text" required minlength="3" maxlength="200"
                           value="{{ old('title') }}" placeholder="Ex.: Programação funcional" class="{{ $campo }}">
                </div>

                <div>
                    <label for="nova-area" class="{{ $rotuloDoCampo }}">Área acadêmica / categoria</label>
                    {{-- Texto livre com sugestões: escrever uma área nova é "habilitar" a área. --}}
                    <input id="nova-area" name="category" type="text" required maxlength="120" list="areas-existentes"
                           value="{{ old('category') }}" class="{{ $campo }}">
                    <datalist id="areas-existentes">
                        @foreach ($categorias as $area)
                            <option value="{{ $area }}"></option>
                        @endforeach
                    </datalist>
                    <p class="mt-1 text-xs text-slate-500">Escolha uma área existente ou escreva uma nova.</p>
                </div>

                <div>
                    <label for="novo-responsavel" class="{{ $rotuloDoCampo }}">Professor responsável</label>
                    <select id="novo-responsavel" name="instructorId" class="{{ $campo }}">
                        @foreach ($instrutores as $pessoa)
                            <option value="{{ $pessoa['id'] }}" @selected(old('instructorId') === $pessoa['id'])>{{ $pessoa['nome'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="nova-ementa" class="{{ $rotuloDoCampo }}">Ementa / descrição curta (opcional)</label>
                    <textarea id="nova-ementa" name="description" rows="3" maxlength="5000"
                              placeholder="Visão abrangente para orientar a admissão dos alunos..."
                              class="{{ $campo }} resize-none">{{ old('description') }}</textarea>
                </div>

                <div>
                    <label for="nova-vigencia" class="{{ $rotuloDoCampo }} flex items-center justify-between gap-2">
                        <span>Vigência de exibição / validade do contrato</span>
                        <x-admin.selo tom="ambar">Proteção jurídica</x-admin.selo>
                    </label>
                    <input id="nova-vigencia" name="vigencia" type="date" value="{{ old('vigencia') }}" class="{{ $campo }}">
                    <p class="mt-1 text-xs text-slate-500">Em branco, o curso fica no ar sem data de vencimento.</p>
                </div>

                <div class="grid grid-cols-2 items-end gap-3">
                    <div>
                        <label for="novo-tipo" class="{{ $rotuloDoCampo }}">Tipo de curso</label>
                        <select id="novo-tipo" name="courseType" class="{{ $campo }}">
                            <option value="fixo" @selected(old('courseType', 'fixo') === 'fixo')>Gravado (fixo)</option>
                            <option value="ao_vivo" @selected(old('courseType') === 'ao_vivo')>Síncrono (ao vivo)</option>
                        </select>
                    </div>
                    <label class="flex cursor-pointer items-center gap-2 pb-2">
                        <input type="checkbox" name="hasChat" value="1" class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary"
                               @checked(old('title') === null || old('hasChat'))>
                        <span class="text-[0.8rem] font-semibold text-slate-700">Ativar chat do curso</span>
                    </label>
                </div>

                <button type="submit" class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                    Disponibilizar curso no catálogo
                </button>
            </form>

            <div class="space-y-1 border-t border-slate-200 pt-4">
                <p class="{{ $rotuloDoCampo }}">Áreas em uso</p>
                <div class="flex max-h-24 flex-wrap gap-1 overflow-y-auto">
                    @forelse ($categorias as $area)
                        <x-admin.selo tom="azul" class="!normal-case">{{ $area }}</x-admin.selo>
                    @empty
                        <span class="text-xs italic text-slate-500">Nenhuma área ainda.</span>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- Os cursos do catálogo --}}
        <section aria-labelledby="titulo-catalogo" class="space-y-4 rounded-[10px] border border-slate-200 bg-white p-5 shadow-card lg:col-span-8">
            <div class="flex flex-col gap-3 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 id="titulo-catalogo" class="text-[0.875rem] font-bold text-slate-900">Cursos ativos no catálogo ({{ $totalNoCatalogo }})</h2>

                <form method="GET" action="/admin/cursos" role="search" class="flex w-full gap-2 sm:w-auto">
                    <label for="busca-curso" class="sr-only">Pesquisar curso ou professor</label>
                    <div class="relative flex-1 sm:w-64">
                        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-2.5 top-2.5 text-xs text-slate-400" aria-hidden="true"></i>
                        <input id="busca-curso" name="busca" type="search" value="{{ $busca }}" maxlength="100"
                               placeholder="Pesquisar disciplina ou professor..."
                               class="w-full rounded-md border border-slate-200 bg-slate-50 py-1.5 pl-8 pr-3 text-slate-700 focus:border-primary focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/30">
                    </div>
                    <button type="submit" class="{{ $botaoContorno }}">Buscar</button>
                    @if ($busca !== '')
                        <a href="/admin/cursos" class="rounded-md px-3 py-1.5 text-sm font-medium text-slate-500 hover:bg-slate-50">Limpar</a>
                    @endif
                </form>
            </div>

            @forelse ($cursos as $c)
                @php($aberto = $abrir === $c['id'])
                <article id="curso-{{ $c['id'] }}" class="space-y-3 rounded-[10px] border border-slate-200 bg-slate-50/50 p-4">
                    <div class="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                        <div class="min-w-0 space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-[0.9rem] font-extrabold text-slate-900">{{ $c['titulo'] }}</h3>
                                @if ($c['categoria'] !== null)
                                    <x-admin.selo tom="neutro">{{ $c['categoria'] }}</x-admin.selo>
                                @endif
                                @php([$tomV, $textoV] = $visibilidade[$c['visibilidade']] ?? ['neutro', 'Sem nível'])
                                <x-admin.selo :tom="$tomV">{{ $textoV }}</x-admin.selo>
                            </div>
                            <p class="line-clamp-2 text-xs text-slate-600">{{ $c['descricao'] }}</p>
                            <p class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                <span>Aulas: <strong class="text-slate-700">{{ $c['aulas'] }}</strong></span>
                                <span>Avaliações: <strong class="text-slate-700">{{ $c['avaliacoes'] }}</strong></span>
                                <span>Professor atual: <strong class="text-slate-800">{{ $c['instrutorNome'] ?? 'não definido' }}</strong></span>
                                <span>Tipo: <strong class="text-slate-700">{{ $c['tipo'] === 'ao_vivo' ? 'Síncrono (ao vivo)' : 'Gravado (fixo)' }}</strong></span>
                                <span>Chat: <strong class="text-slate-700">{{ $c['chat'] ? 'ativo' : 'desativado' }}</strong></span>
                                <span>Vigência: <strong class="text-slate-700">{{ $c['vigenciaTexto'] ?? 'sem limite (permanente)' }}</strong></span>
                            </p>
                        </div>

                        <div class="shrink-0">
                            @if ($c['vencida'])
                                <x-admin.selo tom="ambar"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Expirado (arquivado)</x-admin.selo>
                            @else
                                <x-admin.selo tom="azul">Ativo no AVA</x-admin.selo>
                            @endif
                        </div>
                    </div>

                    {{-- Alunos matriculados de verdade --}}
                    <details class="group border-t border-slate-200 pt-2">
                        <summary class="flex cursor-pointer list-none items-center gap-2 text-[0.8rem] font-semibold text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                            <i class="fa-solid fa-users" aria-hidden="true"></i>
                            <span>Alunos ativos ({{ count($c['alunos']) }})</span>
                            <span class="font-normal text-slate-500 group-open:hidden">Ver detalhes</span>
                            <span class="hidden font-normal text-slate-500 group-open:inline">Ocultar</span>
                        </summary>
                        @if ($c['alunos'] === [])
                            <p class="mt-2 text-xs italic text-slate-500">Nenhum aluno matriculado neste curso.</p>
                        @else
                            <ul class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach ($c['alunos'] as $aluno)
                                    <li class="flex items-center gap-2.5 rounded-md border border-slate-200 bg-white p-2">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-50 text-[0.7rem] font-bold uppercase text-blue-600" aria-hidden="true">{{ mb_substr($aluno['nome'], 0, 1) }}</span>
                                        <span class="min-w-0 leading-tight">
                                            <span class="block truncate text-[0.8rem] font-semibold text-slate-800">{{ $aluno['nome'] }}</span>
                                            <span class="block truncate text-xs text-slate-500">{{ $aluno['email'] ?? '—' }}</span>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </details>

                    {{-- Cuidar do curso --}}
                    <details class="group border-t border-slate-200 pt-2" @if ($aberto) open @endif>
                        <summary class="flex cursor-pointer list-none items-center gap-2 text-[0.8rem] font-semibold text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                            <i class="fa-solid fa-sliders" aria-hidden="true"></i> Gerenciar este curso
                        </summary>

                        <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">

                            <form method="POST" action="/admin/cursos/{{ $c['id'] }}/instrutor" class="space-y-2 rounded-md border border-slate-200 bg-white p-3">
                                @csrf
                                <label for="resp-{{ $c['id'] }}" class="{{ $rotuloDoCampo }}">Trocar professor</label>
                                <select id="resp-{{ $c['id'] }}" name="instructorId" class="{{ $campo }}">
                                    @foreach ($instrutores as $pessoa)
                                        <option value="{{ $pessoa['id'] }}" @selected($c['instrutorId'] === $pessoa['id'])>{{ $pessoa['nome'] }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="{{ $botao }}">Trocar professor</button>
                            </form>

                            <form method="POST" action="/admin/cursos/{{ $c['id'] }}/propriedades" class="space-y-2 rounded-md border border-slate-200 bg-white p-3">
                                @csrf
                                <label for="tipo-{{ $c['id'] }}" class="{{ $rotuloDoCampo }}">Tipo</label>
                                <select id="tipo-{{ $c['id'] }}" name="courseType" class="{{ $campo }}">
                                    <option value="fixo" @selected($c['tipo'] === 'fixo')>Gravado (fixo)</option>
                                    <option value="ao_vivo" @selected($c['tipo'] === 'ao_vivo')>Síncrono (ao vivo)</option>
                                </select>
                                <label class="flex cursor-pointer items-center gap-2">
                                    <input type="checkbox" name="hasChat" value="1" @checked($c['chat']) class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                                    <span class="text-[0.8rem] font-semibold text-slate-700">Ativar chat</span>
                                </label>
                                <button type="submit" class="{{ $botao }}">Salvar tipo e chat</button>
                            </form>

                            <form method="POST" action="/admin/cursos/{{ $c['id'] }}/vigencia" class="space-y-2 rounded-md border border-slate-200 bg-white p-3">
                                @csrf
                                <p class="{{ $rotuloDoCampo }}">Vigência</p>
                                <div x-data="{ prazo: 'data' }" class="space-y-2">
                                    <label class="flex items-center gap-2 text-[0.8rem] text-slate-700">
                                        <input type="radio" name="prazo" value="data" x-model="prazo" checked class="text-primary focus:ring-primary"> Até a data
                                    </label>
                                    <input type="date" name="ate" value="{{ $c['vigenciaDia'] }}" aria-label="Data de vigência" :disabled="prazo !== 'data'" class="{{ $campo }}">
                                    <label class="flex items-center gap-2 text-[0.8rem] text-slate-700">
                                        <input type="radio" name="prazo" value="1-ano" x-model="prazo" class="text-primary focus:ring-primary"> Mais um ano, a partir de hoje
                                    </label>
                                    <label class="flex items-center gap-2 text-[0.8rem] text-slate-700">
                                        <input type="radio" name="prazo" value="permanente" x-model="prazo" class="text-primary focus:ring-primary"> Sem limite (permanente)
                                    </label>
                                </div>
                                <button type="submit" class="{{ $botao }}">{{ $c['vencida'] ? 'Reativar curso' : 'Salvar vigência' }}</button>
                                @if ($c['vencida'])
                                    <p class="text-xs text-slate-500">Para reativar, escolha uma nova data ou "mais um ano".</p>
                                @endif
                            </form>

                            <form method="POST" action="/admin/cursos/{{ $c['id'] }}/capa" enctype="multipart/form-data" class="space-y-2 rounded-md border border-slate-200 bg-white p-3">
                                @csrf
                                <p class="{{ $rotuloDoCampo }}">Capa</p>
                                @if ($c['capa'] !== null)
                                    <img src="{{ $c['capa'] }}" alt="Capa atual do curso" class="h-14 w-24 rounded border border-slate-200 object-cover">
                                @else
                                    <p class="text-xs italic text-slate-500">Sem capa.</p>
                                @endif
                                <label for="capa-{{ $c['id'] }}" class="sr-only">Imagem da capa (PNG, JPEG ou WebP, até 4 MB)</label>
                                <input id="capa-{{ $c['id'] }}" name="capa" type="file" accept="image/png,image/jpeg,image/webp" class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-md file:border file:border-slate-200 file:bg-white file:px-3 file:py-1.5 file:text-sm file:font-medium">
                                <p class="text-xs text-slate-500">PNG, JPEG ou WebP, até 4 MB.</p>
                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" class="{{ $botao }}">Enviar capa</button>
                                    @if ($c['capa'] !== null)
                                        <button type="submit" name="remover" value="1" formnovalidate class="{{ $botaoContorno }}">Remover capa</button>
                                    @endif
                                </div>
                            </form>
                        </div>

                        {{-- Retirar do ar: duas etapas, sem JavaScript. --}}
                        <details class="mt-4 rounded-md border border-red-100 bg-red-50/50 p-3">
                            <summary class="flex cursor-pointer list-none items-center gap-2 text-[0.8rem] font-semibold text-red-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i> Retirar do ar
                            </summary>
                            <form method="POST" action="/admin/cursos/{{ $c['id'] }}/retirar" class="mt-2 space-y-2">
                                @csrf
                                <p class="text-xs text-slate-600">
                                    O curso "{{ $c['titulo'] }}" sai do ar para os alunos. Nada é apagado: o registro, as aulas e o
                                    histórico de quem o cursou ficam preservados.
                                </p>
                                <label for="motivo-{{ $c['id'] }}" class="{{ $rotuloDoCampo }}">Motivo (opcional)</label>
                                <input id="motivo-{{ $c['id'] }}" name="motivo" type="text" maxlength="500" class="{{ $campo }}">
                                <button type="submit" class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400 focus-visible:ring-offset-2">
                                    Sim, retirar do ar
                                </button>
                            </form>
                        </details>
                    </details>
                </article>
            @empty
                <p class="py-6 text-center text-slate-500">
                    {{ $busca !== '' ? 'Nenhum curso encontrado para essa busca.' : 'Nenhum curso no catálogo.' }}
                </p>
            @endforelse
        </section>
    </div>
</x-layouts.admin>
