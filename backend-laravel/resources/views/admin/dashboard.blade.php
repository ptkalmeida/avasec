{{--
    `/admin` e `/admin/relatorio/{aba}` — Dashboard e Relatórios.

    Só leitura. As abas são links (cada uma tem endereço), e a impressão usa a
    própria impressora do navegador: a moldura some no `@media print`.
--}}
@php
    $situacaoDaConta = [
        'active' => ['verde', 'Ativo'],
        'blocked' => ['vermelho', 'Bloqueado'],
        'pending_confirmation' => ['ambar', 'Pendente'],
    ];
    $visibilidade = [
        1 => ['verde', 'Publicado'],
        2 => ['ambar', 'Restrito'],
        3 => ['neutro', 'Rascunho'],
    ];
    $base = $sub === 'consolidado' ? '/admin' : '/admin/relatorio/'.$sub;
    $cabecalhoDeColuna = 'px-3 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-500';
@endphp
<x-layouts.admin titulo="Dashboard & Relatórios" ativo="analytics"
                 contexto="Indicadores da rede e relatórios de acompanhamento.">

    <nav aria-label="Relatórios" class="mb-4 flex gap-1 overflow-x-auto rounded-[10px] border border-slate-200 bg-white p-1.5 print:hidden">
        @foreach ($abas as $chave => $rotulo)
            @php($destino = $chave === 'consolidado' ? '/admin' : '/admin/relatorio/'.$chave)
            <a href="{{ $destino }}"
               @if ($chave === $sub) aria-current="page" @endif
               class="flex-1 whitespace-nowrap rounded-md px-4 py-2 text-center text-[0.85rem] font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary
                      {{ $chave === $sub ? 'bg-primary/10 text-primary' : 'text-slate-600 hover:bg-slate-50' }}">
                {{ $rotulo }}
            </a>
        @endforeach
    </nav>

    <section aria-labelledby="titulo-relatorio" class="space-y-4 rounded-[10px] border border-slate-200 bg-white p-4 shadow-card lg:p-5">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-3">
            <h2 id="titulo-relatorio" class="text-[0.875rem] font-bold text-slate-900">
                Relatório: {{ $abas[$sub] }}
            </h2>
            <button type="button" x-data @click="window.print()"
                    class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary print:hidden">
                <i class="fa-solid fa-print" aria-hidden="true"></i> Imprimir relatório
            </button>
        </div>

        @if ($sub === 'consolidado')
            @php($r = $dados['resumo'])
            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
                <x-admin.indicador icone="fa-user-graduate" :valor="$r['alunos']" rotulo="Alunos cadastrados" />
                <x-admin.indicador icone="fa-book-open" :valor="$r['cursosPublicados']" rotulo="Cursos publicados"
                    :nota="$r['cursos'] > $r['cursosPublicados'] ? ($r['cursos'] - $r['cursosPublicados']).' em rascunho ou restritos' : null" />
                <x-admin.indicador icone="fa-clipboard-list" :valor="$r['matriculas']" rotulo="Matrículas ativas" />
                <x-admin.indicador icone="fa-certificate" :valor="$r['certificados']" rotulo="Certificados emitidos" />
            </div>
            <div class="grid grid-cols-1 gap-3.5 border-t border-slate-200 pt-4 sm:grid-cols-3">
                <x-admin.indicador icone="fa-user-graduate" :valor="$r['alunos']" rotulo="Alunos" nota="Total no sistema" />
                <x-admin.indicador icone="fa-book-open" :valor="$r['cursos']" rotulo="Cursos" nota="Em qualquer visibilidade" />
                <x-admin.indicador icone="fa-chalkboard-user" :valor="$r['equipe']" rotulo="Equipe pedagógica" nota="Gestores de curso" />
            </div>

        @elseif ($sub === 'alunos')
            <div class="grid grid-cols-1 gap-4 rounded-[10px] border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
                <div>
                    <p class="{{ $cabecalhoDeColuna }} !px-0 !py-0">Total de alunos</p>
                    <p class="text-xl font-extrabold text-slate-900">{{ $dados['total'] }} {{ $dados['total'] === 1 ? 'aluno' : 'alunos' }}</p>
                </div>
                <div>
                    <p class="{{ $cabecalhoDeColuna }} !px-0 !py-0">Certificados emitidos</p>
                    <p class="text-xl font-extrabold text-emerald-600">{{ $dados['certificados'] }}</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[0.85rem]">
                    <thead>
                        <tr class="bg-[#FAFAFA] text-left">
                            <th scope="col" class="{{ $cabecalhoDeColuna }}">Nome</th>
                            <th scope="col" class="{{ $cabecalhoDeColuna }}">E-mail</th>
                            <th scope="col" class="{{ $cabecalhoDeColuna }} text-center">Matrículas</th>
                            <th scope="col" class="{{ $cabecalhoDeColuna }} text-center">Progresso médio</th>
                            <th scope="col" class="{{ $cabecalhoDeColuna }} text-center">Situação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($dados['linhas'] as $l)
                            @php([$tom, $texto] = $situacaoDaConta[$l['situacao']] ?? ['neutro', $l['situacao']])
                            <tr>
                                <td class="px-3 py-2 font-bold text-slate-900">{{ $l['nome'] }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $l['email'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-center font-semibold text-slate-700">{{ $l['matriculas'] }}</td>
                                <td class="px-3 py-2 text-center font-semibold text-primary">{{ $l['progresso'] }}%</td>
                                <td class="px-3 py-2 text-center"><x-admin.selo :tom="$tom">{{ $texto }}</x-admin.selo></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">Nenhum aluno cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @elseif ($sub === 'professores')
            <div class="rounded-[10px] border border-slate-200 bg-slate-50 p-4">
                <p class="{{ $cabecalhoDeColuna }} !px-0 !py-0">Equipe pedagógica</p>
                <p class="text-lg font-extrabold text-slate-900">
                    {{ count($dados['linhas']) }} {{ count($dados['linhas']) === 1 ? 'gestor de curso' : 'gestores de curso' }}
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[0.85rem]">
                    <thead>
                        <tr class="bg-[#FAFAFA] text-left">
                            <th scope="col" class="{{ $cabecalhoDeColuna }}">Nome</th>
                            <th scope="col" class="{{ $cabecalhoDeColuna }}">Perfil</th>
                            <th scope="col" class="{{ $cabecalhoDeColuna }} text-center">Cursos vinculados</th>
                            <th scope="col" class="{{ $cabecalhoDeColuna }} text-center">Aulas</th>
                            <th scope="col" class="{{ $cabecalhoDeColuna }} text-center">Situação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($dados['linhas'] as $l)
                            @php([$tom, $texto] = $situacaoDaConta[$l['situacao']] ?? ['neutro', $l['situacao']])
                            <tr>
                                <td class="px-3 py-2 font-bold text-slate-900">{{ $l['nome'] }}</td>
                                <td class="px-3 py-2"><x-admin.selo tom="azul">Gestor de curso</x-admin.selo></td>
                                <td class="px-3 py-2 text-center font-semibold text-primary">{{ $l['cursos'] }}</td>
                                <td class="px-3 py-2 text-center text-slate-600">{{ $l['aulas'] }}</td>
                                <td class="px-3 py-2 text-center"><x-admin.selo :tom="$tom">{{ $texto }}</x-admin.selo></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">Nenhum gestor cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @elseif ($sub === 'cursos')
            <div class="overflow-x-auto">
                <table class="w-full text-[0.85rem]">
                    <thead>
                        <tr class="bg-[#FAFAFA] text-left">
                            <th scope="col" class="{{ $cabecalhoDeColuna }}">Curso</th>
                            <th scope="col" class="{{ $cabecalhoDeColuna }}">Categoria/Eixo</th>
                            <th scope="col" class="{{ $cabecalhoDeColuna }} text-center">Aulas</th>
                            <th scope="col" class="{{ $cabecalhoDeColuna }} text-center">Alunos matriculados</th>
                            <th scope="col" class="{{ $cabecalhoDeColuna }} text-center">Concluintes</th>
                            <th scope="col" class="{{ $cabecalhoDeColuna }} text-center">Progresso médio</th>
                            <th scope="col" class="{{ $cabecalhoDeColuna }} text-right">Visibilidade</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($dados['linhas'] as $l)
                            @php([$tom, $texto] = $visibilidade[$l['visibilidade']] ?? ['neutro', 'Sem nível'])
                            <tr>
                                <td class="px-3 py-2 font-bold text-slate-900">{{ $l['titulo'] }}</td>
                                <td class="px-3 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ $l['categoria'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-center">{{ $l['aulas'] }}</td>
                                <td class="px-3 py-2 text-center font-semibold text-slate-700">{{ $l['matriculados'] }}</td>
                                <td class="px-3 py-2 text-center font-semibold text-emerald-600">{{ $l['concluintes'] }}</td>
                                <td class="px-3 py-2 text-center font-semibold text-primary">{{ $l['progresso'] }}%</td>
                                <td class="px-3 py-2 text-right"><x-admin.selo :tom="$tom">{{ $texto }}</x-admin.selo></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-3 py-6 text-center text-slate-500">Nenhum curso cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @else
            <div class="overflow-x-auto">
                <table class="w-full text-[0.85rem]">
                    <thead>
                        <tr class="bg-[#FAFAFA] text-left">
                            <th scope="col" class="{{ $cabecalhoDeColuna }}">Aluno</th>
                            <th scope="col" class="{{ $cabecalhoDeColuna }}">Curso</th>
                            <th scope="col" class="{{ $cabecalhoDeColuna }} text-center">Data da matrícula</th>
                            <th scope="col" class="{{ $cabecalhoDeColuna }} text-center">Progresso</th>
                            <th scope="col" class="{{ $cabecalhoDeColuna }} text-right">Certificado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($dados['linhas'] as $l)
                            <tr>
                                <td class="px-3 py-2 font-bold text-slate-900">{{ $l['aluno'] }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $l['curso'] }}</td>
                                <td class="px-3 py-2 text-center text-slate-600">{{ $l['matriculadoEm'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-center font-semibold text-primary">{{ $l['progresso'] }}%</td>
                                <td class="px-3 py-2 text-right">
                                    @if ($l['certificado'])
                                        <x-admin.selo tom="verde"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Emitido</x-admin.selo>
                                    @else
                                        <x-admin.selo tom="ambar">Pendente</x-admin.selo>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">Nenhuma matrícula ativa.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if ($ultimaPagina > 1)
            <nav aria-label="Páginas" class="flex items-center justify-between border-t border-slate-200 pt-3 text-[0.85rem] text-slate-600 print:hidden">
                <span>Página {{ $pagina }} de {{ $ultimaPagina }}</span>
                <span class="flex gap-2">
                    @if ($pagina > 1)
                        <a href="{{ $base }}?pagina={{ $pagina - 1 }}" class="rounded-md border border-slate-200 px-3 py-1.5 font-medium hover:bg-slate-50">Anterior</a>
                    @endif
                    @if ($pagina < $ultimaPagina)
                        <a href="{{ $base }}?pagina={{ $pagina + 1 }}" class="rounded-md border border-slate-200 px-3 py-1.5 font-medium hover:bg-slate-50">Próxima</a>
                    @endif
                </span>
            </nav>
        @endif
    </section>
</x-layouts.admin>
