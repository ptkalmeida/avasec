{{--
    Rodapé institucional do portal público (Documento D §2.5, obrigatório).

    Num portal com nove páginas institucionais, o rodapé é o segundo lugar onde
    se procura o mapa do site — o primeiro é o menu. Por isso ele repete a
    organização do menu (Estudar / A Escola / Ajuda): as duas navegações dizem a
    mesma coisa, e os destinos saem da mesma tabela de rotas.

    ## Por que não há item de "Acessibilidade" aqui

    O painel de preferências (alto contraste, corpo de texto, fonte para
    dislexia, narração) vive no React, e seu estado fica em `localStorage`. O
    item do rodapé apontava para `/?janela=acessibilidade`, contando com um
    efeito do SPA que abria a janela.

    Quando a última página pública passou para Blade (20/10/2026), esse efeito
    deixou de rodar em qualquer endereço público, e o item passou a levar a
    lugar nenhum. **Link que promete e não entrega é pior que ausência**: quem
    precisa do recurso clica, não acontece nada, e conclui que o recurso não
    funciona — em vez de concluir que ainda não existe aqui.

    Por isso o item saiu, por decisão do responsável em 20/10/2026: refazer o
    painel em Blade é reimplementação, não cópia (risco 3 do plano 16), e fica
    para depois.

    **Nada foi perdido.** O React saiu do repositório na fase M7 (ADR 020), mas o
    painel continua no histórico do git (`src/components/shared/`, no commit
    anterior à M7) e volta ao rodapé assim que existir em Blade. A pendência
    está escrita no plano 18, §18.4.

    O que é acessibilidade estrutural **continua aqui e não depende do painel**:
    o salto para o conteúdo, a marcação de página atual por `aria-current`, o
    contraste do texto e a navegação por cabeçalhos.
--}}

<footer class="border-t border-ava-linha bg-white font-sans text-escult-ink-2">
    <div class="mx-auto max-w-7xl px-4 pb-5 pt-9 md:px-8">
        <div class="grid grid-cols-1 gap-x-10 gap-y-7 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr]">

            <div class="space-y-3">
                <strong class="block text-[22px] font-extrabold tracking-tight text-ava-tinta">AVASEC</strong>
                <p class="max-w-xs text-rotulo leading-relaxed text-escult-ink-2">
                    Ambiente virtual de aprendizagem da Escola Estadual da Cultura RJ.
                </p>
            </div>

            @php
                $colunas = [
                    'Estudar' => [
                        ['rotulo' => 'Cursos', 'rota' => '/cursos'],
                        ['rotulo' => 'Certificados', 'rota' => '/certificados'],
                        ['rotulo' => 'Calendário', 'rota' => '/calendario'],
                    ],
                    'A Escola' => [
                        ['rotulo' => 'O que é o AVA', 'rota' => '/o-ava'],
                        ['rotulo' => 'O Projeto', 'rota' => '/o-projeto'],
                        ['rotulo' => 'Notícias', 'rota' => '/noticias'],
                    ],
                    'Ajuda' => [
                        ['rotulo' => 'Dúvidas frequentes', 'rota' => '/duvidas'],
                        ['rotulo' => 'Orientações ao estudante', 'rota' => '/orientacoes'],
                    ],
                ];
            @endphp

            @foreach ($colunas as $titulo => $itens)
                <nav aria-label="{{ $titulo }}" class="space-y-3">
                    {{-- Título de coluna é cabeçalho de seção, não ênfase: `<h2>`
                         entra no sumário que o leitor de tela percorre, e
                         `<strong>` não entrava. --}}
                    <h2 class="block text-rotulo font-semibold text-ava-tinta">{{ $titulo }}</h2>
                    <ul class="space-y-2">
                        @foreach ($itens as $item)
                            <li>
                                <a href="{{ $item['rota'] }}"
                                   class="text-rotulo text-escult-ink-2 underline-offset-[3px] transition-colors hover:text-ava-acao hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                                    {{ $item['rotulo'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endforeach

        </div>

        <div class="mt-7 flex flex-col gap-3 border-t border-ava-borda pt-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-apoio text-escult-ink-2">
                © {{ date('Y') }} AVASEC — Escola Estadual da Cultura RJ. Todos os direitos reservados.
            </p>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-apoio text-escult-ink-2">
                <a href="/o-ava"
                   class="underline-offset-[3px] transition-colors hover:text-ava-acao hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                    O que é o AVA
                </a>
            </div>
        </div>
    </div>
</footer>
