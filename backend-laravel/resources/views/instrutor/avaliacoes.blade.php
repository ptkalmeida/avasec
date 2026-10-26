{{--
    `/inst/curso/{ref}/avaliacoes` — as provas do curso.

    Substitui a lista do `AvaliacoesManagePanel.tsx`. Duas diferenças de
    propósito:

    - conta ALUNOS, e não tentativas. O React somava tentativas: com refazer
      ilimitado, um aluno que tentou três vezes contava três vezes em
      "respostas" e, se passou nas três, três vezes em "aprovados";
    - a sub-aba "Exercícios" não aparece: está atrás de
      `atividadesPraticasAvancadas`, desligada.

    Retirar pede confirmação na página e diz o que acontece com as respostas.
--}}
<x-layouts.instrutor :titulo="'Avaliações — '.$curso['title']" ativo="avaliacoes" :cursoDoMenu="$curso">

    <div class="mx-auto max-w-4xl space-y-6">

        <nav aria-label="Trilha de navegação" class="text-apoio text-escult-ink-3">
            <a href="/inst" class="underline-offset-[3px] hover:text-ava-acao hover:underline">Meus cursos</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <a href="/inst/curso/{{ $ref }}" class="underline-offset-[3px] hover:text-ava-acao hover:underline">{{ $curso['title'] }}</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <span class="text-ava-tinta">Avaliações</span>
        </nav>

        @if (session('avisoDoCurso'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-apoio font-semibold text-emerald-800">
                {{ session('avisoDoCurso') }}
            </div>
        @endif

        @if ($errors->has('avaliacoes'))
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-apoio font-semibold text-rose-800">
                {{ $errors->first('avaliacoes') }}
            </div>
        @endif

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="space-y-1">
                <h1 class="font-titulo text-3xl leading-tight tracking-tight text-ava-tinta">Avaliações</h1>
                <p class="text-apoio text-escult-ink-2">
                    O aluno é aprovado com pelo menos {{ $minimo }}% de acertos. Para o certificado, é preciso ser aprovado em todas.
                </p>
            </div>
            <a href="/inst/curso/{{ $ref }}/avaliacoes/nova"
               class="inline-flex shrink-0 items-center justify-center rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                Nova avaliação
            </a>
        </div>

        @if ($avaliacoes === [])
            <div class="rounded-2xl border border-dashed border-ava-borda bg-ava-faixa/50 p-10 text-center">
                <p class="text-corpo font-semibold text-ava-tinta">Este curso não tem avaliações.</p>
                <p class="mt-1 text-apoio text-escult-ink-2">Sem avaliação, o certificado depende só da frequência.</p>
            </div>
        @else
            <ul role="list" class="space-y-3">
                @foreach ($avaliacoes as $avaliacao)
                    <li class="rounded-2xl border border-ava-borda bg-white p-4 shadow-3xs md:p-5" x-data="{ removendo: false }">
                        <div class="space-y-1.5">
                            <h2 class="font-titulo text-lg leading-tight text-ava-tinta break-words">{{ $avaliacao['title'] }}</h2>
                            <p class="text-apoio text-escult-ink-2">
                                {{ $avaliacao['questoes'] === 1 ? '1 questão' : $avaliacao['questoes'].' questões' }}
                                · {{ $avaliacao['alunos'] === 1 ? '1 aluno respondeu' : $avaliacao['alunos'].' alunos responderam' }}
                                · {{ $avaliacao['aprovados'] === 1 ? '1 aprovado' : $avaliacao['aprovados'].' aprovados' }}
                            </p>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2" x-show="! removendo">
                            <a href="/inst/curso/{{ $ref }}/avaliacoes/{{ rawurlencode($avaliacao['id']) }}/editar"
                               class="inline-flex items-center rounded-[10px] border border-ava-borda bg-white px-4 py-2 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                                Editar
                                <span class="sr-only">{{ $avaliacao['title'] }}</span>
                            </a>
                            <button type="button" @click="removendo = true"
                                    class="cursor-pointer rounded-[10px] border border-ava-borda bg-white px-4 py-2 text-rotulo font-semibold text-rose-700 transition-colors hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
                                Retirar
                                <span class="sr-only">{{ $avaliacao['title'] }}</span>
                            </button>
                        </div>

                        <form method="POST" action="/inst/curso/{{ $ref }}/avaliacoes/{{ rawurlencode($avaliacao['id']) }}/remover"
                              x-show="removendo" x-cloak
                              class="mt-3 space-y-2 rounded-xl border border-rose-200 bg-rose-50 p-3">
                            @csrf
                            <p class="text-apoio font-semibold text-rose-900">Retirar “{{ $avaliacao['title'] }}”?</p>
                            <p class="text-apoio text-rose-900">
                                Ela sai do ar para a turma e deixa de ser exigida para o certificado.
                                @if ($avaliacao['tentativas'] > 0)
                                    As {{ $avaliacao['tentativas'] }} {{ $avaliacao['tentativas'] === 1 ? 'resposta já entregue continua registrada' : 'respostas já entregues continuam registradas' }}.
                                @endif
                                Nada é apagado.
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit"
                                        class="cursor-pointer rounded-[10px] bg-rose-700 px-4 py-2 text-rotulo font-semibold text-white transition-colors hover:bg-rose-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2">
                                    Retirar a avaliação
                                </button>
                                <button type="button" @click="removendo = false"
                                        class="cursor-pointer rounded-[10px] border border-ava-borda bg-white px-4 py-2 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                                    Manter
                                </button>
                            </div>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.instrutor>
