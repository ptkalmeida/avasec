{{--
    A prova — todas as questões numa página, corrigida quando o aluno envia.

    Decisão de 20/10: a prova é corrigida no FIM, no servidor, e o gabarito não
    chega a esta página. O React mostrava a alternativa certa a cada questão e
    deixava refazer a questão até acertar, com a resposta final valendo nota;
    qualquer aluno chegava a 100%, e a aprovação exigida pelo certificado
    deixava de dizer alguma coisa.

    Depois do envio, a mesma página mostra o resultado (vem da sessão, uma vez
    só): a nota, e por questão se acertou — sem dizer qual era a certa.

    Uma página só, e não uma questão por vez: sem JavaScript de estado, a
    pessoa revê tudo antes de enviar, e o leitor de tela anda pelas questões
    como por qualquer formulário.
--}}
<x-layouts.aluno titulo="{{ $prova['title'] }}" ativo="curso" :cursoDoMenu="$curso">

    <div class="mx-auto max-w-3xl space-y-8">

        <nav aria-label="Trilha de navegação" class="text-apoio text-escult-ink-3">
            <a href="/aluno" class="underline-offset-[3px] hover:text-ava-acao hover:underline">Painel</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <a href="/aluno/curso/{{ $ref }}" class="underline-offset-[3px] hover:text-ava-acao hover:underline">{{ $curso['title'] }}</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <a href="/aluno/curso/{{ $ref }}/avaliacoes" class="underline-offset-[3px] hover:text-ava-acao hover:underline">Avaliações</a>
        </nav>

        <h1 class="font-titulo text-3xl leading-tight tracking-tight text-ava-tinta break-words">{{ $prova['title'] }}</h1>

        @if ($resultado !== null)
            {{-- ===== Resultado ===== --}}
            @php($acertos = count(array_filter($resultado['correcao'], static fn (array $q): bool => $q['acertou'])))

            <section role="status" aria-labelledby="titulo-resultado"
                     class="space-y-2 rounded-2xl border p-6 {{ $resultado['passed'] ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}">
                <h2 id="titulo-resultado" class="font-titulo text-cartao {{ $resultado['passed'] ? 'text-emerald-900' : 'text-amber-950' }}">
                    {{ $resultado['passed'] ? 'Aprovado nesta avaliação' : 'Ainda não foi desta vez' }}
                </h2>
                <p class="text-corpo text-ava-tinta">
                    Você acertou <strong>{{ $acertos }} de {{ count($resultado['correcao']) }}</strong> questões:
                    <strong>{{ $resultado['scorePercent'] }}%</strong>.
                    @unless ($resultado['passed'])
                        Para ser aprovado é preciso ao menos {{ $minimo }}%.
                    @endunless
                </p>
                <p class="text-apoio text-escult-ink-2">A tentativa foi registrada.</p>
            </section>

            <section aria-labelledby="titulo-correcao" class="space-y-3">
                <h2 id="titulo-correcao" class="font-titulo text-cartao text-ava-tinta">Questão por questão</h2>
                <ol role="list" class="space-y-3">
                    @foreach ($resultado['correcao'] as $q)
                        <li class="space-y-2 rounded-2xl border border-ava-borda bg-white p-4">
                            <p class="flex items-start gap-2 text-rotulo font-semibold text-ava-tinta">
                                <span aria-hidden="true" class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold {{ $q['acertou'] ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white' }}">
                                    {{ $q['acertou'] ? '✓' : '✗' }}
                                </span>
                                <span class="break-words">
                                    <span class="sr-only">{{ $q['acertou'] ? 'Acertou' : 'Errou' }} — </span>
                                    Questão {{ $q['numero'] }}: {{ $q['questionText'] }}
                                </span>
                            </p>
                            @if ($q['explanation'] !== null)
                                <p class="pl-7 text-apoio leading-relaxed text-escult-ink-2 break-words">{{ $q['explanation'] }}</p>
                            @endif
                            @if ($q['reviewMessage'] !== null)
                                <p class="pl-7 text-apoio leading-relaxed text-escult-ink-2 break-words">{{ $q['reviewMessage'] }}</p>
                            @endif
                            @if ($q['recommendedModule'] !== null)
                                <p class="pl-7 text-apoio text-escult-ink-2 break-words">
                                    Revise: <strong class="font-semibold text-ava-tinta">{{ $q['recommendedModule'] }}</strong>
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>

            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="/aluno/curso/{{ $ref }}/avaliacoes"
                   class="inline-flex items-center justify-center rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                    Voltar às avaliações
                </a>
                <a href="/aluno/curso/{{ $ref }}/avaliacoes/{{ rawurlencode($prova['id']) }}"
                   class="inline-flex items-center justify-center rounded-[10px] border border-ava-borda bg-white px-5 py-2.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                    Fazer de novo
                </a>
            </div>

        @elseif ($prova['questions'] === [])
            <div class="rounded-2xl border border-dashed border-ava-borda bg-ava-faixa/50 p-8 text-center">
                <p class="text-corpo font-semibold text-ava-tinta">Esta avaliação ainda não tem questões.</p>
            </div>

        @else
            {{-- ===== A prova ===== --}}
            @php($total = count($prova['questions']))

            <p class="text-apoio leading-relaxed text-escult-ink-2">
                {{ $total === 1 ? '1 questão' : $total.' questões' }}. Responda todas e envie no fim:
                a correção sai na hora, e cada envio conta como uma tentativa.
            </p>

            @if ($errors->has('respostas'))
                <div role="alert" id="erro-respostas" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-apoio font-semibold text-rose-800">
                    {{ $errors->first('respostas') }}
                </div>
            @endif

            {{--
                Aviso ao sair com respostas marcadas e não enviadas — o React
                fazia o mesmo com a navegação do SPA. Aqui é o aviso do próprio
                navegador, e desarma ao enviar.
            --}}
            <form method="POST" action="/aluno/curso/{{ $ref }}/avaliacoes/{{ rawurlencode($prova['id']) }}"
                  x-data="{ marcou: {{ old('respostas') ? 'true' : 'false' }}, enviando: false }"
                  x-on:change="marcou = true"
                  x-on:submit="enviando = true"
                  x-on:beforeunload.window="if (marcou && ! enviando) { $event.preventDefault(); $event.returnValue = ''; }"
                  class="space-y-6">
                @csrf

                @foreach ($prova['questions'] as $i => $q)
                    @php($marcada = old('respostas.'.$q['id']))
                    <fieldset class="min-w-0 space-y-3 rounded-2xl border border-ava-borda bg-white p-5">
                        <legend class="sr-only">Questão {{ $i + 1 }} de {{ $total }}</legend>
                        <p aria-hidden="true" class="text-sobretitulo uppercase text-escult-ink-3">Questão {{ $i + 1 }} de {{ $total }}</p>
                        <p id="enunciado-{{ $i }}" class="text-corpo font-semibold leading-relaxed text-ava-tinta break-words">{{ $q['questionText'] }}</p>

                        <div role="radiogroup" aria-labelledby="enunciado-{{ $i }}" class="space-y-2">
                            @foreach ($q['options'] as $j => $opcao)
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-ava-borda p-3.5 text-apoio text-ava-tinta transition-colors hover:bg-ava-faixa has-[:checked]:border-ava-acao has-[:checked]:bg-ava-faixa has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ava-acao">
                                    <input type="radio" name="respostas[{{ $q['id'] }}]" value="{{ $j }}"
                                           @checked($marcada !== null && (string) $marcada === (string) $j)
                                           class="mt-0.5 h-4 w-4 shrink-0 accent-[var(--color-ava-acao)]">
                                    <span class="break-words"><span class="font-bold">{{ chr(65 + $j) }})</span> {{ $opcao }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                <div class="flex flex-col gap-3 sm:flex-row">
                    <button type="submit" x-bind:disabled="enviando"
                            class="cursor-pointer rounded-[10px] bg-ava-acao px-6 py-3 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2 disabled:opacity-60">
                        <span x-text="enviando ? 'Enviando…' : 'Enviar respostas'">Enviar respostas</span>
                    </button>
                    <a href="/aluno/curso/{{ $ref }}/avaliacoes"
                       class="inline-flex items-center justify-center rounded-[10px] border border-ava-borda bg-white px-6 py-3 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                        Sair sem enviar
                    </a>
                </div>
            </form>
        @endif
    </div>
</x-layouts.aluno>
