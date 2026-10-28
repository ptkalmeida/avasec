{{--
    Certificados do aluno — o documento que a pessoa veio buscar.

    Substitui a sub-aba de certificados do `ProfileView.tsx`. Duas listas numa
    página só, em vez de três abas: "Emitidos" e "A caminho" são curtas, e
    esconder uma atrás de um controle só acrescenta um clique. A terceira aba,
    Validação, era o autenticador público reimplementado no painel; a função
    fica, apontando para onde ela já existe.

    Os números vêm do `CertificateService`, o mesmo que emite: a tela não
    recalcula a frequência.
--}}
<x-layouts.aluno titulo="Certificados" ativo="certificados" :concluidosDoMenu="$concluidos">

    <div class="mx-auto max-w-5xl space-y-8">

        <div class="space-y-2">
            <h1 class="font-titulo text-3xl tracking-tight text-ava-tinta">Certificados</h1>
            <p class="text-apoio leading-relaxed text-escult-ink-2">
                {{ count($emitidos) === 1 ? '1 certificado emitido' : count($emitidos).' certificados emitidos' }}{{ $emAndamento === [] ? '' : ' · '.(count($emAndamento) === 1 ? '1 a caminho' : count($emAndamento).' a caminho') }}.
            </p>
        </div>

        {{-- Emitidos --}}
        <section aria-labelledby="titulo-emitidos" class="space-y-4">
            <h2 id="titulo-emitidos" class="font-titulo text-cartao text-ava-tinta">Emitidos</h2>

            @if ($emitidos === [])
                <div class="rounded-2xl border border-dashed border-ava-borda bg-ava-faixa/50 p-8 text-center">
                    <p class="text-corpo font-semibold text-ava-tinta">Você ainda não tem certificado emitido.</p>
                    <p class="mt-1 text-apoio text-escult-ink-2">Ele sai quando o curso é concluído, com os critérios cumpridos.</p>
                </div>
            @else
                <ul role="list" class="space-y-4">
                    @foreach ($emitidos as $cert)
                        @php($carga = $cargaPorCurso[$cert['curso_id']] ?? null)
                        <li class="flex flex-col justify-between gap-5 rounded-2xl border border-ava-borda bg-white p-5 shadow-3xs md:flex-row md:items-center">
                            <div class="min-w-0 space-y-2">
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-sobretitulo uppercase text-emerald-800">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3" aria-hidden="true">
                                        <path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/>
                                    </svg>
                                    Emitido
                                </span>
                                <h3 class="font-titulo text-lg leading-tight text-ava-tinta break-words">{{ $cert['titulo_curso'] }}</h3>

                                <dl class="flex flex-wrap gap-x-5 gap-y-1 text-apoio text-escult-ink-2">
                                    <div><dt class="inline">Emitido em</dt> <dd class="inline font-semibold text-ava-tinta">{{ $cert['data_emissao'] }}</dd></div>
                                    @if ($carga !== null)
                                        <div><dt class="inline">Carga horária</dt> <dd class="inline font-semibold text-ava-tinta">{{ $carga }}h</dd></div>
                                    @endif
                                    <div><dt class="inline">Frequência</dt> <dd class="inline font-semibold text-ava-tinta">{{ $cert['percentual_frequencia'] }}%</dd></div>
                                </dl>

                                {{--
                                    O código vai em `<code>` selecionável, e não com
                                    um botão "copiar" que só falava "copiado" em voz
                                    alta: sem narração, nada confirmava a cópia. O
                                    clique triplo seleciona o código inteiro.
                                --}}
                                <p class="text-apoio text-escult-ink-2">
                                    Código de verificação:
                                    <code class="select-all rounded border border-ava-borda bg-ava-faixa px-1.5 py-0.5 font-mono text-ava-tinta break-all">{{ $cert['hash_verificacao'] }}</code>
                                </p>
                            </div>

                            <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                                <a href="/aluno/certificados/{{ urlencode((string) $cert['id']) }}/pdf"
                                   class="inline-flex items-center justify-center gap-2 rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>
                                    </svg>
                                    Baixar PDF
                                </a>
                                <a href="/aluno/certificados/{{ urlencode((string) $cert['id']) }}/pdf?modo=ver" target="_blank" rel="noopener"
                                   class="inline-flex items-center justify-center gap-2 rounded-[10px] border border-ava-borda bg-white px-5 py-2.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                                    Ver
                                    <span class="sr-only">o certificado em nova aba</span>
                                </a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Histórico escolar: emitido pela secretaria (admin); aparece aqui sozinho. --}}
        @if ($historicos !== [])
            <section aria-labelledby="titulo-historico" class="space-y-4">
                <h2 id="titulo-historico" class="font-titulo text-cartao text-ava-tinta">Histórico escolar</h2>
                <ul role="list" class="space-y-4">
                    @foreach ($historicos as $h)
                        <li class="flex flex-col justify-between gap-4 rounded-2xl border border-ava-borda bg-white p-5 shadow-3xs md:flex-row md:items-center">
                            <div class="space-y-1">
                                <h3 class="font-titulo text-lg leading-tight text-ava-tinta">Histórico escolar</h3>
                                <p class="text-apoio text-escult-ink-2">
                                    Emitido em <span class="font-semibold text-ava-tinta">{{ $h['emitido_em'] }}</span>
                                    · {{ $h['cursos'] === 1 ? '1 curso' : $h['cursos'].' cursos' }}
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                                <a href="/aluno/historico/{{ $h['id'] }}/pdf"
                                   class="inline-flex items-center justify-center gap-2 rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                                    Baixar PDF
                                </a>
                                <a href="/aluno/historico/{{ $h['id'] }}/pdf?modo=ver" target="_blank" rel="noopener"
                                   class="inline-flex items-center justify-center gap-2 rounded-[10px] border border-ava-borda bg-white px-5 py-2.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                                    Ver
                                    <span class="sr-only">o histórico em nova aba</span>
                                </a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- A caminho --}}
        @if ($emAndamento !== [])
            <section aria-labelledby="titulo-a-caminho" class="space-y-4">
                <h2 id="titulo-a-caminho" class="font-titulo text-cartao text-ava-tinta">A caminho</h2>

                <ul role="list" class="space-y-4">
                    @foreach ($emAndamento as $item)
                        @php($curso = $item['curso'])
                        @php($s = $item['situacao'])
                        @php($ref = ($curso['slug'] ?? '') !== '' ? $curso['slug'] : $curso['id'])
                        <li class="space-y-4 rounded-2xl border border-ava-borda bg-white p-5 shadow-3xs">
                            <h3 class="font-titulo text-lg leading-tight text-ava-tinta break-words">{{ $curso['titulo'] }}</h3>

                            @if (! $s['emite'])
                                <p class="text-apoio text-escult-ink-2">Este curso não emite certificado.</p>
                            @else
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between text-apoio">
                                        <span class="text-escult-ink-2">Frequência</span>
                                        {{-- O número vem escrito: barra que só existe como cor
                                             não é legível por quem não a distingue (D §2.13). --}}
                                        <span class="font-semibold text-ava-tinta">{{ $s['frequencia'] }}% de {{ $s['minima'] }}% exigidos</span>
                                    </div>
                                    <div class="h-2 w-full rounded-full bg-ava-lilas-fundo" aria-hidden="true">
                                        <div class="h-2 rounded-full bg-ava-lilas" style="width: {{ min(100, $s['frequencia']) }}%"></div>
                                    </div>
                                </div>

                                {{--
                                    "O que falta" diz o que FALTA de verdade. O React só
                                    falava de frequência — e com a frequência cumprida e uma
                                    prova em aberto, mandava continuar assistindo aulas quem
                                    já tinha assistido todas (desde a ADR 14, avaliação
                                    também segura o certificado).
                                --}}
                                @php($faltaFrequencia = $s['frequencia'] < $s['minima'])
                                <div class="rounded-xl border border-ava-lilas-borda bg-ava-lilas-fundo p-3 text-apoio leading-relaxed text-ava-tinta">
                                    <strong class="block">O que falta</strong>
                                    @if (! $faltaFrequencia && $s['avaliacoesPendentes'] === [])
                                        Os critérios estão cumpridos. O certificado sai quando o curso for concluído.
                                    @else
                                        <ul class="mt-1 list-disc space-y-0.5 pl-5">
                                            @if ($faltaFrequencia)
                                                <li>Chegar a {{ $s['minima'] }}% de frequência — faltam {{ $s['minima'] - $s['frequencia'] }} pontos.</li>
                                            @endif
                                            @foreach ($s['avaliacoesPendentes'] as $avaliacao)
                                                <li>Ser aprovado na avaliação "{{ $avaliacao }}".</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endif

                            <a href="/aluno/curso/{{ $ref }}"
                               class="inline-flex items-center gap-2 rounded-[10px] bg-ava-marinho px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-tinta focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                                Continuar curso
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- A antiga aba "Validação": a função é a do autenticador público. --}}
        <p class="text-apoio text-escult-ink-2">
            Precisa conferir a autenticidade de um certificado?
            <a href="/certificados" class="font-semibold text-ava-acao underline underline-offset-2 hover:text-ava-acao-escuro">Use o autenticador público</a>
            — é o mesmo que instituições e empregadores usam.
        </p>
    </div>
</x-layouts.aluno>
