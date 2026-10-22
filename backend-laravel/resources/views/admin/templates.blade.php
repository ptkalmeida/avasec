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
                 contexto="Cabeçalho, assinaturas e rodapé dos documentos emitidos.">

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

    @if ($tipo === 'historico')
        <div class="mb-4 flex items-start gap-2 rounded-[10px] border border-amber-100 bg-amber-50 px-3 py-2 text-sm text-amber-700">
            <i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
            <span>O histórico escolar ainda não tem emissão de PDF própria na plataforma — este modelo fica pronto e salvo para quando essa emissão for construída, mas por enquanto não afeta nenhum documento gerado.</span>
        </div>
    @endif

    <form method="POST" action="/admin/templates/{{ $tipo }}" class="max-w-3xl space-y-5 rounded-[10px] border border-slate-200 bg-white p-5 shadow-card">
        @csrf
        {{-- O primeiro botão do formulário é o que a tecla Enter aciona: tem de ser o de salvar, e não o de remover. --}}
        <button type="submit" name="acao" value="salvar" class="sr-only" tabindex="-1" aria-hidden="true">Salvar modelo</button>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-[0.875rem] font-bold text-slate-900">{{ $tipos[$tipo] }}</h2>
            @if ($tipo === 'certificado')
                <a href="/api/document-templates/certificado/preview" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                    <i class="fa-regular fa-eye" aria-hidden="true"></i> Visualizar documento atual
                </a>
            @endif
        </div>
        @if ($tipo === 'certificado')
            <p class="-mt-3 text-xs text-slate-500">A pré-visualização mostra o modelo JÁ SALVO, com dados de exemplo, em outra aba.</p>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-[10px] border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">
                @foreach ($errors->all() as $erro)
                    <p>{{ $erro }}</p>
                @endforeach
            </div>
        @endif

        <div>
            <label for="instituicao" class="{{ $rotulo }}">Nome da instituição</label>
            <input id="instituicao" name="institutionName" type="text" maxlength="191" value="{{ $instituicao }}" class="{{ $campo }}">
        </div>

        <div>
            <label for="rodape" class="{{ $rotulo }}">Texto de rodapé</label>
            <textarea id="rodape" name="footerText" rows="3" maxlength="2000" class="{{ $campo }}">{{ $rodape }}</textarea>
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
</x-layouts.admin>
