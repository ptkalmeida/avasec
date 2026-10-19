{{--
    Assinatura da marca: símbolo + "AVASEC" + o descritor da escola.

    É o mesmo desenho do `AvasecLogo` do React, e os dois precisam continuar
    iguais enquanto as duas tecnologias convivem — a marca é a primeira coisa
    que a pessoa usa para saber que não trocou de site ao navegar de uma tela
    migrada para uma que ainda não foi.

    O descritor quebra em DUAS linhas deliberadas, com `whitespace-nowrap`, e
    não por acidente: em caixa alta com entreletra de 0,08em ele ocupa cerca de
    275px, e espremido pelo menu virava quatro linhas fora do esquadro do
    símbolo. `shrink-0` no contêiner é o que impede o cabeçalho de comprimi-lo.
--}}

<div class="flex shrink-0 select-none items-center gap-2">
    <svg class="h-10 w-10 shrink-0" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="AVASEC">
        <circle cx="50" cy="50" r="46" fill="#f8fafc" stroke="#e2e8f0" stroke-width="2" />
        {{-- Segmentos abstratos da identidade visual da Escola Estadual da Cultura RJ --}}
        <path d="M35 25h12v40H35z" fill="#3BCEAC" class="opacity-90" />
        <path d="M47 30h12v25H47z" fill="#EE4266" class="opacity-95" />
        <path d="M59 20h12v50H59z" fill="#540D6E" class="opacity-95" />
        <path d="M35 65h36v8H35z" fill="#FFD23F" class="opacity-95" />
        <circle cx="41" cy="40" r="6" fill="#FFD23F" />
        <circle cx="65" cy="45" r="7" fill="#EE4266" />
        <circle cx="53" cy="35" r="5" fill="#540D6E" />
        <path d="M47 55 a6 6 0 0 1 12 0" fill="none" stroke="#1e293b" stroke-width="4" stroke-linecap="round" />
    </svg>

    <div class="text-left leading-tight">
        <span class="block font-sans text-2xl font-black tracking-tight text-ava-acao">AVASEC</span>
        <span class="mt-1 hidden whitespace-nowrap text-nota font-semibold uppercase tracking-[0.08em] text-escult-ink-2 sm:block">
            Escola Estadual
            <br>
            da Cultura RJ
        </span>
    </div>
</div>
