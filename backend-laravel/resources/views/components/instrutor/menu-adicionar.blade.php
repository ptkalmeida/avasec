@props(['apos', 'base', 'versao', 'aberto' => false, 'novo' => null, 'campo', 'uploadAtivo' => false])

{{--
    O "Adicionar" que fica entre dois trechos do material (e no topo, `apos = -1`).

    Fechado, é um link que abre o menu pelo endereço (`?adicionar=N`); aberto,
    oferece os tipos. Texto nasce com um trecho-modelo, que a pessoa edita em
    seguida; imagem e vídeo não têm modelo — o arquivo ou o link só existem depois
    que a pessoa escolhe, então abrem o formulário (`?novo=imagem&apos=N`).

    Código NÃO é oferecido: quem escreve as aulas é gestor de conteúdo, e um bloco
    que pede linguagem e indentação convida ao erro. Os que já existem continuam
    editáveis pelo lápis.
--}}
@php
    $url = request()->url();
    $ancora = 'adicionar-'.$apos;
    $tipos = ['secao' => 'Seção', 'subsecao' => 'Subtítulo', 'paragrafo' => 'Parágrafo', 'listaNumerada' => 'Lista numerada', 'listaMarcadores' => 'Lista'];
    $botao = 'inline-flex cursor-pointer items-center rounded-[10px] border border-ava-borda bg-white px-3 py-1.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao';
@endphp

