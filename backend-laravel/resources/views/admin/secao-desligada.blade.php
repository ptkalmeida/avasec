{{--
    Seção do painel administrativo que existe no sistema mas está DESLIGADA.

    Exercícios práticos e solicitações acadêmicas têm flag própria em
    `config/features.php`, e as duas estão desligadas. Nenhuma foi reconstruída em
    Blade — construir tela para função que ninguém pode usar seria escrever código
    sem leitor. O endereço, porém, é do servidor: antes caía no React, que desenhava
    o aviso E, por baixo dele, a tela antiga inteira.

    Quando a coordenação ligar uma delas, a tela precisa ser construída — e o
    `SecoesDesligadasDoAdminTest` falha dizendo isso, em vez de a pessoa descobrir
    pelo aviso na cara do administrador.
--}}
<x-layouts.admin :titulo="$item['rotulo']" contexto="{{ $item['subtitulo'] }}">

    <div class="max-w-2xl space-y-4 rounded-[10px] border border-amber-100 bg-amber-50 p-6">
        <p class="flex items-center gap-2 font-semibold text-amber-800">
            <i class="fa-solid fa-lock" aria-hidden="true"></i> Esta seção não está disponível nesta versão da plataforma.
        </p>
        <p class="text-sm leading-relaxed text-amber-800">
            {{ $explicacao }}
            Ela não aparece no menu; você chegou aqui por um link salvo ou pelo endereço digitado.
        </p>
        <a href="/admin"
           class="inline-flex items-center gap-2 rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao Dashboard
        </a>
    </div>
</x-layouts.admin>
