{{--
    A prévia de UM trecho do material, devolvida por `AreaDoInstrutorController::previa`
    enquanto a pessoa digita. Sem layout: é um pedaço de HTML que o Alpine encaixa
    na página. O trecho sai pelo MESMO componente que a aula do aluno usa, então a
    prévia não tem regra de formatação própria.
--}}
@if ($erro !== null)
    <p class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-apoio text-amber-950">{{ $erro }}</p>
@elseif ($blocos === [])
    <p class="text-apoio italic text-escult-ink-2">Nada para mostrar ainda.</p>
@else
    <x-aluno.conteudo-da-aula :blocos="$blocos" />
@endif