<div id="{{ $ancora }}" class="scroll-mt-6 py-1.5">
    @if ($novo !== null)
        <div id="novo" class="scroll-mt-6 space-y-3 rounded-xl border-2 border-ava-acao/60 bg-ava-faixa/40 p-4">
            <p class="text-sobretitulo uppercase text-ava-acao">Adicionar: {{ $novo === 'imagem' ? 'imagem' : 'vídeo complementar' }}</p>

            @if ($errors->has('material'))
                <p role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-apoio font-semibold text-rose-800">{{ $errors->first('material') }}</p>
            @endif

            <form method="POST" action="{{ $base }}/material" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <input type="hidden" name="acao" value="inserir">
                <input type="hidden" name="versao" value="{{ $versao }}">
                <input type="hidden" name="apos" value="{{ $apos }}">
                <input type="hidden" name="tipo" value="{{ $novo }}">

                @if ($novo === 'imagem')
                    @if ($uploadAtivo)
                        <div class="space-y-1.5">
                            <label for="nova-arquivo" class="block text-rotulo font-semibold text-ava-tinta">Arquivo da imagem</label>
                            <p id="nova-dica-arquivo" class="text-apoio text-escult-ink-2">
                                PNG, JPG, WEBP ou GIF. A imagem enviada fica num endereço público: quem tiver o link abre o arquivo sem
                                estar matriculado. <strong>Não use imagem para prova, gabarito ou dado pessoal.</strong>
                            </p>
                            <input id="nova-arquivo" name="arquivo" type="file" accept="image/png,image/jpeg,image/webp,image/gif" required aria-describedby="nova-dica-arquivo"
                                   class="block w-full text-apoio text-ava-tinta file:mr-3 file:cursor-pointer file:rounded-[10px] file:border file:border-ava-borda file:bg-white file:px-4 file:py-2 file:text-rotulo file:font-semibold file:text-ava-tinta hover:file:bg-ava-faixa">
                        </div>
                    @else
                        <p class="text-apoio text-escult-ink-2">O envio de arquivos está desligado nesta instalação: não é possível adicionar imagens agora.</p>
                    @endif
                @else
                    <div class="space-y-1.5">
                        <label for="nova-url" class="block text-rotulo font-semibold text-ava-tinta">Link do vídeo no YouTube</label>
                        <p id="nova-dica-url" class="text-apoio text-escult-ink-2">
                            Vídeo complementar, no meio da explicação. O vídeo principal da aula fica na zona do topo e abre antes do texto.
                        </p>
                        <input id="nova-url" name="url" type="text" inputmode="url" required aria-describedby="nova-dica-url" class="{{ $campo }}"
                               placeholder="https://youtu.be/…" value="{{ old('url') }}">
                    </div>
                @endif

                <div class="space-y-1.5">
                    <label for="nova-descricao" class="block text-rotulo font-semibold text-ava-tinta">
                        {{ $novo === 'imagem' ? 'Descrição da imagem (obrigatória)' : 'Título do vídeo (obrigatório)' }}
                    </label>
                    @if ($novo === 'imagem')
                        <p id="nova-dica-descricao" class="text-apoio text-escult-ink-2">
                            Descreva o que a imagem mostra. Quem usa leitor de tela, ou está com a internet ruim, recebe esta descrição no lugar da figura.
                        </p>
                    @endif
                    <input id="nova-descricao" name="descricao" type="text" required @if ($novo === 'imagem') aria-describedby="nova-dica-descricao" @endif class="{{ $campo }}"
                           placeholder="{{ $novo === 'imagem' ? 'Ex.: hierarquia das classes Pessoa, Física e Aluno' : 'Ex.: demonstração do cadastro' }}"
                           value="{{ old('descricao') }}">
                </div>

                <div class="space-y-1.5">
                    <label for="nova-legenda" class="block text-rotulo font-semibold text-ava-tinta">Legenda <span class="font-normal text-escult-ink-2">(opcional)</span></label>
                    <p id="nova-dica-legenda" class="text-apoio text-escult-ink-2">
                        Comece por Figura, Imagem, Gráfico, Vídeo, Tabela ou Quadro, seguido do número — senão ela vira um parágrafo solto.
                    </p>
                    <input id="nova-legenda" name="legenda" type="text" aria-describedby="nova-dica-legenda" class="{{ $campo }}"
                           placeholder="{{ $novo === 'imagem' ? 'Ex.: Figura 1 - fluxo de matrícula' : 'Ex.: Vídeo 1 - demonstração' }}"
                           value="{{ old('legenda') }}">
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <a href="{{ $url }}?editar=material#{{ $ancora }}" class="{{ $botao }}">Cancelar</a>
                    @if ($novo === 'video' || $uploadAtivo)
                        <button type="submit"
                                class="cursor-pointer rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                            Adicionar
                        </button>
                    @endif
                </div>
            </form>
        </div>
    @elseif ($aberto)
        <div class="space-y-2 rounded-xl border border-ava-borda bg-white p-3 shadow-3xs">
            <p class="text-sobretitulo uppercase text-escult-ink-2">Adicionar aqui</p>
            <form method="POST" action="{{ $base }}/material" class="flex flex-wrap gap-2">
                @csrf
                <input type="hidden" name="acao" value="inserir">
                <input type="hidden" name="versao" value="{{ $versao }}">
                <input type="hidden" name="apos" value="{{ $apos }}">
                @foreach ($tipos as $valor => $rotulo)
                    <button type="submit" name="tipo" value="{{ $valor }}" class="{{ $botao }}">{{ $rotulo }}</button>
                @endforeach
                <a href="{{ $url }}?editar=material&novo=imagem&apos={{ $apos }}#novo" class="{{ $botao }}">Imagem</a>
                <a href="{{ $url }}?editar=material&novo=video&apos={{ $apos }}#novo" class="{{ $botao }}">Vídeo</a>
                <a href="{{ $url }}?editar=material#material" class="inline-flex items-center rounded-[10px] px-3 py-1.5 text-rotulo font-semibold text-escult-ink-2 hover:text-ava-tinta focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">Fechar</a>
            </form>
        </div>
    @else
        <a href="{{ $url }}?editar=material&adicionar={{ $apos }}#{{ $ancora }}"
           class="group flex w-full items-center gap-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
            <span class="h-px flex-1 bg-ava-borda transition-colors group-hover:bg-ava-acao"></span>
            <span class="rounded-full border border-ava-borda bg-white px-2.5 py-0.5 text-sobretitulo uppercase text-escult-ink-2 transition-colors group-hover:border-ava-acao group-hover:text-ava-acao">
                + Adicionar<span class="sr-only"> trecho {{ $apos < 0 ? 'no começo' : 'depois do trecho '.($apos + 1) }}</span>
            </span>
            <span class="h-px flex-1 bg-ava-borda transition-colors group-hover:bg-ava-acao"></span>
        </a>
    @endif
</div>
