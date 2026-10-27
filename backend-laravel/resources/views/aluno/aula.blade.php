{{--
    Página da aula — tela 5 do redesenho (layout B aprovado).

    Título da aula ACIMA do vídeo, vídeo em destaque, conteúdo escrito abaixo em
    largura de leitura. A lista de aulas vive no menu lateral e **não** se
    repete aqui: nesta tela a área principal é só o conteúdo, que é o que a
    pessoa veio ler.

    No lugar de "Aula X de Y" aparece o nome do CURSO. A posição na grade ela vê
    na lista; o que precisa saber aqui é onde está.

    Saíram, por decisão da skill: frequência, "frequência cumprida", o ícone
    decorativo ao lado do título, o selo "Online" e a duração em minutos. O
    cartão de suporte do rodapé também saiu — "Suporte pedagógico" é item do
    menu lateral, e leva ao canal de mensagens, que é onde a dúvida vira
    conversa registrada.

    "Material Didático" e "Anotações Privadas" continuam como abas, lado a lado
    do conteúdo, como a skill pede.
--}}
<x-layouts.aluno
    :titulo="$aula['titulo']"
    ativo="curso"
    :cursoDoMenu="$curso"
    :concluidosDoMenu="$concluidos"
    :aulasDoMenu="$aulas"
    :aulaAtual="$aula['id']">

    @php($ref = $curso['slug'] ?? $curso['id'])

    <article class="mx-auto max-w-3xl space-y-6">

        <header class="space-y-2">
            {{-- O nome do curso é o contexto; o título da aula é o assunto. --}}
            <p class="text-sobretitulo uppercase text-escult-ink-3">
                <a href="/aluno/curso/{{ $ref }}" class="underline-offset-[3px] hover:text-ava-acao hover:underline">
                    {{ $curso['titulo'] }}
                </a>
            </p>
            <h1 class="font-titulo text-2xl leading-tight tracking-tight text-ava-tinta md:text-3xl">
                {{ $aula['titulo'] }}
            </h1>
        </header>

        @if (! empty($aula['url_video']))
            <x-aluno.video :url="$aula['url_video']" :titulo="$aula['titulo']" />
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3 border-y border-ava-borda py-4">
            <livewire:aluno.conclusao-da-aula
                :cursoId="$curso['id']"
                :aulaId="$aula['id']"
                :concluida="$aula['concluida']"
                :key="'conclusao-'.$aula['id']" />

            <p class="text-apoio text-escult-ink-3">
                {{ $curso['aulasConcluidas'] }} de {{ $curso['aulasNoCurso'] }} aulas concluídas
            </p>
        </div>

        {{--
            Abas em Alpine, e não em Livewire: trocar de aba é mostrar o que já
            está na página. Uma ida ao servidor para isso seria lentidão sem
            troca nenhuma — e as anotações, que vivem no navegador, se perderiam
            a cada troca.
        --}}
        <div x-data="{ aba: 'material' }">
            <div role="tablist" aria-label="Conteúdo da aula" class="flex border-b border-ava-borda">
                <button type="button" role="tab" id="aba-material"
                        @click="aba = 'material'"
                        :aria-selected="aba === 'material' ? 'true' : 'false'"
                        :tabindex="aba === 'material' ? 0 : -1"
                        aria-controls="painel-material"
                        class="flex-1 cursor-pointer border-b-2 px-4 py-3 text-rotulo font-bold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao"
                        :class="aba === 'material' ? 'border-ava-lilas text-ava-tinta' : 'border-transparent text-escult-ink-3 hover:text-ava-tinta'">
                    Material Didático
                </button>
                <button type="button" role="tab" id="aba-anotacoes"
                        @click="aba = 'anotacoes'"
                        :aria-selected="aba === 'anotacoes' ? 'true' : 'false'"
                        :tabindex="aba === 'anotacoes' ? 0 : -1"
                        aria-controls="painel-anotacoes"
                        class="flex-1 cursor-pointer border-b-2 px-4 py-3 text-rotulo font-bold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao"
                        :class="aba === 'anotacoes' ? 'border-ava-lilas text-ava-tinta' : 'border-transparent text-escult-ink-3 hover:text-ava-tinta'">
                    Anotações Privadas
                </button>
            </div>

            <div id="painel-material" role="tabpanel" aria-labelledby="aba-material" x-show="aba === 'material'" class="pt-5">
                @if ($blocos === [])
                    <p class="rounded-xl border border-dashed border-ava-borda bg-ava-faixa/50 p-6 text-center text-apoio text-escult-ink-2">
                        Esta aula ainda não tem conteúdo escrito publicado.
                    </p>
                @else
                    <x-aluno.conteudo-da-aula :blocos="$blocos" />
                @endif

                @if (! empty($aula['documentos']))
                    <section class="mt-8 space-y-3 rounded-2xl border border-ava-borda bg-ava-faixa/40 p-5">
                        <h2 class="font-titulo text-cartao text-ava-tinta">Materiais de apoio</h2>
                        <ul role="list" class="space-y-2">
                            @foreach ($aula['documentos'] as $documento)
                                @php($nosso = str_starts_with((string) ($documento['url'] ?? ''), '/uploads/'))
                                <li>
                                    {{-- O ícone é uma promessa: seta para baixo diz
                                         "isto baixa". Só o que sai de /uploads/ é
                                         servido por nós e baixa de verdade; o resto
                                         abre outra aba, e a tela diz isso. --}}
                                    <a href="{{ $documento['url'] }}"
                                       @unless ($nosso) target="_blank" rel="noopener noreferrer" @endunless
                                       class="flex items-center gap-3 rounded-[10px] border border-ava-borda bg-white px-4 py-3 transition-colors hover:border-ava-acao/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                                        <span class="shrink-0 text-ava-acao" aria-hidden="true">{{ $nosso ? '↓' : '↗' }}</span>
                                        <span class="min-w-0 flex-1 text-rotulo font-semibold text-ava-tinta">{{ $documento['titulo'] ?? 'Material' }}</span>
                                        <span class="shrink-0 text-nota uppercase text-escult-ink-3">
                                            {{ $nosso ? 'baixar' : 'abre em nova aba' }}
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>

            {{--
                As anotações vivem no NAVEGADOR, como sempre viveram: o React as
                guardava em `localStorage`, e a chave é a mesma, para quem já
                escreveu não perder o que tem.

                Isto está dito na tela porque a promessa importa: "privadas" aqui
                significa que nem o AVASEC as vê — e também que elas não
                acompanham a pessoa para outro computador.
            --}}
            <div id="painel-anotacoes" role="tabpanel" aria-labelledby="aba-anotacoes" x-show="aba === 'anotacoes'" x-cloak class="space-y-3 pt-5"
                 x-data="{
                    chave: 'ava_student_lesson_notes',
                    aula: '{{ $aula['id'] }}',
                    texto: '',
                    salvo: false,
                    init() {
                        try {
                            const tudo = JSON.parse(localStorage.getItem(this.chave) || '{}');
                            this.texto = tudo[this.aula] || '';
                        } catch (e) { this.texto = ''; }
                    },
                    guardar() {
                        try {
                            const tudo = JSON.parse(localStorage.getItem(this.chave) || '{}');
                            if (this.texto.trim() === '') { delete tudo[this.aula]; } else { tudo[this.aula] = this.texto; }
                            localStorage.setItem(this.chave, JSON.stringify(tudo));
                            this.salvo = true;
                            setTimeout(() => { this.salvo = false; }, 2500);
                        } catch (e) { this.salvo = false; }
                    },
                 }">
                <label for="anotacoes" class="block text-rotulo font-semibold text-ava-tinta">Suas anotações desta aula</label>
                <textarea id="anotacoes" x-model="texto" rows="8"
                          placeholder="Escreva aqui o que quiser lembrar desta aula…"
                          class="w-full rounded-xl border border-ava-borda bg-white p-4 text-apoio leading-relaxed text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30"></textarea>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" @click="guardar()"
                            class="cursor-pointer rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                        Salvar anotações
                    </button>
                    <span x-show="salvo" x-cloak role="status" class="text-apoio font-semibold text-ava-lilas">Anotações salvas.</span>
                </div>

                <p class="text-apoio leading-relaxed text-escult-ink-3">
                    Estas anotações ficam guardadas apenas neste navegador: ninguém mais as vê, nem a
                    coordenação — e elas não acompanham você em outro computador.
                </p>
            </div>
        </div>

        <nav aria-label="Navegação entre aulas" class="flex flex-wrap items-center justify-between gap-3 border-t border-ava-borda pt-5">
            @if ($anterior !== null)
                <a href="/aluno/curso/{{ $ref }}/aula/{{ $anterior['id'] }}"
                   class="inline-flex items-center gap-2 rounded-[10px] border border-ava-borda px-5 py-2.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                    <span aria-hidden="true">←</span>
                    <span>Aula anterior</span>
                </a>
            @else
                <span></span>
            @endif

            @if ($proxima !== null)
                <a href="/aluno/curso/{{ $ref }}/aula/{{ $proxima['id'] }}"
                   class="inline-flex items-center gap-2 rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                    <span>Próxima aula</span>
                    <span aria-hidden="true">→</span>
                </a>
            @else
                <a href="/aluno/curso/{{ $ref }}"
                   class="inline-flex items-center gap-2 rounded-[10px] border border-ava-acao px-5 py-2.5 text-rotulo font-semibold text-ava-acao transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                    <span>Voltar ao curso</span>
                    <span aria-hidden="true">→</span>
                </a>
            @endif
        </nav>
    </article>
</x-layouts.aluno>
