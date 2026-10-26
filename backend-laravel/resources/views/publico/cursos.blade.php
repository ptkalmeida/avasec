{{--
    "Cursos em destaque" — tela 2 do redesenho (skill `redesenhar-ava-lms`),
    migrada para Blade na fase M2 do plano 16.

    Substitui o trecho `currentView === 'cursos'` do `src/App.tsx`. O que muda em
    relação à tela React, por decisão registrada da skill e aprovada em
    19/10/2026:

    - título "Cursos Disponíveis" → **"Cursos em destaque"**;
    - a busca própria do catálogo e os filtros por categoria **saem**; a busca
      geral do cabeçalho permanece e é a única;
    - aparecem **quatro** cursos, não os nove;
    - entra o botão "Veja mais cursos", que leva ao login do aluno.

    O texto de abertura perdeu a frase "Use os filtros para encontrar por área ou
    por nome": ela descrevia dois controles que deixaram de existir. Manter o
    rótulo fiel não vale quando o rótulo passa a mentir.

    Os quatro são os quatro PRIMEIROS da vitrine, e não "os mais recentes": não
    existe data de publicação para ordenar (ver config/catalogo_publico.php). A
    skill prevê este caso e manda não afirmar a ordem — por isso nada na tela diz
    "recentes".
--}}
<x-layouts.publico
    ativo="Cursos"
    titulo="Cursos em destaque"
    descricao="Conheça os cursos oferecidos pela Escola Estadual da Cultura RJ.">

    <section class="relative overflow-hidden border-b border-ava-borda bg-gradient-to-b from-white to-ava-faixa/60 px-4 py-10 md:py-14">
        {{-- Composição geométrica da identidade, só a partir de 1280px: em tela
             estreita ela disputaria espaço com o texto, que é o conteúdo. --}}
        <div aria-hidden="true" class="absolute right-24 top-8 hidden h-40 w-20 rounded-r-full bg-ava-marinho xl:block"></div>
        <div aria-hidden="true" class="absolute right-[11.5rem] top-16 hidden h-20 w-10 rounded-l-full bg-ava-dourado xl:block"></div>
        <div aria-hidden="true" class="absolute right-8 top-6 hidden h-16 w-16 bg-ava-rosa xl:block"></div>
        <div aria-hidden="true" class="absolute bottom-8 right-10 hidden h-20 w-20 bg-ava-lilas xl:block"></div>

        <div class="relative mx-auto max-w-7xl space-y-4 text-left">
            <p class="filete text-sobretitulo uppercase text-ava-tinta">Catálogo</p>
            <h1 class="max-w-2xl font-titulo text-3xl tracking-tight text-ava-tinta md:text-pagina">Cursos em destaque</h1>
            <p class="max-w-2xl text-corpo leading-relaxed text-escult-ink-2">
                Conheça os cursos oferecidos pela Escola Estadual da Cultura RJ.
            </p>
        </div>
    </section>

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 md:py-10">

        <ul role="list" aria-label="Cursos em destaque" class="grid grid-cols-1 gap-6 text-left sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cursos as $curso)
                <li class="flex flex-col justify-between overflow-hidden rounded-2xl border border-ava-borda bg-white shadow-3xs transition-all duration-300 hover:border-ava-acao/40 hover:shadow-md">
                    <div class="relative h-44 overflow-hidden bg-ava-marinho">
                        {{-- `alt` vazio e `aria-hidden`: a imagem é ilustrativa e o
                             título vem logo abaixo em texto. Repetir o título no
                             `alt` faria o leitor de tela anunciar o curso duas
                             vezes seguidas. --}}
                        <img src="{{ $curso['image'] }}" alt="" aria-hidden="true"
                             loading="lazy" referrerpolicy="no-referrer"
                             class="h-full w-full object-cover">
                        <span class="absolute left-3 top-3 max-w-[calc(100%-1.5rem)] truncate rounded-full bg-white px-2.5 py-1 text-nota font-semibold uppercase tracking-wide text-ava-acao shadow-sm">
                            {{ $curso['category'] }}
                        </span>
                    </div>

                    <div class="flex flex-1 flex-col justify-between space-y-3 p-5">
                        <div class="space-y-2">
                            <h2 class="line-clamp-2 font-titulo text-cartao leading-snug text-ava-tinta">{{ $curso['title'] }}</h2>
                            <p class="text-apoio font-semibold text-escult-ink-2">Tutorado por: Prof. {{ $curso['instructor'] }}</p>
                            <p class="line-clamp-3 text-apoio leading-relaxed text-escult-ink-2">{{ $curso['description'] }}</p>
                        </div>

                        {{-- "Inscrever-se" sempre levou ao login, e continua levando:
                             não há inscrição pública sem conta. --}}
                        <a href="/entrar"
                           class="mt-3 flex w-full items-center justify-center gap-1.5 rounded-[10px] bg-ava-acao py-2.5 text-center text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                            <span>Inscrever-se</span>
                            <span aria-hidden="true">→</span>
                            <span class="sr-only">no curso {{ $curso['title'] }}</span>
                        </a>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="flex justify-center pt-2">
            <a href="/entrar"
               class="inline-flex items-center gap-2 rounded-[10px] border border-ava-lilas bg-ava-lilas-fundo px-7 py-3 text-rotulo font-semibold text-ava-lilas transition-colors hover:bg-ava-lilas hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-lilas focus-visible:ring-offset-2">
                <span>Veja mais cursos</span>
                <span aria-hidden="true">→</span>
            </a>
        </div>
    </div>
</x-layouts.publico>
