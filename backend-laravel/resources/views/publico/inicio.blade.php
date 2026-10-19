{{--
    Página inicial pública — tela 1 do redesenho (conceito A aprovado).

    Hero dividido, faixa de estatísticas e o destaque Patrimônio Vivo. **A página
    termina aí**, por decisão da skill.

    Duas coisas saíram, e elas são o motivo de esta tela ter esperado:

    1. **"Sentiu falta de algum curso?"** — ícone, texto, campo e botão, por
       remoção explícita da skill. Nada ocupa o lugar.
    2. **"A educação que orienta esta escola"** — a seção dos três princípios,
       que caía pela regra de terminar no Patrimônio Vivo. Ela era o ÚNICO lugar
       onde aquele texto existia, então foi **movida** para `/o-projeto`, que é a
       página que fala da concepção pedagógica. Tirar do lugar não é tirar a
       função; apagar texto institucional seria.

    O bloco "Minha Aprendizagem", que aparecia aqui para quem estava logado,
    também não voltou: tudo o que ele mostrava (cursos concluídos, certificados,
    continuar a aula) vive no painel, e o cabeçalho agora oferece "Meu painel" a
    quem tem sessão. Repetir o painel na home era a terceira cópia do mesmo dado.
--}}
<x-layouts.publico
    titulo="Escola Estadual da Cultura RJ"
    descricao="A AVASEC é o portal de capacitação e qualificação profissional da Escola Estadual da Cultura RJ.">

    <section class="relative flex flex-col items-center overflow-hidden bg-white px-4 py-12 text-ava-tinta lg:py-20">
        <div class="mx-auto grid w-full max-w-7xl grid-cols-1 items-center gap-10 text-left md:px-8 lg:grid-cols-12 lg:gap-12">

            <div class="relative z-10 min-w-0 space-y-6 lg:col-span-7">
                <p class="filete text-sobretitulo uppercase text-ava-tinta">
                    Inscrições Abertas — Cursos Livres de Qualificação
                </p>

                <h1 class="break-words font-titulo text-[2rem] leading-[1.08] tracking-tight text-ava-tinta sm:text-5xl lg:text-[54px]">
                    Escola Estadual da Cultura <span class="text-ava-acao">RJ</span>
                </h1>

                <p class="max-w-2xl text-corpo leading-relaxed text-escult-ink-2">
                    A AVASEC é o portal de capacitação e qualificação profissional da Escola Estadual
                    da Cultura RJ. Oferecemos cursos livres e de excelência em Cultura, Gestão Cultural,
                    Economia Criativa e Linguagens Artísticas com certificação digital homologada.
                </p>

                <div class="flex flex-col gap-3 pt-2 sm:flex-row">
                    <a href="/cursos"
                       class="flex items-center justify-center gap-2 rounded-[10px] bg-ava-acao px-7 py-3 text-rotulo font-semibold text-white shadow-sm transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                        <span>Descubra mais</span>
                        <span aria-hidden="true">→</span>
                    </a>
                    <a href="/entrar"
                       class="flex items-center justify-center gap-2 rounded-[10px] border border-ava-acao bg-white px-7 py-3 text-rotulo font-semibold text-ava-acao transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                        <span aria-hidden="true">▶</span>
                        <span>Comece a estudar</span>
                    </a>
                </div>
            </div>

            {{--
                Composição geométrica da identidade, SEM foto.

                Aqui havia o retrato de um educador homenageado. A menção
                nominal saiu de todo o site por decisão da coordenação
                (10/09/2026), e no lugar entra uma referência à educação, sem
                pessoa nenhuma. Sem imagem, e não com uma foto de banco: uma
                foto de estudantes que não são desta escola seria outra
                afirmação falsa, do mesmo tipo da que acabou de sair.
            --}}
            <div class="relative flex min-w-0 items-center justify-center lg:col-span-5">
                <div class="relative h-[340px] w-full max-w-[420px] shrink-0 sm:h-[380px]">
                    <div aria-hidden="true" class="absolute left-6 top-0 h-36 w-[72px] rounded-r-full bg-ava-marinho"></div>
                    <div aria-hidden="true" class="absolute left-0 top-10 h-20 w-10 rounded-l-full bg-ava-dourado"></div>
                    <div aria-hidden="true" class="absolute right-4 top-2 h-20 w-20 bg-ava-rosa"></div>
                    <div aria-hidden="true" class="absolute bottom-0 right-0 h-28 w-28 rounded-tl-full bg-ava-lilas-fundo"></div>
                    <div aria-hidden="true" class="absolute bottom-4 right-20 h-16 w-16 bg-ava-lilas"></div>

                    <div class="absolute inset-x-6 bottom-12 top-12 z-10 flex flex-col items-center justify-center gap-3 rounded-3xl border border-ava-borda bg-white px-6 text-center shadow-xl sm:inset-x-10">
                        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-ava-icone-fundo">
                            <svg class="h-7 w-7 text-ava-acao" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M12 3 1 8l11 5 9-4.09V17h2V8L12 3Z"/><path d="M5 13.18v3.82c0 1.1 3.13 3 7 3s7-1.9 7-3v-3.82l-7 3.18-7-3.18Z"/>
                            </svg>
                        </div>
                        <span class="font-titulo text-lg leading-tight text-ava-tinta">
                            Educação pública, gratuita e de qualidade
                        </span>
                        <span class="text-nota text-escult-ink-2">Escola Estadual da Cultura RJ</span>
                        <div class="flex flex-wrap justify-center gap-1.5 pt-1">
                            <span class="rounded-full border border-ava-acao/30 px-2.5 py-0.5 text-nota font-semibold text-ava-acao">✦ Criatividade</span>
                            <span class="rounded-full bg-ava-marinho px-2.5 py-0.5 text-nota font-semibold text-white">▲ Inovação</span>
                            <span class="rounded-full border border-ava-rosa/30 px-2.5 py-0.5 text-nota font-semibold text-ava-rosa">● Arte</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="relative overflow-hidden border-t border-ava-borda bg-white px-4 py-14">
        <div class="mx-auto grid max-w-7xl grid-cols-1 items-center gap-10 lg:grid-cols-12 lg:gap-12">

            <div class="min-w-0 space-y-6 text-left lg:col-span-6">
                <div class="space-y-3">
                    <div class="h-[3px] w-7 rounded-full bg-ava-dourado" aria-hidden="true"></div>
                    <h2 class="font-titulo text-3xl tracking-tight text-ava-tinta">AVASEC em dados</h2>
                </div>

                <p class="text-apoio leading-relaxed text-escult-ink-2">
                    A Escola Estadual da Cultura RJ foi lançada em janeiro de 2024 e é promovida pela
                    Diretoria de Políticas para Trabalhadores da Cultura e da Economia Criativa por meio
                    da Coordenação de Capacitação e Qualificação Profissional. Veja aqui os resultados já
                    alcançados de nossa rede:
                </p>

                {{-- Os quatro números são conteúdo institucional fixo, como sempre
                     foram no React: não há consulta por trás deles, e inventar uma
                     origem seria afirmar uma precisão que o sistema não tem. --}}
                <ul role="list" class="grid grid-cols-1 gap-4 pt-2 min-[400px]:grid-cols-2">
                    @foreach ([
                        ['188K', 'Estudantes cadastrados'],
                        ['300K', 'Inscrições nos cursos'],
                        ['66K', 'Mil concluintes'],
                        ['4M+', 'Visitas à plataforma'],
                    ] as [$numero, $rotulo])
                        <li class="flex items-center gap-3.5 rounded-2xl border border-ava-borda bg-white p-4 shadow-3xs">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-ava-lilas-fundo" aria-hidden="true">
                                <span class="h-2.5 w-2.5 rounded-full bg-ava-lilas"></span>
                            </span>
                            <span class="min-w-0">
                                <strong class="block text-2xl font-black leading-none text-ava-acao">{{ $numero }}</strong>
                                <span class="mt-1.5 block text-nota font-semibold uppercase tracking-wide text-escult-ink-2">{{ $rotulo }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="min-w-0 lg:col-span-6">
                <div class="relative flex min-h-[300px] w-full items-end overflow-hidden rounded-2xl bg-ava-marinho text-white">
                    <img src="https://images.unsplash.com/photo-1514306191717-452ec28c7814?w=600&auto=format&fit=crop&q=70"
                         alt="Estudantes em atividade prática de arte e cultura"
                         referrerpolicy="no-referrer" loading="lazy"
                         class="absolute inset-y-0 right-0 h-full w-full object-cover sm:w-3/5">
                    <div class="absolute inset-0 bg-gradient-to-t from-ava-marinho via-ava-marinho/90 to-ava-marinho/10 sm:bg-gradient-to-r" aria-hidden="true"></div>
                    <div class="relative z-10 space-y-2 p-6 text-left sm:max-w-[60%] sm:p-8">
                        <span class="block text-sobretitulo uppercase text-ava-dourado">Patrimônio Vivo</span>
                        <strong class="block font-titulo text-lg">Fazer Artístico Decolonial</strong>
                        <p class="text-apoio text-white/85">
                            Oficinas ministradas de maneira autônoma com apoio das comunidades locais e
                            certificadas em nossa rede.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.publico>
