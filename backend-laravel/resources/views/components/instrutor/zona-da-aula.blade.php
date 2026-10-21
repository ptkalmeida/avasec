@props(['id', 'titulo', 'dica' => null, 'editando' => false, 'editavel' => true])

{{--
    Uma das quatro zonas da página da aula (identificação, vídeo, material
    didático, anexos).

    O lápis é um LINK, e não um botão com JavaScript: `?editar=<zona>` abre o
    formulário da zona no servidor. O F5 no meio da edição volta para o mesmo
    formulário, o erro de validação reabre a zona certa, e tudo funciona sem JS.
    Só uma zona fica aberta por vez — o foco não se perde entre quatro formulários.
--}}
<section id="{{ $id }}" aria-labelledby="{{ $id }}-titulo"
         class="scroll-mt-6 overflow-hidden rounded-2xl border border-ava-borda bg-white shadow-3xs">
    <header class="flex items-start justify-between gap-3 border-b border-ava-borda px-4 py-3 md:px-5">
        <div class="min-w-0">
            <h2 id="{{ $id }}-titulo" class="text-sobretitulo uppercase text-ava-tinta">{{ $titulo }}</h2>
            @if ($dica !== null)
                <p class="mt-0.5 text-apoio text-escult-ink-2">{{ $dica }}</p>
            @endif
        </div>

        @if ($editavel)
            <a href="{{ $editando ? request()->url().'#'.$id : request()->url().'?editar='.$id.'#'.$id }}"
               class="inline-flex shrink-0 items-center rounded-[10px] border border-ava-borda bg-white px-3 py-1.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                {{ $editando ? 'Fechar' : 'Editar' }}<span class="sr-only"> {{ mb_strtolower($titulo) }}</span>
            </a>
        @endif
    </header>

    <div class="p-4 md:p-5">
        {{ $slot }}
    </div>
</section>
