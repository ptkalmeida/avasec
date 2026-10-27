{{--
    Painel inicial do aluno — tela 3 do redesenho (conceito A aprovado).

    O que saiu, por decisão da skill: fotos e ícones de perfil, o texto de apoio
    sob a saudação, os indicadores "Curso ativo" e "Presença média", a descrição
    do curso, as abas de estado (Todos / Em andamento / Concluídos), o bloco
    "Meu progresso", o "Início da Jornada" e a seção "Disciplinas acadêmicas
    disponíveis".

    O que NÃO saiu junto, por decisão de 19/10: as duas funções que viviam nos
    blocos removidos. Matricular-se em outro curso virou o item "Catálogo" do
    menu lateral; os cursos concluídos viraram submenu. Tirar do lugar não é
    tirar a função.

    A barra é compacta e o texto ao lado dela é "X de Y aulas concluídas" — sem
    o percentual repetido, que a skill proíbe. O número existe no `aria-valuenow`
    porque ali ele é a forma de a barra ser lida, não uma segunda exibição.
--}}
<x-layouts.aluno
    titulo="Início"
    ativo="painel"
    :cursoDoMenu="$emAndamento[0] ?? null"
    :concluidosDoMenu="$concluidos">

    <div class="mx-auto max-w-4xl space-y-8">

        <h1 class="font-titulo text-3xl tracking-tight text-ava-tinta">
            Olá, {{ $aluno['nome'] }}
        </h1>

        @forelse ($emAndamento as $curso)
            <article class="overflow-hidden rounded-2xl border border-ava-borda bg-white shadow-3xs">
                <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center md:p-6">

                    {{-- A capa escolhida pela gestão, e a MESMA em toda tela que
                         representa este curso. Sem capa, um bloco da paleta — e
                         não uma foto de banco de imagens, que afirmaria algo
                         sobre um curso que ninguém cadastrou. --}}
                    <div class="h-28 w-full shrink-0 overflow-hidden rounded-xl bg-ava-marinho sm:w-44">
                        @if (! empty($curso['imagem_capa']) || ! empty($curso['miniatura']))
                            <img src="{{ $curso['imagem_capa'] ?: $curso['miniatura'] }}" alt="" aria-hidden="true"
                                 referrerpolicy="no-referrer" class="h-full w-full object-cover">
                        @endif
                    </div>

                    <div class="min-w-0 flex-1 space-y-3">
                        <div class="space-y-1">
                            @if (! empty($curso['categoria']))
                                <span class="inline-block rounded-full bg-ava-lilas-fundo px-2.5 py-1 text-nota font-semibold uppercase tracking-wide text-ava-lilas">
                                    {{ $curso['categoria'] }}
                                </span>
                            @endif
                            <h2 class="font-titulo text-cartao leading-snug text-ava-tinta">{{ $curso['titulo'] }}</h2>
                        </div>

                        <div class="space-y-1.5">
                            <div class="h-2 w-full overflow-hidden rounded-full bg-ava-faixa"
                                 role="progressbar"
                                 aria-valuenow="{{ $curso['percentual'] }}"
                                 aria-valuemin="0"
                                 aria-valuemax="100"
                                 aria-label="Progresso em {{ $curso['titulo'] }}">
                                <div class="h-full rounded-full bg-ava-lilas" style="width: {{ $curso['percentual'] }}%"></div>
                            </div>
                            <p class="text-apoio text-escult-ink-2">
                                {{ $curso['aulasConcluidas'] }} de {{ $curso['aulasNoCurso'] }} aulas concluídas
                            </p>
                        </div>
                    </div>

                    <a href="/aluno/curso/{{ $curso['slug'] ?? $curso['id'] }}"
                       class="shrink-0 rounded-[10px] bg-ava-acao px-6 py-3 text-center text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                        Continuar curso
                    </a>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-ava-borda bg-ava-faixa/50 p-8 text-center">
                <p class="text-corpo text-escult-ink-2">Você ainda não tem curso em andamento.</p>
                <a href="/aluno/catalogo"
                   class="mt-4 inline-block rounded-[10px] bg-ava-acao px-6 py-3 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                    Descobrir cursos
                </a>
            </div>
        @endforelse
    </div>
</x-layouts.aluno>
