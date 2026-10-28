{{--
    "Certificados e Emissão" — os critérios e o autenticador público.

    Substitui o trecho `currentView === 'certificados'` do `src/App.tsx`. O
    conteúdo editável vem do mesmo `SitePageContentService`; a consulta, do
    mesmo `CertificateService` que a API já usa.

    O formulário é GET: o resultado fica no endereço, que é o que o QR impresso
    no certificado já carrega (`/?verify=<hash>`, redirecionado para cá). Assim
    recarregar a página, mandar o link adiante ou usar o "voltar" do navegador
    devolvem a mesma resposta, sem refazer um POST.
--}}
<x-layouts.publico ativo="Certificados" :titulo="$pagina['titulo']" :descricao="$pagina['descricao']">
    <x-componentes.moldura-pagina
        :sobretitulo="$pagina['eyebrow']"
        :titulo="$pagina['titulo']"
        :descricao="$pagina['descricao']"
        centralizado
    >
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start text-left">
            {{-- Coluna da esquerda: o que é preciso cumprir. --}}
            <div class="lg:col-span-6 space-y-6">
                <div class="bg-white p-6 rounded-2xl border border-ava-borda shadow-3xs space-y-4">
                    <h2 class="text-cartao text-ava-tinta font-titulo flex items-center gap-2.5">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             class="h-5 w-5 text-ava-acao shrink-0" aria-hidden="true">
                            <path d="m15.477 12.89 1.515 8.526a.5.5 0 0 1-.81.47l-3.58-2.687a1 1 0 0 0-1.197 0l-3.586 2.686a.5.5 0 0 1-.81-.469l1.514-8.526"/>
                            <circle cx="12" cy="8" r="6"/>
                        </svg>
                        <span>{{ $pagina['criteriaTitle'] }}</span>
                    </h2>

                    <p class="text-apoio text-escult-ink-2 leading-relaxed">{{ $pagina['criteriaIntro'] }}</p>

                    <ul class="space-y-4 pt-2">
                        @foreach ($pagina['items'] as $criterio)
                            <li class="flex gap-3 items-start">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                     class="h-4 w-4 mt-1 text-ava-acao shrink-0" aria-hidden="true">
                                    <path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/>
                                </svg>
                                <span class="text-apoio">
                                    <strong class="text-ava-tinta block">{{ $criterio['titulo'] }}</strong>
                                    <span class="text-escult-ink-2 text-rotulo leading-normal block">{{ $criterio['descricao'] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="bg-ava-faixa border border-ava-borda p-5 rounded-2xl flex gap-3.5 items-start">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         class="h-6 w-6 text-ava-marinho shrink-0 mt-0.5" aria-hidden="true">
                        <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>
                        <path d="m9 12 2 2 4-4"/>
                    </svg>
                    <p class="text-rotulo text-ava-tinta leading-relaxed">{{ $pagina['noticeText'] }}</p>
                </div>
            </div>

            {{-- Coluna da direita: o autenticador. --}}
            <div class="lg:col-span-6 bg-white p-6 rounded-2xl border border-ava-borda shadow-3xs space-y-6">
                <div class="space-y-1.5">
                    <h2 class="text-cartao text-ava-tinta font-titulo flex items-center gap-2.5">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             class="h-5 w-5 text-ava-acao shrink-0" aria-hidden="true">
                            <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>
                            <path d="m9 12 2 2 4-4"/>
                        </svg>
                        <span>{{ $pagina['authenticatorTitle'] }}</span>
                    </h2>
                    <p class="text-apoio text-escult-ink-2">{{ $pagina['authenticatorDescription'] }}</p>
                </div>

                {{--
                    GET, e sem `@csrf`: a consulta não altera nada e o token numa
                    URL de consulta seria copiado junto quando alguém mandasse o
                    endereço adiante.
                --}}
                <form method="GET" action="/certificados" class="flex gap-2.5" role="search">
                    <label for="verify" class="sr-only">Código de validação ou nome completo do aluno</label>
                    <input type="text" id="verify" name="verify" value="{{ $termo }}"
                           placeholder="Ex.: CERT-JOAO-123 ou João Silva"
                           autocomplete="off"
                           class="flex-1 min-w-0 bg-white border border-ava-borda text-ava-tinta rounded-[10px] px-4 py-3 text-apoio font-medium focus:outline-none focus:ring-2 focus:ring-ava-acao/30 focus:border-ava-acao placeholder:text-escult-ink-3">
                    <button type="submit"
                            class="rounded-[10px] bg-ava-acao hover:bg-ava-acao-escuro text-white text-rotulo font-semibold px-5 py-3 transition-all shadow-xs cursor-pointer shrink-0">
                        Verificar
                    </button>
                </form>

                @if ($consultou)
                    {{--
                        `aria-live`: o resultado chega numa página nova, mas o foco
                        continua no formulário, e quem usa leitor de tela precisa
                        ouvir que a resposta apareceu.
                    --}}
                    <div class="pt-4 border-t border-ava-borda font-sans" aria-live="polite">
                        @if ($certificado === null)
                            <div class="bg-rose-50 border border-rose-200 rounded-2xl p-4 flex gap-3 items-start">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                     class="h-5 w-5 text-rose-600 shrink-0 mt-0.5" aria-hidden="true">
                                    <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                                </svg>
                                <div class="text-apoio space-y-1">
                                    <strong class="text-rose-900 uppercase block tracking-wider font-extrabold">Código não encontrado</strong>
                                    <p class="text-rose-700 leading-relaxed">
                                        Nenhum registro correspondente ao termo "{{ $termo }}" foi encontrado.
                                        Confira a grafia do nome ou o código de verificação impresso no documento.
                                    </p>
                                </div>
                            </div>
                        @else
                            {{--
                                O certificado REVOGADO aparece, e é por isso que a
                                consulta pública existe: sumir com ele responderia
                                "não existe" sobre um papel que está impresso e pode
                                estar anexado a um processo. Quem confere precisa da
                                resposta "foi revogado", que é outra coisa (ADR 12).

                                A cor não é a única pista: o selo é escrito, e o
                                motivo e a data vêm por extenso.
                            --}}
                            <div @class([
                                'rounded-2xl border p-4 space-y-3.5',
                                'bg-rose-50 border-rose-200' => $certificado['revogado'],
                                'bg-emerald-50 border-emerald-200' => ! $certificado['revogado'],
                            ])>
                                @if ($certificado['revogado'])
                                    <p class="flex items-center gap-2.5 text-rose-800">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                             class="h-5 w-5 text-rose-600 shrink-0" aria-hidden="true">
                                            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/>
                                            <path d="M12 9v4"/><path d="M12 17h.01"/>
                                        </svg>
                                        <strong class="text-sobretitulo uppercase">Certificado revogado — sem validade</strong>
                                    </p>

                                    <div class="space-y-1 rounded-lg border border-rose-100 bg-white/60 p-2.5 text-rotulo leading-relaxed text-rose-900">
                                        {{--
                                            A data entra por expressão, e não por
                                            `@ if` no meio da frase: diretiva colada
                                            em texto corrido não é reconhecida como
                                            diretiva, o fechamento fica órfão e a
                                            view inteira deixa de compilar.
                                        --}}
                                        <p class="font-bold">
                                            Este documento foi revogado pela instituição{{ empty($certificado['revogadoEm']) ? '' : ' em '.$certificado['revogadoEm'] }}
                                            e não comprova conclusão de curso.
                                        </p>
                                        @if (! empty($certificado['motivoRevogacao']))
                                            <p class="font-medium text-rose-700">Motivo registrado: {{ $certificado['motivoRevogacao'] }}</p>
                                        @endif
                                        <p class="text-rose-700">
                                            Os dados abaixo são os do documento como foi emitido, para conferência com o papel em mãos.
                                        </p>
                                    </div>
                                @else
                                    <p class="flex items-center gap-2.5 text-emerald-800">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                             class="h-5 w-5 text-emerald-600 shrink-0" aria-hidden="true">
                                            <path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/>
                                        </svg>
                                        <strong class="text-sobretitulo uppercase">Certificado válido e homologado</strong>
                                    </p>
                                @endif

                                <dl @class([
                                    'grid grid-cols-2 gap-3 text-apoio leading-relaxed border-t pt-3',
                                    'border-rose-100' => $certificado['revogado'],
                                    'border-emerald-100' => ! $certificado['revogado'],
                                ])>
                                    <div>
                                        <dt class="text-escult-ink-2 block text-sobretitulo uppercase">Aluno</dt>
                                        <dd class="text-ava-tinta font-bold block">{{ $certificado['nome_aluno'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-escult-ink-2 block text-sobretitulo uppercase">Curso</dt>
                                        <dd class="text-ava-tinta font-bold block">{{ $certificado['titulo_curso'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-escult-ink-2 block text-sobretitulo uppercase">Data de emissão</dt>
                                        <dd class="text-ava-tinta font-medium block">{{ $certificado['data_emissao'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-escult-ink-2 block text-sobretitulo uppercase">Registro de autenticidade</dt>
                                        <dd class="text-ava-tinta font-bold block select-all break-words">{{ $certificado['hash_verificacao'] }}</dd>
                                    </div>
                                </dl>

                                @if (! $certificado['revogado'])
                                    <p class="text-apoio text-emerald-700 leading-normal font-medium bg-white/50 p-2.5 rounded-lg border border-emerald-100/50">
                                        Certificado emitido em conformidade com as diretrizes do AVA da Escola Estadual
                                        da Cultura RJ. Registro de presença homologado: {{ $certificado['percentual_frequencia'] }}%.
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </x-componentes.moldura-pagina>
</x-layouts.publico>
