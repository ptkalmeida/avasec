{{--
    `/admin/alunos/{id}` — a ficha de um aluno: o que o banco sabe dele.

    Endereçada pelo id da conta, e não pelo nome: homônimo existe de verdade
    (há "Homonimo Teste" no banco de testes), e dois alunos de mesmo nome
    dividiriam o mesmo endereço.
--}}
@php
    $situacaoDaConta = ['active' => ['verde', 'Ativa'], 'blocked' => ['vermelho', 'Bloqueada'], 'pending_confirmation' => ['ambar', 'Aguardando confirmação']];
    [$tomDaConta, $textoDaConta] = $situacaoDaConta[$aluno['conta']] ?? ['neutro', $aluno['conta']];
    $rotulo = 'text-[0.7rem] font-bold uppercase tracking-[0.06em] text-slate-500';
    $cabecalho = 'px-3 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-500';
@endphp
<x-layouts.admin :titulo="$aluno['nome']" ativo="students" contexto="Ficha do aluno">

    <nav aria-label="Trilha de navegação" class="mb-4 text-xs text-slate-500">
        <a href="/admin/alunos" class="font-medium text-primary hover:underline"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i>Voltar para Alunos</a>
    </nav>

    @if (session('senhaDefinida'))
        @php($definida = session('senhaDefinida'))
        <div role="status" class="mb-4 rounded-[10px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <p class="flex items-center gap-2 font-semibold"><i class="fa-solid fa-key" aria-hidden="true"></i> Senha de {{ $definida['nome'] }}</p>
            <p class="mt-1">Repasse esta senha por um canal seguro. Ela não será mostrada de novo.</p>
            <p class="mt-2"><code class="rounded bg-white px-2 py-1 font-mono text-base font-bold text-slate-900">{{ $definida['senha'] }}</code></p>
        </div>
    @endif

    @if (session('avisoDoAluno'))
        <div role="status" class="mb-4 flex items-center gap-2 rounded-[10px] border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ session('avisoDoAluno') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">

        <section aria-labelledby="titulo-dados" class="h-fit space-y-4 rounded-[10px] border border-slate-200 bg-white p-5 shadow-card lg:col-span-4">
            <div class="flex items-start justify-between gap-2">
                <h2 id="titulo-dados" class="text-[0.875rem] font-bold text-slate-900">Dados da conta</h2>
                <x-admin.selo :tom="$tomDaConta">{{ $textoDaConta }}</x-admin.selo>
            </div>

            <dl class="space-y-3">
                <div><dt class="{{ $rotulo }}">Nome</dt><dd class="mt-0.5 text-slate-900">{{ $aluno['nome'] }}</dd></div>
                @if ($aluno['nome_social'] !== null)
                    <div><dt class="{{ $rotulo }}">Nome social</dt><dd class="mt-0.5 text-slate-900">{{ $aluno['nome_social'] }}</dd></div>
                @endif
                <div><dt class="{{ $rotulo }}">E-mail</dt><dd class="mt-0.5 break-all text-slate-900">{{ $aluno['email'] ?? '—' }}</dd></div>
                <div><dt class="{{ $rotulo }}">CPF</dt><dd class="mt-0.5 text-slate-900">{{ $aluno['cpf'] ?? '—' }}</dd></div>
                @if ($aluno['celular'] !== null)
                    <div><dt class="{{ $rotulo }}">Celular</dt><dd class="mt-0.5 text-slate-900">{{ $aluno['celular'] }}</dd></div>
                @endif
                <div>
                    <dt class="{{ $rotulo }}">Município / UF</dt>
                    <dd class="mt-0.5 text-slate-900">{{ $aluno['municipio'] ?? '—' }}{{ $aluno['uf'] !== null ? ' / '.$aluno['uf'] : '' }}</dd>
                </div>
                <div><dt class="{{ $rotulo }}">Área de interesse</dt><dd class="mt-0.5 text-slate-900">{{ $aluno['area'] ?? '—' }}</dd></div>
                <div><dt class="{{ $rotulo }}">Cadastro em</dt><dd class="mt-0.5 text-slate-900">{{ $aluno['cadastro'] ?? '—' }}</dd></div>
            </dl>
        </section>

        <div class="min-w-0 space-y-6 lg:col-span-8">
            <section aria-labelledby="titulo-matriculas" class="space-y-3 rounded-[10px] border border-slate-200 bg-white p-5 shadow-card">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="titulo-matriculas" class="text-[0.875rem] font-bold text-slate-900">Matrículas e progresso</h2>
                    @if ($aluno['multipla'])
                        <x-admin.selo tom="roxo">Matrícula múltipla permitida</x-admin.selo>
                    @endif
                </div>

                @if ($aluno['matriculas'] === [])
                    <p class="text-slate-500">Este aluno não está matriculado em nenhum curso.</p>
                @else
                    <div class="relative overflow-x-auto">
                        <table class="w-full text-[0.85rem]">
                            <thead>
                                <tr class="bg-[#FAFAFA] text-left">
                                    <th scope="col" class="{{ $cabecalho }}">Curso</th>
                                    <th scope="col" class="{{ $cabecalho }}">Matrícula</th>
                                    <th scope="col" class="{{ $cabecalho }} text-center">Aulas feitas</th>
                                    <th scope="col" class="{{ $cabecalho }} text-center">Progresso</th>
                                    <th scope="col" class="{{ $cabecalho }} text-right">Certificado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @foreach ($aluno['matriculas'] as $m)
                                    <tr>
                                        <td class="px-3 py-2 font-bold text-slate-900">{{ $m['curso'] }}</td>
                                        <td class="px-3 py-2"><x-admin.selo :tom="$m['tipo'] === 'Principal' ? 'azul' : 'roxo'">{{ $m['tipo'] }}</x-admin.selo></td>
                                        <td class="px-3 py-2 text-center text-slate-600">{{ $m['aulasFeitas'] }} de {{ $m['aulas'] }}</td>
                                        <td class="px-3 py-2 text-center font-semibold text-primary">{{ $m['progresso'] }}%</td>
                                        <td class="px-3 py-2 text-right">
                                            @if ($m['certificado'])
                                                <x-admin.selo tom="verde">Emitido</x-admin.selo>
                                            @else
                                                <x-admin.selo tom="ambar">Pendente</x-admin.selo>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($aluno['concluidos'] !== [])
                    <div class="border-t border-slate-200 pt-3">
                        <p class="{{ $rotulo }} mb-1">Cursos concluídos</p>
                        <ul class="flex flex-wrap gap-1.5">
                            @foreach ($aluno['concluidos'] as $titulo)
                                <li class="rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs text-slate-700">{{ $titulo }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            <section aria-labelledby="titulo-certificados" class="space-y-3 rounded-[10px] border border-slate-200 bg-white p-5 shadow-card">
                <h2 id="titulo-certificados" class="text-[0.875rem] font-bold text-slate-900">Certificados emitidos ({{ count($aluno['certificados']) }})</h2>
                @forelse ($aluno['certificados'] as $c)
                    <p class="flex items-center gap-2 text-slate-700">
                        <i class="fa-solid fa-certificate text-primary" aria-hidden="true"></i>
                        <span>{{ $c['curso'] }}</span>
                        @if ($c['emitidoEm'] !== null)
                            <span class="text-xs text-slate-500">— {{ $c['emitidoEm'] }}</span>
                        @endif
                    </p>
                @empty
                    <p class="text-slate-500">Nenhum certificado emitido para este aluno.</p>
                @endforelse
            </section>

            {{-- O histórico lista os cursos concluídos com certificado. Emitir acrescenta uma emissão nova e o aluno a vê na área dele. --}}
            <section aria-labelledby="titulo-historico" class="space-y-3 rounded-[10px] border border-slate-200 bg-white p-5 shadow-card">
                <h2 id="titulo-historico" class="text-[0.875rem] font-bold text-slate-900">Histórico escolar ({{ count($historicos) }})</h2>
                @error('historico')
                    <p role="alert" class="rounded-[10px] border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>
                @enderror
                @forelse ($historicos as $h)
                    <p class="flex items-center gap-2 text-slate-700">
                        <i class="fa-solid fa-file-pdf text-primary" aria-hidden="true"></i>
                        <a href="/admin/alunos/{{ $aluno['id'] }}/historico/{{ $h['id'] }}/pdf" target="_blank" rel="noopener" class="font-medium text-primary hover:underline">
                            Emitido em {{ $h['emitido_em'] }}
                        </a>
                        <span class="text-xs text-slate-500">— {{ $h['cursos'] }} {{ $h['cursos'] === 1 ? 'curso' : 'cursos' }}</span>
                    </p>
                @empty
                    <p class="text-slate-500">Nenhum histórico emitido para este aluno.</p>
                @endforelse
                <form method="POST" action="/admin/alunos/{{ $aluno['id'] }}/historico">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                        <i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i> Emitir histórico escolar
                    </button>
                    <p class="mt-1.5 text-xs text-slate-500">O aluno passa a ver o documento na área dele, em Certificados, para baixar.</p>
                </form>
            </section>

            <section aria-labelledby="titulo-acoes" class="space-y-5 rounded-[10px] border border-slate-200 bg-white p-5 shadow-card">
                <h2 id="titulo-acoes" class="text-[0.875rem] font-bold text-slate-900">Ações sobre a conta</h2>

                @if ($errors->any())
                    <div role="alert" class="rounded-[10px] border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">
                        <ul class="list-inside list-disc">
                            @foreach ($errors->all() as $erro)
                                <li>{{ $erro }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{--
                    Confirmar o cadastro: só aparece enquanto a conta aguarda a coordenação.
                    O botão é só o gatilho — o servidor recusa qualquer outra origem
                    (ver AlunosDoAdminController::confirmarCadastro).
                --}}
                @if ($aluno['conta'] === 'pending_confirmation')
                    <form method="POST" action="/admin/alunos/{{ $aluno['id'] }}/confirmar" class="space-y-2 rounded-[10px] border border-emerald-100 bg-emerald-50 p-3.5">
                        @csrf
                        <p class="text-[0.8rem] font-bold text-slate-800">Cadastro aguardando confirmação</p>
                        <p class="text-xs text-slate-600">
                            {{ $aluno['nome'] }} se cadastrou pelo site e ainda não consegue entrar. Ao confirmar, a conta fica ativa
                            e o acesso à plataforma é liberado.
                        </p>
                        <button type="submit" class="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i> Confirmar cadastro
                        </button>
                    </form>
                @endif

                {{-- Redefinir a senha --}}
                <form method="POST" action="/admin/alunos/{{ $aluno['id'] }}/senha" class="space-y-2">
                    @csrf
                    <label for="nova-senha-aluno" class="{{ $rotulo }}">Redefinir senha</label>
                    <p class="text-xs text-slate-500">Quem a coordenação define aqui vale na próxima entrada de {{ $aluno['nome'] }}. {{ $regraDaSenha }}</p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input id="nova-senha-aluno" name="novaSenha" type="text" required maxlength="128" autocomplete="off"
                               value="{{ $senhaSugerida }}"
                               class="w-full rounded-md border border-slate-200 px-3 py-1.5 font-mono text-slate-900 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                        <button type="submit" class="shrink-0 rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">Definir senha</button>
                    </div>
                </form>

                {{-- Matrícula múltipla: só o administrador concede --}}
                @if ($permiteMatriculaMultipla)
                    <form method="POST" action="/admin/alunos/{{ $aluno['id'] }}/matricula-multipla" class="space-y-2 rounded-[10px] border border-violet-100 bg-violet-50/50 p-3.5">
                        @csrf
                        <p class="text-[0.8rem] font-bold text-slate-800">Matrícula múltipla simultânea</p>
                        <p class="text-xs text-slate-600">
                            Quando permitida, {{ explode(' ', $aluno['nome'])[0] }} pode cursar mais de uma disciplina ao mesmo tempo. Hoje está
                            <strong>{{ $aluno['multipla'] ? 'permitida' : 'não permitida' }}</strong>.
                        </p>
                        @if ($aluno['multipla'])
                            <button type="submit" class="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">Revogar permissão</button>
                        @else
                            <input type="hidden" name="permitir" value="1">
                            <button type="submit" class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">Permitir matrícula múltipla</button>
                        @endif
                    </form>
                @endif

                {{-- Retirar o aluno: duas etapas, sem JavaScript --}}
                <details class="rounded-md border border-red-100 bg-red-50/50 p-3">
                    <summary class="flex cursor-pointer list-none items-center gap-2 text-[0.8rem] font-semibold text-red-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300">
                        <i class="fa-solid fa-user-slash" aria-hidden="true"></i> Retirar da plataforma
                    </summary>
                    <form method="POST" action="/admin/alunos/{{ $aluno['id'] }}/inativar" class="mt-2 space-y-2">
                        @csrf
                        <p class="text-xs text-slate-600">
                            {{ $aluno['nome'] }} deixa de entrar na plataforma. Nada é apagado: o registro, as matrículas, as notas e os
                            certificados ficam preservados, com quem retirou, quando e por quê.
                        </p>
                        <label for="motivo-aluno" class="{{ $rotulo }}">Motivo (opcional)</label>
                        <input id="motivo-aluno" name="motivo" type="text" maxlength="500"
                               class="w-full rounded-md border border-slate-200 bg-white px-3 py-1.5 text-slate-900 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                        <button type="submit" class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400 focus-visible:ring-offset-2">Sim, retirar da plataforma</button>
                    </form>
                </details>
            </section>
        </div>
    </div>
</x-layouts.admin>
