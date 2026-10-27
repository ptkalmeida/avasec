{{--
    Seção da área do aluno que existe no sistema mas está DESLIGADA.

    Eventos, solicitações acadêmicas, mensagens, exercícios práticos e sala ao
    vivo têm flag própria em `config/features.php`, e todas estão desligadas.
    Nenhuma delas foi reconstruída em Blade — construir tela para função que
    ninguém pode usar seria escrever código sem leitor.

    O endereço, porém, passa a ser do servidor. Antes caía no React, que
    desenhava o mesmo aviso: enquanto um só endereço de `/aluno` sobrasse para
    o SPA, o aluno podia navegar por dentro dele e voltar às telas antigas de
    todas as outras seções (é o defeito que `rotasDoServidor.ts` corrige pela
    outra ponta). Com estas rotas, a área do aluno inteira é do servidor.

    Quando a coordenação ligar uma delas, a tela precisa ser construída — e o
    `SecoesDesligadasDoAlunoTest` falha dizendo isso, em vez de a pessoa
    descobrir pelo aviso na cara do aluno.
--}}
<x-layouts.aluno :titulo="$titulo" ativo="">

    <div class="mx-auto max-w-2xl space-y-6">

        <h1 class="font-titulo text-3xl tracking-tight text-ava-tinta">{{ $titulo }}</h1>

        <div class="space-y-3 rounded-2xl border border-amber-200 bg-amber-50 p-6">
            <p class="text-corpo font-semibold text-amber-950">Esta seção não está disponível.</p>
            <p class="text-apoio leading-relaxed text-amber-950">
                {{ $explicacao }}
                Ela não aparece no menu; você chegou aqui por um link salvo ou pelo endereço digitado.
            </p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <a href="/aluno"
               class="inline-flex items-center justify-center rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                Voltar ao início
            </a>
            @if (config('features.mensagensDiretas') === true)
                <a href="/aluno/suporte"
                   class="inline-flex items-center justify-center rounded-[10px] border border-ava-borda bg-white px-5 py-2.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                    Falar com o gestor do curso
                </a>
            @endif
        </div>
    </div>
</x-layouts.aluno>
