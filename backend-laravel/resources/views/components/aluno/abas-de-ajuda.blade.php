@props(['ativa' => 'duvidas'])

{{--
    As duas abas de "Ajuda e suporte" (ADR 026): as dúvidas frequentes e a conversa com o gestor.

    Eram dois itens de menu, "Ajuda" e "Suporte pedagógico". Viraram um item com duas abas, e
    cada aba continua sendo a sua própria página, no seu próprio endereço (`/aluno/duvidas` e
    `/aluno/suporte`): quem já tinha um dos dois guardado não perde nada, e a aula continua
    mandando o aluno direto à conversa, com a aula no assunto.

    Com mensagens desligadas só existem as dúvidas, e não há o que alternar: a barra some, em
    vez de oferecer uma aba que leva a uma página que só diz que não dá.

    A aba ativa se distingue por `aria-current` e pelo traço grosso embaixo, e não só pela cor.
--}}
@if (config('features.mensagensDiretas') === true)
    <nav aria-label="Ajuda e suporte" class="flex gap-1 border-b border-ava-borda">
        @foreach ([['duvidas', '/aluno/duvidas', 'Dúvidas frequentes'], ['suporte', '/aluno/suporte', 'Falar com o gestor']] as [$chave, $rota, $rotulo])
            <a href="{{ $rota }}"
               @if ($ativa === $chave) aria-current="page" @endif
               class="-mb-px rounded-t-[10px] border-b-[3px] px-4 py-2.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao
                      {{ $ativa === $chave ? 'border-ava-lilas bg-ava-lilas-fundo' : 'border-transparent' }}">
                {{ $rotulo }}
            </a>
        @endforeach
    </nav>
@endif
