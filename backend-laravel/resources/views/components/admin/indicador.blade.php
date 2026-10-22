@props(['icone', 'valor', 'rotulo', 'nota' => null])

{{-- Cartão de indicador (D §1.8): ícone, valor grande e rótulo. --}}
<div class="flex items-center gap-3 rounded-[10px] border border-slate-200 bg-white p-4 shadow-card">
    <i class="fa-solid {{ $icone }} text-lg text-primary" aria-hidden="true"></i>
    <div class="min-w-0">
        <div class="text-[1.85rem] font-extrabold leading-none text-slate-900">{{ $valor }}</div>
        <div class="mt-1 text-xs text-slate-500">{{ $rotulo }}</div>
        @if ($nota !== null)
            <div class="text-[0.7rem] text-slate-400">{{ $nota }}</div>
        @endif
    </div>
</div>
