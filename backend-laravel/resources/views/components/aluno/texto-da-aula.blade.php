@props(['texto' => ''])

{{--
    Texto da aula com `**negrito**` aplicado.

    O enriquecimento é feito partindo a string e escapando cada pedaço — nunca
    montando HTML a partir do que o autor digitou. É a mesma decisão do
    `renderInline` do React, e pelo mesmo motivo: o conteúdo da aula é texto que
    uma pessoa escreve, e texto que vira marcação é por onde entra script.

    A atribuição usa a forma em linha, como no resto destas views. A forma de
    bloco não entra aqui, e o nome das duas diretivas não é escrito nem dentro
    de comentário: o compilador do Blade as processa aí dentro também, e o
    fechamento casa com a abertura errada. Da primeira vez derrubou todas as
    telas públicas com 500; da segunda, pior — a página respondeu 200 e o texto
    da aula saiu em branco.
--}}

{{-- Mantém os delimitadores no resultado para reconhecer o trecho em negrito. --}}
@php($pedacos = preg_split('/(\*\*[^*]+\*\*)/u', $texto, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$texto])
@foreach ($pedacos as $pedaco)
    @if ($pedaco === '')
        @continue
    @elseif (str_starts_with($pedaco, '**') && str_ends_with($pedaco, '**') && mb_strlen($pedaco) > 4)
        <strong class="font-bold text-ava-tinta">{{ mb_substr($pedaco, 2, -2) }}</strong>
    @else
        {{ $pedaco }}
    @endif
@endforeach
