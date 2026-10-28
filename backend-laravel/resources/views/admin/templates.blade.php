{{--
    `/admin/templates` — cabeçalho, assinaturas e rodapé dos documentos emitidos.

    O tipo (certificado ou histórico) está no endereço. Acrescentar e remover
    assinatura devolvem a tela com a lista mudada, sem gravar: só "Salvar modelo"
    grava.
--}}
@php
    $campo = 'w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-slate-900 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30';
    $rotulo = 'mb-1 block text-[0.7rem] font-bold uppercase tracking-[0.06em] text-slate-500';
@endphp
<x-layouts.admin titulo="Modelos de Certificado e Histórico" ativo="templates"
                 contexto="Título, texto, logo, assinaturas e rodapé dos documentos emitidos.">

    <nav aria-label="Tipo de documento" class="mb-4 flex flex-wrap gap-1 rounded-[10px] border border-slate-200 bg-white p-1.5">
        @foreach ($tipos as $chave => $rotuloDoTipo)
            <a href="/admin/templates?tipo={{ $chave }}"
               @if ($chave === $tipo) aria-current="page" @endif
               class="whitespace-nowrap rounded-md px-4 py-2 text-[0.85rem] font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary
                      {{ $chave === $tipo ? 'bg-primary/10 text-primary' : 'text-slate-600 hover:bg-slate-50' }}">
                {{ $rotuloDoTipo }}
            </a>
        @endforeach
    </nav>

    @if (session('avisoDeModelo'))
        <div role="status" class="mb-4 flex items-center gap-2 rounded-[10px] border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ session('avisoDeModelo') }}
        </div>
    @endif

    <form method="POST" action="/admin/templates/{{ $tipo }}" x-data class="max-w-3xl space-y-5 rounded-[10px] border border-slate-200 bg-white p-5 shadow-card">
        @csrf
        {{-- O primeiro botão do formulário é o que a tecla Enter aciona: tem de ser o de salvar, e não o de remover. --}}
        <button type="submit" name="acao" value="salvar" class="sr-only" tabindex="-1" aria-hidden="true">Salvar modelo</button>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-[0.875rem] font-bold text-slate-900">{{ $tipos[$tipo] }}</h2>
            {{-- A prévia envia o que está na tela (sem gravar) e abre o PDF em outra aba. --}}
            <button type="submit" formaction="/admin/templates/{{ $tipo }}/previa" formtarget="_blank" formnovalidate
                    class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                <i class="fa-regular fa-eye" aria-hidden="true"></i> Pré-visualizar
            </button>
        </div>
        <p class="-mt-3 text-xs text-slate-500">A pré-visualização mostra o que está na tela, com dados de exemplo, em outra aba — sem salvar.</p>

        @if ($errors->any())
            <div role="alert" class="rounded-[10px] border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">
                @foreach ($errors->all() as $erro)
                    <p>{{ $erro }}</p>
                @endforeach
            </div>
        @endif

        <div>
            <label for="instituicao" class="{{ $rotulo }}">Nome da instituição</label>
            <input id="instituicao" name="nome_instituicao" type="text" maxlength="191" value="{{ $instituicao }}" class="{{ $campo }}">
        </div>

        <div>
            <label for="titulo" class="{{ $rotulo }}">Título do documento</label>
            <input id="titulo" name="titulo" type="text" maxlength="191" value="{{ $titulo }}" class="{{ $campo }}">
        </div>

        <div>
            <label for="corpo" class="{{ $rotulo }}">Texto do corpo</label>
            <textarea id="corpo" x-ref="corpo" name="texto_corpo" rows="{{ $tipo === 'certificado' ? 9 : 3 }}" maxlength="3000" class="{{ $campo }}">{{ $corpo }}</textarea>
            <p class="mt-1 text-xs text-slate-500">Linha em branco separa parágrafos. Os campos automáticos são trocados pelos dados de cada aluno ao emitir.</p>
            <div class="mt-2 flex flex-wrap gap-1.5" role="group" aria-label="Campos automáticos">
                @foreach ($camposDoTipo as $chave => $descricao)
                    <button type="button" title="{{ $descricao }}"
                            @click="$refs.corpo.setRangeText(@js('{'.$chave.'}'), $refs.corpo.selectionStart, $refs.corpo.selectionEnd, 'end'); $refs.corpo.focus()"
                            class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1 font-mono text-xs text-slate-700 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                        {{ '{'.$chave.'}' }}
                    </button>
                @endforeach
            </div>
        </div>

        @if ($tipo === 'historico')
            <fieldset class="space-y-2">
                <legend class="{{ $rotulo }}">Nomes das colunas da tabela</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($rotulos as $chave => $nomeDaColuna)
                        <div>
                            <label for="coluna-{{ $chave }}" class="sr-only">Nome da coluna {{ $chave }}</label>
                            <input id="coluna-{{ $chave }}" name="rotulos_colunas[{{ $chave }}]" type="text" maxlength="60" value="{{ $nomeDaColuna }}" class="{{ $campo }}">
                        </div>
                    @endforeach
                </div>
            </fieldset>
        @endif

        <div>
            <label for="rodape" class="{{ $rotulo }}">Texto de rodapé</label>
            <textarea id="rodape" name="texto_rodape" rows="3" maxlength="2000" class="{{ $campo }}">{{ $rodape }}</textarea>
        </div>

        <fieldset class="space-y-2">
            <legend class="{{ $rotulo }}">Assinaturas ({{ count($assinaturas) }} de {{ $maxAssinaturas }})</legend>

            @forelse ($assinaturas as $i => $a)
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <label class="sr-only" for="assinatura-nome-{{ $i }}">Nome da assinatura {{ $i + 1 }}</label>
                    <input id="assinatura-nome-{{ $i }}" name="assinaturas[{{ $i }}][name]" type="text" maxlength="191" value="{{ $a['name'] }}" placeholder="Nome" class="{{ $campo }} flex-1">
                    <label class="sr-only" for="assinatura-cargo-{{ $i }}">Cargo da assinatura {{ $i + 1 }}</label>
                    <input id="assinatura-cargo-{{ $i }}" name="assinaturas[{{ $i }}][role]" type="text" maxlength="191" value="{{ $a['role'] }}" placeholder="Cargo" class="{{ $campo }} flex-1">
                    <button type="submit" name="acao" value="remover:{{ $i }}" formnovalidate
                            class="shrink-0 rounded-md border border-slate-200 p-2 text-red-500 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300"
                            title="Remover a assinatura {{ $i + 1 }}">
                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                        <span class="sr-only">Remover a assinatura {{ $i + 1 }}</span>
                    </button>
                </div>
            @empty
                <p class="text-xs italic text-slate-500">Nenhuma assinatura neste modelo.</p>
            @endforelse

            <button type="submit" name="acao" value="adicionar" formnovalidate
                    class="inline-flex items-center gap-1.5 rounded-md px-1 py-1 text-[0.85rem] font-semibold text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Adicionar assinatura
            </button>
        </fieldset>

        <div class="flex items-center justify-between gap-3 border-t border-slate-200 pt-4">
            <p class="text-xs text-slate-500">
                @if ($atualizadoEm !== null)
                    Última alteração: {{ \Carbon\CarbonImmutable::parse($atualizadoEm)->setTimezone(\App\Support\Fuso::exibicao())->format('d/m/Y H:i') }}
                @else
                    Ainda não foi alterado: valem os textos de fábrica.
                @endif
            </p>
            <button type="submit" name="acao" value="salvar"
                    class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Salvar modelo
            </button>
        </div>
    </form>

    {{-- Logo: formulário próprio (envio de arquivo); fora do principal porque formulário não aninha. --}}
    <section class="mt-5 max-w-3xl space-y-3 rounded-[10px] border border-slate-200 bg-white p-5 shadow-card" aria-labelledby="logo-titulo">
        <h2 id="logo-titulo" class="text-[0.875rem] font-bold text-slate-900">Logo da instituição</h2>
        @if ($logo)
            <img src="{{ $logo }}" alt="Logo atual do documento" class="max-h-16 max-w-[220px] rounded border border-slate-200 p-1">
        @else
            <p class="text-xs italic text-slate-500">Nenhum logo: o documento sai só com o nome da instituição.</p>
        @endif
        <form method="POST" action="/admin/templates/{{ $tipo }}/logo" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
            @csrf
            <label for="logo" class="sr-only">Arquivo do logo (PNG ou JPEG, até 2 MB)</label>
            <input id="logo" name="logo" type="file" accept="image/png,image/jpeg" required class="text-sm text-slate-700">
            <button type="submit" class="rounded-md border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">Enviar logo</button>
        </form>
        @if ($logo)
            <form method="POST" action="/admin/templates/{{ $tipo }}/logo/tirar">
                @csrf
                <button type="submit" class="text-[0.85rem] font-semibold text-red-600 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300">Tirar o logo deste modelo</button>
            </form>
        @endif
        <p class="text-xs text-slate-500">PNG ou JPEG, até 2 MB. Ao enviar, o logo já vale para os próximos documentos; a pré-visualização mostra o logo salvo.</p>
    </section>

    <x-admin.versoes :versoes="$versoes" :acao="'/admin/templates/'.$tipo.'/versoes'" oque="modelo" />
</x-layouts.admin>
