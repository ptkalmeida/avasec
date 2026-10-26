{{--
    Ajuda do aluno — dúvidas frequentes e o caminho até uma pessoa.

    O acordeão é o componente do `/duvidas` público, com o mesmo conteúdo
    editável no admin (ver AreaDoAlunoController::ajuda para o motivo de não
    trazer as seis perguntas que o React tinha escritas no código).

    A busca e o filtro por categoria do React não vieram: o conteúdo editável
    não tem categoria, e com três a trinta perguntas a lista se lê inteira.
--}}
<x-layouts.aluno titulo="Ajuda" ativo="ajuda">

    <div class="mx-auto max-w-3xl space-y-8">

        <div class="space-y-2">
            <h1 class="font-titulo text-3xl tracking-tight text-ava-tinta">Ajuda</h1>
            <p class="text-apoio leading-relaxed text-escult-ink-2">Respostas para as dúvidas mais comuns sobre o ambiente de estudos.</p>
        </div>

        <section aria-labelledby="titulo-frequentes" class="space-y-4">
            <h2 id="titulo-frequentes" class="font-titulo text-cartao text-ava-tinta">Dúvidas frequentes</h2>
            <livewire:publico.duvidas-frequentes />
        </section>

        <section aria-labelledby="titulo-pessoa" class="space-y-3 rounded-2xl border border-ava-borda bg-white p-5 shadow-3xs">
            <h2 id="titulo-pessoa" class="font-titulo text-cartao text-ava-tinta">Não encontrou a resposta?</h2>
            @if (config('features.mensagensDiretas') === true)
                <p class="text-apoio text-escult-ink-2">Escreva para o gestor do seu curso. Se a dúvida for sobre uma aula, envie de dentro dela: o gestor recebe o título da aula junto.</p>
                <a href="/aluno/suporte"
                   class="inline-flex items-center justify-center rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                    Falar com o gestor do curso
                </a>
            @else
                <p class="text-apoio text-escult-ink-2">Procure a coordenação do seu curso na escola.</p>
            @endif
        </section>
    </div>
</x-layouts.aluno>
