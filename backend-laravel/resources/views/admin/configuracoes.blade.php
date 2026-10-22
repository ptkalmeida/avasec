{{--
    `/admin/configuracoes` — parâmetros do sistema.

    Um formulário só, com um botão que grava de verdade. Cada interruptor é uma
    caixa de marcar (funciona sem JavaScript e é lida por leitor de tela).
--}}
@php
    $interruptores = [
        ['allowDirectMessages', 'Habilitar mensagens diretas (DMs)', 'Permitir que alunos enviem mensagens diretas para professores e administradores.', true],
        ['allowGlobalChat', 'Habilitar chat global', 'Exibe uma sala de bate-papo global onde todos da instituição podem conversar livremente.', false],
        ['openEnrollment', 'Inscrições abertas para novos ciclos', 'Quando ativado, os alunos podem buscar e se matricular em novos cursos no catálogo.', false],
        ['autoCertify', 'Emissão automática de certificados', 'Permitir que o visualizador de documentos emita os certificados automaticamente ao atingir os requisitos.', false],
        ['liveClassRecording', 'Gravação automática de aulas ao vivo', 'Gravar automaticamente os encontros síncronos e arquivar no curso.', false],
    ];
@endphp
<x-layouts.admin titulo="Configurações" ativo="settings"
                 contexto="Parâmetros letivos, incluindo a exigência de presença para o certificado.">

    @if (session('avisoDeConfiguracoes'))
        <div role="status" class="mb-4 flex items-center gap-2 rounded-[10px] border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ session('avisoDeConfiguracoes') }}
        </div>
    @endif

    <form method="POST" action="/admin/configuracoes" class="max-w-2xl space-y-5 rounded-[10px] border border-slate-200 bg-white p-5 shadow-card">
        @csrf

        <div>
            <h2 class="text-[0.875rem] font-bold text-slate-900">Permissões e funcionalidades globais</h2>
            <p class="mt-0.5 text-xs text-slate-500">Estas configurações valem para toda a plataforma.</p>
        </div>

        @if ($errors->any())
            <div role="alert" class="rounded-[10px] border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">
                @foreach ($errors->all() as $erro)
                    <p>{{ $erro }}</p>
                @endforeach
            </div>
        @endif

        <ul class="space-y-4">
            @foreach ($interruptores as [$chave, $titulo, $descricao, $temEfeito])
                <li>
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" name="{{ $chave }}" value="1" @checked($valores[$chave])
                               class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-primary focus:ring-primary">
                        <span>
                            <span class="block text-[0.85rem] font-bold text-slate-800">{{ $titulo }}</span>
                            <span class="block text-xs text-slate-500">{{ $descricao }}</span>
                            @unless ($temEfeito)
                                <span class="mt-1 inline-block text-[0.7rem] italic text-slate-400">Registrada; nenhuma tela da plataforma a consulta ainda.</span>
                            @endunless
                        </span>
                    </label>
                </li>
            @endforeach
        </ul>

        <div class="border-t border-slate-200 pt-4">
            <label for="arquivamento" class="block text-[0.85rem] font-bold text-slate-800">Duração do arquivamento automático (cursos)</label>
            <select id="arquivamento" name="autoArchiveDuration"
                    class="mt-1 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-slate-900 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                @foreach ($opcoesDeArquivamento as $valor => $texto)
                    <option value="{{ $valor }}" @selected($valores['autoArchiveDuration'] === $valor)>{{ $texto }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">Defina quando turmas inativas devem ser arquivadas do catálogo.</p>
            <p class="mt-1 text-[0.7rem] italic text-slate-400">Registrada; nenhuma tela da plataforma a consulta ainda.</p>
        </div>

        <div class="rounded-[10px] border border-dashed border-slate-200 bg-slate-50 p-4 text-xs text-slate-600">
            <strong class="mb-1 block text-slate-800">Sobre a presença mínima para o certificado</strong>
            A frequência mínima é definida em cada curso, na tela de gestão do curso
            (<a href="/inst" class="font-semibold text-primary hover:underline">Meus cursos</a> → Gestão do curso → Propriedades).
        </div>

        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Salvar configurações
        </button>
    </form>
</x-layouts.admin>
