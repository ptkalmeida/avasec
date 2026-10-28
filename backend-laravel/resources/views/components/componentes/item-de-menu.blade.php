@props(['rota', 'rotulo', 'ativo' => false, 'contagem' => null, 'detalhe' => null])

{{--
    Um item de menu lateral — a moldura, sem o ícone.

    Nasceu de `aluno/item-do-menu` quando o instrutor ganhou menu próprio. O
    motivo de ser compartilhado é o mesmo que fez o componente existir: "como se
    mostra o item ativo" tem de ser UMA regra — fundo lilás claro, marcador à
    esquerda e `aria-current`. Duas cópias viram duas regras parecidas, e em
    seguida alguém distingue o ativo só pela cor da fonte, que é o que a skill
    proíbe.

    O ícone entra como slot, porque é a única parte que muda entre as áreas. Com
    o menu recolhido, sobra o ícone e o rótulo vira `title` mais texto para
    leitor de tela: ícone sozinho sem nome acessível é botão mudo.

    `contagem` é o número à direita (por exemplo, pendências). Fica fora quando
    é nulo ou zero: "0 a fazer" fixo na tela é ruído permanente.
--}}

<a href="{{ $rota }}"
   @if ($ativo) aria-current="page" @endif
   :title="recolhido ? '{{ $rotulo }}' : null"
   class="flex items-center gap-3 rounded-[10px] px-3 py-2.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao
          {{ $ativo ? 'bg-ava-lilas-fundo shadow-[inset_3px_0_0_0_var(--color-ava-lilas)]' : '' }}">

    <span class="flex h-5 w-5 shrink-0 items-center justify-center {{ $ativo ? 'text-ava-lilas' : 'text-escult-ink-3' }}" aria-hidden="true">
        {{ $slot }}
    </span>

    {{-- `detalhe` é a linha de apoio sob o rótulo (por exemplo, o título da aula em "Continuar
         estudando"). Recolhido, o rótulo e o detalhe seguem para o leitor de tela. --}}
    <span class="min-w-0 flex-1" x-show="!recolhido">
        {{ $rotulo }}
        @if ($detalhe !== null && $detalhe !== '')
            <span class="block truncate text-nota font-normal text-escult-ink-3">{{ $detalhe }}</span>
        @endif
    </span>
    <span class="sr-only" x-show="recolhido" x-cloak>{{ $rotulo }}@if ($detalhe !== null && $detalhe !== ''): {{ $detalhe }}@endif</span>

    @if ($contagem !== null && $contagem > 0)
        <span x-show="!recolhido"
              class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-nota font-bold text-amber-900">
            {{ $contagem }}
            <span class="sr-only">pendente{{ $contagem === 1 ? '' : 's' }}</span>
        </span>
    @endif
</a>
