{{--
    Seção do instrutor que existe no sistema mas está DESLIGADA.

    Irmã de `aluno/secao-desligada`, pelo mesmo motivo: o endereço passa a ser do
    servidor, que diz o que aconteceu, em vez de o React desenhar o mesmo aviso —
    e de sobrar um endereço de `/inst` para o SPA. Quando a função for ligada, a
    rota responde 501 e o teste falha apontando qual tela construir.
--}}
<x-layouts.instrutor :titulo="$titulo" ativo="avaliacoes" :cursoDoMenu="$curso">

    <div class="mx-auto max-w-2xl space-y-6">

        <h1 class="font-titulo text-3xl tracking-tight text-ava-tinta">{{ $titulo }}</h1>

        <div class="space-y-3 rounded-2xl border border-amber-200 bg-amber-50 p-6">
            <p class="text-corpo font-semibold text-amber-950">Esta seção não está disponível.</p>
            <p class="text-apoio leading-relaxed text-amber-950">
                {{ $explicacao }}
                Ela não aparece no menu; você chegou aqui por um link salvo ou pelo endereço digitado.
            </p>
        </div>

        <a href="/inst/curso/{{ $ref }}"
           class="inline-flex items-center justify-center rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
            Voltar ao curso
        </a>
    </div>
</x-layouts.instrutor>
