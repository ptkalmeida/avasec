@props(['tom' => 'neutro'])

{{--
    Selo de status (D §1.5): fundo no tom 50, texto no tom 600/700 da mesma cor.
    Nunca a cor sólida como fundo. Seis tons, cada um com um papel:
    azul (informação), ambar (pendente), verde (ativo/concluído), vermelho
    (erro/bloqueio), roxo (especial), neutro (rascunho/inativo).
--}}
@php
    $cores = [
        'azul' => 'bg-blue-50 text-blue-600',
        'ambar' => 'bg-amber-50 text-amber-600',
        'verde' => 'bg-emerald-50 text-emerald-600',
        'vermelho' => 'bg-red-50 text-red-600',
        'roxo' => 'bg-violet-50 text-violet-600',
        'neutro' => 'bg-slate-100 text-slate-500',
    ];
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide', $cores[$tom] ?? $cores['neutro']]) }}>{{ $slot }}</span>
