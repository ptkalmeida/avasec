{{--
    `/inst/curso/{ref}/alunos` — quem está cursando, e os pedidos de matrícula.

    A aba do painel React era, na prática, vazia: com a aprovação automática
    ligada (a configuração de hoje) ela só mostrava um aviso, embora o texto
    prometesse "acompanhamento de turmas". Aqui ela mostra a turma de verdade.

    Só o que o banco sabe. O painel antigo mostrava, na aba de mensagens, uma
    "presença" de uma tabela fixa por nome de aluno e um e-mail inventado a
    partir do nome; nada disso veio. Quem já concluiu entra só na contagem: o
    instrutor não fica com ex-aluno no alcance para sempre.

    A fila de pedidos só existe com a aprovação automática desligada — a tela
    decide pela REGRA, e não por a lista estar vazia.
--}}
<x-layouts.instrutor :titulo="'Alunos — '.$curso['title']" ativo="alunos" :cursoDoMenu="$curso">

    <div class="mx-auto max-w-5xl space-y-6">

        <nav aria-label="Trilha de navegação" class="text-apoio text-escult-ink-3">
            <a href="/inst" class="underline-offset-[3px] hover:text-ava-acao hover:underline">Meus cursos</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <a href="/inst/curso/{{ $ref }}" class="underline-offset-[3px] hover:text-ava-acao hover:underline">{{ $curso['title'] }}</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <span class="text-ava-tinta">Alunos</span>
        </nav>

        @if (session('avisoDoCurso'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-apoio font-semibold text-emerald-800">
                {{ session('avisoDoCurso') }}
            </div>
        @endif

        @if ($errors->has('pedidos'))
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-apoio font-semibold text-rose-800">
                {{ $errors->first('pedidos') }}
            </div>
        @endif

        <div class="space-y-1">
            <h1 class="font-titulo text-3xl leading-tight tracking-tight text-ava-tinta">Alunos</h1>
            <p class="text-apoio text-escult-ink-2">
                {{ count($cursando) === 1 ? '1 aluno cursando' : count($cursando).' alunos cursando' }}
                @if ($concluiram > 0)
                    · {{ $concluiram === 1 ? '1 já concluiu' : $concluiram.' já concluíram' }}
                @endif
            </p>
        </div>

        {{-- O aviso muda com a regra, em vez de afirmar o que deixou de ser verdade. --}}
        @if ($automatica)
            <p class="rounded-xl border border-ava-borda bg-ava-faixa p-3 text-apoio text-ava-tinta">
                As matrículas são aprovadas automaticamente: o acesso é liberado no ato do pedido.
            </p>
        @else
            <section aria-labelledby="titulo-pedidos" class="space-y-3">
                <h2 id="titulo-pedidos" class="font-titulo text-cartao text-ava-tinta">
                    Pedidos de matrícula
                    @if ($pedidos !== [])
                        <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-nota font-bold text-amber-900">{{ count($pedidos) }}</span>
                    @endif
                </h2>

                @if ($pedidos === [])
                    <p class="rounded-2xl border border-dashed border-ava-borda bg-ava-faixa/50 p-6 text-center text-apoio text-escult-ink-2">
                        Nenhum pedido aguardando decisão.
                    </p>
                @else
                    <ul role="list" class="space-y-2">
                        @foreach ($pedidos as $pedido)
                            <li class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-rotulo font-semibold text-ava-tinta break-words">{{ $pedido['aluno'] }}</p>
                                    <p class="text-apoio text-amber-950">pediu matrícula neste curso em {{ $pedido['enviadoEm'] }}</p>
                                </div>
                                <form method="POST" action="/inst/curso/{{ $ref }}/pedidos/{{ rawurlencode($pedido['id']) }}" class="flex shrink-0 gap-2">
                                    @csrf
                                    <button type="submit" name="decisao" value="rejected"
                                            class="cursor-pointer rounded-[10px] border border-ava-borda bg-white px-4 py-2 text-rotulo font-semibold text-rose-700 transition-colors hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
                                        Reprovar<span class="sr-only"> o pedido de {{ $pedido['aluno'] }}</span>
                                    </button>
                                    <button type="submit" name="decisao" value="approved"
                                            class="cursor-pointer rounded-[10px] bg-ava-acao px-4 py-2 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                                        Aprovar<span class="sr-only"> o pedido de {{ $pedido['aluno'] }}</span>
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        <section aria-labelledby="titulo-turma" class="space-y-3">
            <h2 id="titulo-turma" class="font-titulo text-cartao text-ava-tinta">Turma</h2>

            @if ($cursando === [])
                <div class="rounded-2xl border border-dashed border-ava-borda bg-ava-faixa/50 p-8 text-center">
                    <p class="text-corpo font-semibold text-ava-tinta">Ninguém está cursando este curso agora.</p>
                </div>
            @else
                <ul role="list" class="space-y-3">
                    @foreach ($cursando as $aluno)
                        <li class="space-y-2 rounded-2xl border border-ava-borda bg-white p-4 shadow-3xs md:p-5">
                            <div class="min-w-0">
                                <h3 class="font-titulo text-lg leading-tight text-ava-tinta break-words">{{ $aluno['nome'] }}</h3>
                                @if ($aluno['email'] !== null)
                                    <p class="text-apoio text-escult-ink-2 break-all">{{ $aluno['email'] }}</p>
                                @endif
                            </div>

                            <dl class="grid grid-cols-1 gap-x-6 gap-y-1 text-apoio sm:grid-cols-3">
                                <div>
                                    <dt class="text-escult-ink-2">Aulas</dt>
                                    <dd class="font-semibold text-ava-tinta">{{ $aluno['aulasFeitas'] }} de {{ $aluno['aulas'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-escult-ink-2">Avaliações aprovadas</dt>
                                    <dd class="font-semibold text-ava-tinta">
                                        @if ($aluno['provas'] === 0)
                                            <span class="font-normal text-escult-ink-3">o curso não tem</span>
                                        @else
                                            {{ $aluno['provasAprovadas'] }} de {{ $aluno['provas'] }}
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-escult-ink-2">Certificado</dt>
                                    <dd class="font-semibold text-ava-tinta">{{ $aluno['certificado'] ? 'Emitido' : 'Ainda não' }}</dd>
                                </div>
                            </dl>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-layouts.instrutor>
