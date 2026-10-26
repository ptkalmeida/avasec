{{--
    Suporte pedagógico — a conversa com o gestor do curso.

    É a função do antigo cartão da aula, que a skill tirou de lá. A mensagem vai
    pelo mesmo serviço que a API usa.

    Desde 21/10 a tela MOSTRA a conversa, e não só envia. Antes, ler a resposta
    dependia da flag do mural do curso (`forum`), que é outra funcionalidade —
    e havia resposta do gestor no banco que o aluno nunca pôde ler.
--}}
<x-layouts.aluno titulo="Suporte pedagógico" ativo="suporte" :concluidosDoMenu="$concluidos">

    <div class="mx-auto max-w-2xl space-y-6">

        <div class="space-y-2">
            <h1 class="font-titulo text-3xl tracking-tight text-ava-tinta">Suporte pedagógico</h1>
            <p class="text-apoio leading-relaxed text-escult-ink-2">
                Tem dúvida sobre o conteúdo ou algum problema técnico? Escreva aqui. A mensagem chega
                ao gestor de conteúdos do seu curso, e a resposta dele aparece nesta página.
            </p>
        </div>

        @if (! $ligado)
            <div role="status" class="rounded-xl border border-ava-borda bg-ava-faixa p-4 text-apoio leading-relaxed text-ava-tinta">
                O envio de mensagens está desativado nesta versão da plataforma. Para falar com a
                coordenação, use os contatos da sua escola.
            </div>
        @else
            @if (session('suporteEnviado'))
                {{-- `role="status"`: a confirmação chega numa página nova e o
                     leitor de tela precisa anunciá-la sem que a pessoa a procure. --}}
                <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-apoio font-semibold text-emerald-800">
                    Mensagem enviada ao gestor do curso.
                </div>
            @endif

            @if ($conversa !== [])
                <section aria-labelledby="titulo-conversa" class="space-y-3">
                    <h2 id="titulo-conversa" class="font-titulo text-cartao text-ava-tinta">Sua conversa</h2>

                    <ol role="list" class="space-y-3">
                        @foreach ($conversa as $msg)
                            @php($meu = $msg['deQuem'] === 'student')
                            <li class="rounded-2xl border p-4 {{ $meu ? 'border-ava-borda bg-white' : 'border-ava-acao/30 bg-ava-faixa' }}">
                                <div class="mb-1.5 flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                    <span class="text-rotulo font-semibold text-ava-tinta">{{ $meu ? 'Você' : $msg['autor'] }}</span>
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
                </section>
            @endif

            <form method="POST" action="/aluno/suporte" class="space-y-4 rounded-2xl border border-ava-borda bg-white p-5 shadow-3xs">
                @csrf

                @if ($aula !== null)
                    <input type="hidden" name="aula" value="{{ $aula['id'] }}">
                    <p class="text-apoio text-escult-ink-2">
                        Sobre a aula <strong class="text-ava-tinta">{{ $aula['title'] }}</strong>
                        — o gestor recebe o nome da aula junto com a sua mensagem.
                    </p>
                @endif

                <div class="space-y-1.5">
                    <label for="texto" class="block text-rotulo font-semibold text-ava-tinta">Sua mensagem</label>
                    <textarea id="texto" name="texto" rows="6" maxlength="2000" required
                              @error('texto') aria-invalid="true" aria-describedby="erro-texto" @enderror
                              class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-apoio text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">{{ old('texto') }}</textarea>
                    @error('texto')
                        <p id="erro-texto" class="text-apoio font-semibold text-rose-700">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                    Enviar mensagem
                </button>
            </form>
        @endif
    </div>
</x-layouts.aluno>
