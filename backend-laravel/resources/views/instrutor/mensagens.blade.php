{{--
    `/inst/mensagens` — as dúvidas dos alunos e a resposta do gestor.

    Uma página só para toda a caixa, por decisão do responsável em 21/10: o
    gestor precisa responder quem o procurou, e a dúvida vem de uma aula.

    Duas listas na mesma tela: à esquerda quem escreveu, com quem espera
    resposta em cima; à direita a conversa escolhida. Abaixo de `lg` elas
    empilham, e a escolhida vem primeiro — numa tela estreita, a lista de
    conversas acima do diálogo empurraria a resposta para fora da vista.

    A aula aparece como etiqueta em cada mensagem: é a única ligação que existe
    entre a dúvida e a aula (`[Aula: título]` no texto, ver AssuntoDaAula).

    O que esta tela NÃO tem: a presença do aluno e o selo "qualificado" que o
    painel React mostrava ao lado da conversa. Aquele número vinha de uma tabela
    fixa por NOME de aluno escrita no código — 85% para "Gabriel Rodrigues",
    75% para quem não estivesse nela. Decisão de 21/10: fora do MVP. Número
    inventado ao lado de uma conversa sobre o curso é pior que número nenhum.
--}}
<x-layouts.instrutor titulo="Mensagens" ativo="mensagens" :aguardandoNoMenu="$aguardando">

    <div class="mx-auto max-w-6xl space-y-6">

        <div class="space-y-2">
            <h1 class="font-titulo text-3xl tracking-tight text-ava-tinta">Mensagens</h1>
            <p class="text-apoio leading-relaxed text-escult-ink-2">
                As dúvidas que chegam dos alunos dos seus cursos.
                @if ($aguardando > 0)
                    <strong class="text-ava-tinta">{{ $aguardando === 1 ? '1 aguarda resposta' : $aguardando.' aguardam resposta' }}.</strong>
                @endif
            </p>
        </div>

        @if (session('respostaEnviada'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-apoio font-semibold text-emerald-800">
                Resposta enviada ao aluno.
            </div>
        @endif

        @if ($conversas === [])
            <div class="rounded-2xl border border-dashed border-ava-borda bg-ava-faixa/50 p-8 text-center">
                <p class="text-corpo font-semibold text-ava-tinta">Nenhum aluno escreveu ainda.</p>
                <p class="mt-1 text-apoio text-escult-ink-2">As dúvidas enviadas pela área do aluno aparecem aqui.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,320px)_minmax(0,1fr)]">

                {{-- A conversa aberta vem primeiro em tela estreita. --}}
                <section aria-labelledby="titulo-conversa" class="order-1 min-w-0 space-y-4 lg:order-2">
                    <h2 id="titulo-conversa" class="font-titulo text-cartao text-ava-tinta">
                        {{ $aberta === null ? 'Escolha uma conversa' : 'Conversa com '.$aberta['nome'] }}
                    </h2>

                    @if ($aberta === null)
                        <p class="rounded-2xl border border-dashed border-ava-borda bg-ava-faixa/50 p-6 text-apoio text-escult-ink-2">
                            Escolha um aluno na lista para ler e responder.
                        </p>
                    @else
                        <ol role="list" class="space-y-3">
                            @foreach ($mensagens as $msg)
                                @php($doAluno = $msg['deQuem'] === 'student')
                                <li class="rounded-2xl border p-4 {{ $doAluno ? 'border-ava-borda bg-white' : 'border-ava-acao/30 bg-ava-faixa' }}">
                                    <div class="mb-1.5 flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                        <span class="text-rotulo font-semibold text-ava-tinta">
                                            {{ $doAluno ? $msg['autor'] : 'Você' }}
                                        </span>
                                        <span class="text-apoio text-escult-ink-3">{{ $msg['quando'] }}</span>
                                    </div>

                                    @if ($msg['aula'] !== null)
                                        <p class="mb-1.5">
                                            <span class="inline-block rounded-full bg-ava-lilas-fundo px-2.5 py-0.5 text-sobretitulo uppercase text-ava-lilas break-words">
                                                Aula: {{ $msg['aula'] }}
                                            </span>
                                        </p>
                                    @endif

                                    <p class="whitespace-pre-line text-apoio leading-relaxed text-ava-tinta break-words">{{ $msg['texto'] }}</p>
                                </li>
                            @endforeach
                        </ol>

                        <form method="POST" action="/inst/mensagens" class="space-y-3 rounded-2xl border border-ava-borda bg-white p-5 shadow-3xs">
                            @csrf
                            <input type="hidden" name="aluno" value="{{ $aberta['alunoId'] }}">

                            <div class="space-y-1.5">
                                <label for="resposta" class="block text-rotulo font-semibold text-ava-tinta">Sua resposta</label>
                                <textarea id="resposta" name="texto" rows="5" maxlength="2000" required
                                          @error('texto') aria-invalid="true" aria-describedby="erro-resposta" @enderror
                                          class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-apoio text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">{{ old('texto') }}</textarea>
                                @error('texto')
                                    <p id="erro-resposta" class="text-apoio font-semibold text-rose-700">{{ $message }}</p>
                                @enderror
                                <p class="text-apoio text-escult-ink-3">O aluno lê a resposta na área dele, em Suporte pedagógico.</p>
                            </div>

                            <button type="submit"
                                    class="cursor-pointer rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                                Responder
                            </button>
                        </form>
                    @endif
                </section>

                <section aria-labelledby="titulo-lista" class="order-2 min-w-0 space-y-3 lg:order-1">
                    <h2 id="titulo-lista" class="font-titulo text-cartao text-ava-tinta">Alunos</h2>

                    <ul role="list" class="space-y-2">
                        @foreach ($conversas as $conversa)
                            @php($escolhida = $aberta !== null && $aberta['alunoId'] === $conversa['alunoId'])
                            <li>
                                <a href="/inst/mensagens?aluno={{ urlencode($conversa['alunoId']) }}"
                                   @if ($escolhida) aria-current="true" @endif
                                   class="block rounded-[10px] border p-3.5 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao
                                          {{ $escolhida ? 'border-ava-acao bg-ava-faixa' : 'border-ava-borda bg-white hover:bg-ava-faixa' }}">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <span class="min-w-0 truncate text-rotulo font-semibold text-ava-tinta">{{ $conversa['nome'] }}</span>
                                        @if ($conversa['aguardando'])
                                            <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-nota font-bold text-amber-900">Aguarda</span>
                                        @endif
                                    </div>
                                    @if ($conversa['ultima']['aula'] !== null)
                                        <p class="mt-1 truncate text-apoio text-escult-ink-3">Aula: {{ $conversa['ultima']['aula'] }}</p>
                                    @endif
                                    <p class="mt-1 line-clamp-2 text-apoio text-escult-ink-2 break-words">{{ $conversa['ultima']['texto'] }}</p>
                                    <p class="mt-1 text-apoio text-escult-ink-3">{{ $conversa['ultima']['quando'] }}</p>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>
        @endif
    </div>
</x-layouts.instrutor>
