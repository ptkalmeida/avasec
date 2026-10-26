{{--
    "O Projeto Pedagógico" — terceira tela migrada (fase M1 do plano 16).

    Substitui `src/components/pages/ProjetoPage.tsx`. Conteúdo do mesmo
    `SitePageContentService`; muda só quem monta o HTML.

    Duas diferenças em relação às telas anteriores, e as duas são do original:

    1. O texto de abertura NÃO vai para a moldura. Lá em cima ficam só o
       sobretítulo e o título; a descrição entra na coluna de texto, ao lado da
       imagem, porque é ela que o leitor lê junto dos pilares.
    2. O alinhamento é à esquerda (a moldura sem `centralizado`), ao contrário
       de "O que é o AVA" e "Dúvidas".

    A numeração dos pilares vem da ORDEM, não do conteúdo: o admin edita título
    e descrição, nunca o número. Era assim no React (`index + 1`) e continua.
--}}
<x-layouts.publico ativo="A Escola" :titulo="$pagina['title']" :descricao="$pagina['description']">
    <x-componentes.moldura-pagina
        :sobretitulo="$pagina['eyebrow']"
        :titulo="$pagina['title']"
    >
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
            {{--
                Imagem decorativa. O contorno deslocado do desenho antigo saiu:
                com `w-full` e `left-4` ele passava 16px da tela em 320px. No
                lugar ficou o par geométrico da skill, dentro da caixa.
            --}}
            <div class="lg:col-span-5 relative flex items-center justify-center">
                <div class="relative w-full max-w-[22rem] aspect-square shrink-0">
                    <div aria-hidden="true" class="absolute -top-0 -left-0 h-16 w-16 rounded-br-full bg-ava-dourado z-10"></div>
                    <div aria-hidden="true" class="absolute bottom-0 right-0 h-16 w-16 bg-ava-rosa z-10"></div>
                    <div class="w-full h-full rounded-2xl overflow-hidden shadow-xl relative bg-ava-marinho">
                        {{--
                            `referrerpolicy="no-referrer"`: o endereço da imagem é
                            editável pelo admin e costuma apontar para fora. Sem
                            isto, o navegador conta ao servidor de destino de qual
                            página do portal a pessoa veio.
                        --}}
                        <img
                            src="{{ $pagina['imageUrl'] }}"
                            alt="Escola Estadual da Cultura RJ"
                            class="w-full h-full object-cover filter brightness-95"
                            referrerpolicy="no-referrer"
                        >
                    </div>
                </div>
            </div>

            {{-- Texto de abertura e os pilares do plano político-pedagógico. --}}
            <div class="lg:col-span-7 space-y-6 text-left">
                <p class="text-corpo text-escult-ink-2 leading-relaxed">{{ $pagina['description'] }}</p>

                <div class="space-y-4 pt-2">
                    @foreach ($pagina['items'] as $i => $pilar)
                        <div class="flex gap-4 rounded-2xl border border-ava-borda bg-white p-4.5">
                            <div class="h-11 w-11 rounded-full bg-ava-icone-fundo flex items-center justify-center text-ava-acao text-lg font-black shrink-0">
                                {{ $i + 1 }}
                            </div>
                            <div class="space-y-1 min-w-0">
                                <strong class="text-rotulo text-ava-tinta font-bold block">{{ $pilar['title'] }}</strong>
                                <p class="text-apoio text-escult-ink-2 leading-relaxed">{{ $pilar['description'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </x-componentes.moldura-pagina>

    {{--
        "A educação que orienta esta escola" — texto que MOROU na página inicial.

        A skill `redesenhar-ava-lms` manda a home terminar no destaque Patrimônio
        Vivo, e esta seção ficava depois dele. Ela era o único lugar do site onde
        este texto existia, então mudou de endereço em vez de desaparecer: é a
        página que fala da concepção pedagógica, e é aqui que ele faz sentido.

        O texto é o mesmo, palavra por palavra. O botão "Conheça o projeto" que a
        acompanhava na home saiu: ele apontava para esta página, e um botão que
        leva à página onde ele já está é um beco.

        Aqui havia, antes de 10/09/2026, uma homenagem nominal a um educador. A
        menção saiu de todo o site por decisão da coordenação; os três princípios
        continuam porque descrevem uma concepção pedagógica, não uma biografia.
    --}}
    <section id="nossa-concepcao" class="bg-white px-4 py-16 text-ava-tinta">
        <div class="mx-auto max-w-5xl space-y-10 text-left">

            <div class="space-y-3 text-center">
                <p class="filete text-sobretitulo uppercase text-ava-tinta">Nossa concepção</p>
                <h2 class="font-titulo text-3xl tracking-tight text-ava-tinta md:text-secao">A educação que orienta esta escola</h2>
                <p class="font-sans text-nota tracking-wide text-escult-ink-2">Escola Estadual da Cultura RJ</p>
            </div>

            <p class="mx-auto max-w-2xl text-center text-apoio leading-relaxed text-escult-ink-2">
                Ensinar é uma prática que começa pelo que o estudante já sabe. Nesta escola,
                arte, cultura e economia criativa são o caminho para desenvolver leitura crítica
                da realidade, autonomia e capacidade de transformar o próprio contexto — dentro e
                fora da sala de aula.
            </p>

            <div class="grid grid-cols-1 gap-8 sm:grid-cols-3 sm:gap-0 sm:divide-x sm:divide-ava-borda">
                @foreach ([
                    ['Centrada em quem aprende', 'O processo parte do respeito e da bagagem que cada estudante já traz consigo.'],
                    ['Diálogo, não transmissão', 'Aprender é via de mão dupla: quem ensina também aprende com a turma.'],
                    ['Leitura crítica', 'Educar para a autonomia e a cidadania ativa, com reflexão sobre a realidade.'],
                ] as [$titulo, $texto])
                    <div class="space-y-2 sm:px-8 sm:first:pl-0 sm:last:pr-0">
                        <span class="block h-[3px] w-7 rounded-full bg-ava-lilas" aria-hidden="true"></span>
                        <strong class="block font-titulo text-cartao text-ava-tinta">{{ $titulo }}</strong>
                        <p class="text-apoio leading-relaxed text-escult-ink-2">{{ $texto }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</x-layouts.publico>
