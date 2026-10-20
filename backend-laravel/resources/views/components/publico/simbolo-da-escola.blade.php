@props(['classe' => 'h-10 w-10'])

{{--
    O símbolo da Escola Estadual da Cultura RJ, sozinho.

    Decorativo (`aria-hidden`): em todo lugar onde ele aparece o nome da escola
    está escrito ao lado, ou o link que o contém tem nome próprio. Anunciá-lo
    faria o leitor de tela dizer o nome duas vezes.
--}}
<svg class="{{ $classe }} shrink-0" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    <circle cx="50" cy="50" r="46" fill="#f8fafc" stroke="#e2e8f0" stroke-width="2" />
    <path d="M35 25h12v40H35z" fill="#3BCEAC" class="opacity-90" />
    <path d="M47 30h12v25H47z" fill="#EE4266" class="opacity-95" />
    <path d="M59 20h12v50H59z" fill="#540D6E" class="opacity-95" />
    <path d="M35 65h36v8H35z" fill="#FFD23F" class="opacity-95" />
    <circle cx="41" cy="40" r="6" fill="#FFD23F" />
    <circle cx="65" cy="45" r="7" fill="#EE4266" />
    <circle cx="53" cy="35" r="5" fill="#540D6E" />
    <path d="M47 55 a6 6 0 0 1 12 0" fill="none" stroke="#1e293b" stroke-width="4" stroke-linecap="round" />
</svg>
