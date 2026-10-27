{{--
    `/inst/curso/{ref}/grade-curricular` — as aulas do curso, em ordem.

    Mudar a ordem e retirar uma aula é daqui. O CONTEÚDO da aula (texto, vídeo,
    anexos) é a página da aula, que tem endereço próprio (`/aulas/{id}` e
    `/aulas/nova`) e também é do servidor desde a fase M6 do plano 16.

    O que NÃO veio do painel React:

    - o selo "Obrigatória/Opcional". A coluna `isOptional` NÃO EXISTE no banco:
      o botão de alternar nunca gravou nada, e todo curso mostrava "Obrigatória"
      — informação que o sistema não tem. Quando existir a coluna, o selo volta;
    - o arrastar-para-reordenar que o texto da tela prometia: nunca houve, só
      as setas, e prometer o que não existe é pior que dizer o que há;
    - o "confirm()" do navegador para remover. A confirmação é na página, com o
      nome da aula, como a prova do aluno já faz.

    Setas e remoção são formulários (POST), e não JavaScript: funcionam sem ele,
    e cada clique grava uma coisa só — o React reenviava o curso inteiro.
--}}
<x-layouts.instrutor :titulo="'Grade curricular — '.$curso['titulo']" ativo="grade" :cursoDoMenu="$curso">

    <div class="mx-auto max-w-4xl space-y-6">

        <nav aria-label="Trilha de navegação" class="text-apoio text-escult-ink-3">
            <a href="/inst" class="underline-offset-[3px] hover:text-ava-acao hover:underline">Meus cursos</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <a href="/inst/curso/{{ $ref }}" class="underline-offset-[3px] hover:text-ava-acao hover:underline">{{ $curso['titulo'] }}</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <span class="text-ava-tinta">Grade curricular</span>
        </nav>

        @if (session('avisoDoCurso'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-apoio font-semibold text-emerald-800">
                {{ session('avisoDoCurso') }}
            </div>
        @endif

        @if ($errors->has('grade'))
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-apoio font-semibold text-rose-800">
                {{ $errors->first('grade') }}
            </div>
        @endif

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="space-y-1">
                <h1 class="font-titulo text-3xl leading-tight tracking-tight text-ava-tinta">Grade curricular</h1>
                <p class="text-apoio text-escult-ink-2">
                    {{ count($aulas) === 1 ? '1 aula' : count($aulas).' aulas' }} · tempo total {{ $tempoTotal }}
                </p>
            </div>

            <a href="/inst/curso/{{ $ref }}/aulas/nova"
               class="inline-flex shrink-0 items-center justify-center rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                Criar nova aula
            </a>
        </div>

        @if ($vigenciaVencida)
            <p class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-apoio text-amber-950">
                A vigência deste curso terminou em <strong>{{ $vigencia_ate }}</strong>: ele não é oferecido a novos alunos.
                A grade continua editável.
            </p>
        @endif

        @if ($aulas === [])
            <div class="rounded-2xl border border-dashed border-ava-borda bg-ava-faixa/50 p-10 text-center">
                <p class="text-corpo font-semibold text-ava-tinta">A grade deste curso está vazia.</p>
                <p class="mt-1 text-apoio text-escult-ink-2">Crie a primeira aula para os alunos poderem começar.</p>
            </div>
        @else
            <ol role="list" class="space-y-3">
                @foreach ($aulas as $i => $aula)
                    <li id="aula-{{ $aula['id'] }}" class="scroll-mt-6 rounded-2xl border border-ava-borda bg-white p-4 shadow-3xs md:p-5"
                        x-data="{ removendo: false }">
                        <div class="flex items-start gap-3 md:gap-4">

                            {{-- Número e setas. A posição é dita por texto, para o leitor de tela. --}}
                            <div class="flex shrink-0 flex-col items-center gap-1">
                                <form method="POST" action="/inst/curso/{{ $ref }}/aulas/{{ rawurlencode($aula['id']) }}/mover">
                                    @csrf
                                    <input type="hidden" name="direcao" value="up">
                                    <button type="submit" @disabled($i === 0)
                                            class="cursor-pointer rounded-lg p-1.5 text-escult-ink-2 transition-colors hover:bg-ava-faixa hover:text-ava-acao focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M14.77 12.79a.75.75 0 0 1-1.06-.02L10 8.832 6.29 12.77a.75.75 0 1 1-1.08-1.04l4.25-4.5a.75.75 0 0 1 1.08 0l4.25 4.5a.75.75 0 0 1-.02 1.06Z" clip-rule="evenodd"/></svg>
                                        <span class="sr-only">Subir a aula {{ $i + 1 }}, {{ $aula['titulo'] }}</span>
                                    </button>
                                </form>

                                <span class="flex h-7 w-7 items-center justify-center rounded-full border border-ava-borda bg-ava-faixa text-apoio font-bold text-ava-tinta" aria-hidden="true">{{ $i + 1 }}</span>

                                <form method="POST" action="/inst/curso/{{ $ref }}/aulas/{{ rawurlencode($aula['id']) }}/mover">
                                    @csrf
                                    <input type="hidden" name="direcao" value="down">
                                    <button type="submit" @disabled($i === count($aulas) - 1)
                                            class="cursor-pointer rounded-lg p-1.5 text-escult-ink-2 transition-colors hover:bg-ava-faixa hover:text-ava-acao focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>
                                        <span class="sr-only">Descer a aula {{ $i + 1 }}, {{ $aula['titulo'] }}</span>
                                    </button>
                                </form>
                            </div>

                            <div class="min-w-0 flex-1 space-y-1.5">
                                <h2 class="font-titulo text-lg leading-tight text-ava-tinta break-words">{{ $aula['titulo'] }}</h2>

                                <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-apoio text-escult-ink-2">
                                    <span>{{ $aula['duracao'] ?? 'Sem duração' }}</span>
                                    <span>{{ $aula['documentos'] === 1 ? '1 documento' : $aula['documentos'].' documentos' }}</span>
                                    @if ($aula['temVideo'])
                                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-sobretitulo uppercase text-amber-900">Vídeo</span>
                                    @endif
                                </p>

                                <div class="flex flex-wrap gap-2 pt-1" x-show="! removendo">
                                    <a href="/inst/curso/{{ $ref }}/aulas/{{ rawurlencode($aula['id']) }}"
                                       class="inline-flex items-center rounded-[10px] border border-ava-borda bg-white px-4 py-2 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                                        Editar conteúdo
                                        <span class="sr-only">da aula {{ $i + 1 }}, {{ $aula['titulo'] }}</span>
                                    </a>
                                    <button type="button" @click="removendo = true"
                                            class="cursor-pointer rounded-[10px] border border-ava-borda bg-white px-4 py-2 text-rotulo font-semibold text-rose-700 transition-colors hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
                                        Retirar da grade
                                        <span class="sr-only">a aula {{ $i + 1 }}, {{ $aula['titulo'] }}</span>
                                    </button>
                                </div>

                                {{-- Confirmação na PÁGINA: diz o que acontece e o que não. --}}
                                <form method="POST" action="/inst/curso/{{ $ref }}/aulas/{{ rawurlencode($aula['id']) }}/remover"
                                      x-show="removendo" x-cloak
                                      class="space-y-2 rounded-xl border border-rose-200 bg-rose-50 p-3">
                                    @csrf
                                    <p class="text-apoio font-semibold text-rose-900">
                                        Retirar “{{ $aula['titulo'] }}” da grade?
                                    </p>
                                    <p class="text-apoio text-rose-900">
                                        Os alunos deixam de vê-la e ela sai da conta do progresso. Nada é apagado: a aula
                                        continua guardada no sistema, com quem a retirou e quando.
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="submit"
                                                class="cursor-pointer rounded-[10px] bg-rose-700 px-4 py-2 text-rotulo font-semibold text-white transition-colors hover:bg-rose-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2">
                                            Retirar a aula
                                        </button>
                                        <button type="button" @click="removendo = false"
                                                class="cursor-pointer rounded-[10px] border border-ava-borda bg-white px-4 py-2 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                                            Manter
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</x-layouts.instrutor>
