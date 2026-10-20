{{--
    Assinatura da marca no topo: símbolo + o nome da escola.

    O logotipo "AVASEC" saiu do topo por decisão do responsável em 20/10/2026:
    quem assina a página é a Escola Estadual da Cultura RJ. O nome deixou de ser
    descritor miúdo e passou a ser o texto da marca.

    Duas consequências que não são estéticas:

    - O nome aparece em TODAS as larguras. Antes o descritor era `hidden` abaixo
      de `sm`, e no celular a marca mostrava só "AVASEC"; tirar a palavra sem
      mudar isto deixaria a marca sem texto nenhum no telefone.
    - A quebra em duas linhas continua deliberada, com `whitespace-nowrap`:
      largura previsível, que o menu não espreme (`shrink-0` no contêiner).
    - Abaixo de `sm` o conjunto encolhe (símbolo de 32px, nome em 13px). Em
      320px a linha do cabeçalho tem 288px úteis, e marca + "Entrar" + menu
      somavam 321: a página inteira passava a rolar na horizontal. Medido, não
      estimado — e o e2e de cada página pública confere os 320px.

    É o mesmo desenho do `AvasecLogo` do React, e os dois precisam continuar
    iguais enquanto as duas tecnologias convivem — a marca é a primeira coisa
    que a pessoa usa para saber que não trocou de site.
--}}

<div class="flex shrink-0 select-none items-center gap-1.5 sm:gap-2">
    <x-publico.simbolo-da-escola classe="h-8 w-8 sm:h-10 sm:w-10" />

    <span class="block whitespace-nowrap text-left font-sans text-[13px] font-extrabold leading-tight tracking-tight text-ava-tinta sm:text-base">
        Escola Estadual
        <br>
        da Cultura RJ
    </span>
</div>
