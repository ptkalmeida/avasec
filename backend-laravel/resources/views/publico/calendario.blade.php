{{--
    "Calendário de Aulas ao Vivo" — a última página pública a sair do React.

    Substitui `src/components/pages/CalendarioPage.tsx`. O cabeçalho vem do
    `SitePageContentService`; a agenda, dos encontros realmente marcados pelos
    gestores (`App\Support\AgendaAoVivo`). A página não inventa encontro: lista
    vazia diz que não há nada marcado, e diz por quê.

    A faixa colorida à esquerda do cartão diz o TIPO do evento — aula ao vivo em
    azul, webinar aberto em dourado. A etiqueta escrita continua no cartão: a
    cor nunca é a única pista (Documento D §2.13).
--}}
<x-layouts.publico ativo="Ajuda" :titulo="$pagina['titulo']" :descricao="$pagina['descricao']">
    <x-componentes.moldura-pagina
        :sobretitulo="$pagina['eyebrow']"
        :titulo="$pagina['titulo']"
        :descricao="$pagina['descricao']"
        centralizado
    >
        @if ($agenda === [])
            <div class="rounded-2xl border border-dashed border-ava-borda bg-ava-faixa/50 p-10 text-center">
                <div class="h-14 w-14 rounded-full bg-white text-ava-acao flex items-center justify-center mx-auto mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-7 w-7" aria-hidden="true">
                        <path d="M4.2 4.2A2 2 0 0 0 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 1.82-1.18"/>
                        <path d="M21 15.5V6a2 2 0 0 0-2-2H9.5"/><path d="M16 2v4"/><path d="M3 10h7"/>
                        <path d="M21 10h-5.5"/><path d="m2 2 20 20"/>
                    </svg>
                </div>
                <strong class="block text-rotulo font-bold text-ava-tinta">
                    Nenhum encontro agendado para os próximos {{ $dias }} dias.
                </strong>
                <span class="mt-1 block text-apoio text-escult-ink-2 max-w-md mx-auto leading-relaxed">
                    Esta agenda mostra apenas encontros realmente marcados pelos gestores dos cursos.
                    Assim que uma nova aula ao vivo for agendada, ela aparece aqui.
                </span>
            </div>
        @else
            <ul class="grid grid-cols-1 md:grid-cols-3 gap-6 text-left">
                @foreach ($agenda as $evento)
                    @php($ehWebinar = $evento['tipo'] === 'webinar')

                    <li @class([
                        'bg-white rounded-2xl p-5 border border-ava-borda shadow-3xs hover:shadow-md transition-all flex flex-col justify-between h-full border-l-4',
                        'border-l-ava-dourado' => $ehWebinar,
                        'border-l-ava-acao' => ! $ehWebinar,
                    ])>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center gap-2">
                                <span class="text-nota font-bold uppercase tracking-wide bg-ava-icone-fundo text-ava-tinta px-2.5 py-1 rounded-full inline-flex items-center gap-1.5">
                                    @if ($ehWebinar)
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3" aria-hidden="true">
                                            <circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>
                                        </svg>
                                        Webinar aberto
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3" aria-hidden="true">
                                            <path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/>
                                            <rect x="2" y="6" width="14" height="12" rx="2"/>
                                        </svg>
                                        Aula ao vivo
                                    @endif
                                </span>

                                <span class="text-apoio text-escult-ink-2 flex items-center gap-1 shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5" aria-hidden="true">
                                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                                    </svg>
                                    {{ \App\Support\AgendaAoVivo::hora($evento['quando']) }}
                                </span>
                            </div>

                            <div class="flex gap-4 items-start">
                                <div class="h-14 w-14 shrink-0 bg-white rounded-xl border border-ava-borda flex flex-col items-center justify-center">
                                    <span class="text-xl font-black text-ava-tinta leading-none">{{ \App\Support\AgendaAoVivo::dia($evento['quando']) }}</span>
                                    <span class="text-apoio text-escult-ink-2 font-extrabold">{{ \App\Support\AgendaAoVivo::mes($evento['quando']) }}</span>
                                </div>
                                <div class="space-y-1">
                                    <strong class="text-rotulo font-bold text-ava-tinta line-clamp-2 leading-snug block">{{ $evento['titulo'] }}</strong>
                                    <span class="text-rotulo text-escult-ink-2 block">{{ $evento['contexto'] }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 mt-4 border-t border-ava-borda flex items-center justify-between gap-2">
                            <span class="text-sobretitulo text-escult-ink-2 uppercase">
                                {{ \App\Support\AgendaAoVivo::distanciaEmDias($evento['quando'], $agora) }}@if ($evento['duracaoMinutos'] !== null) · {{ $evento['duracaoMinutos'] }} min @endif
                            </span>

                            {{--
                                Webinar aberto é evento público e o link é o convite
                                dele: quem está autenticado vai direto para a sala
                                que a coordenação cadastrou.

                                Aula ao vivo NÃO ganha link aqui. O `link_reuniao` é a
                                chave da sala de uma turma, o catálogo anônimo já vem
                                sem ele (ISO-01), e quem está matriculado entra pelo
                                painel do curso.

                                `SafeUrl::sanitize` filtra `javascript:` e afins e devolve null
                                para o "#" dos webinars antigos — nesse caso a pessoa
                                vê o caminho do painel, e não um link morto.
                            --}}
                            @php($sala = $ehWebinar && ! empty($usuarioLogado) ? \App\Support\SafeUrl::sanitize($evento['link']) : null)

                            @if ($sala !== null)
                                <a href="{{ $sala }}" target="_blank" rel="noreferrer noopener"
                                   class="text-sobretitulo uppercase text-ava-acao hover:text-ava-acao-escuro flex items-center gap-1 shrink-0">
                                    <span>Acessar sala</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5" aria-hidden="true">
                                        <path d="M15 3h6v6"/><path d="M10 14 21 3"/>
                                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                    </svg>
                                </a>
                            @else
                                {{--
                                    Sem link, o destino honesto é onde a sala realmente
                                    fica: o painel de quem está matriculado, ou a porta
                                    de entrada para quem ainda não entrou. No React
                                    isto era um botão que só falava uma frase em voz
                                    alta — quem navega sem narração não recebia resposta
                                    nenhuma ao clicar.
                                --}}
                                <a href="{{ empty($usuarioLogado) ? '/entrar' : '/aluno' }}"
                                   class="text-sobretitulo uppercase text-ava-acao hover:text-ava-acao-escuro flex items-center gap-1 shrink-0">
                                    <span>{{ empty($usuarioLogado) ? 'Participar' : 'Ir ao painel' }}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5" aria-hidden="true">
                                        <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
                                    </svg>
                                </a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            <p class="mt-6 text-center text-rotulo text-escult-ink-2">
                {{ count($agenda) }} {{ count($agenda) === 1 ? 'encontro' : 'encontros' }} nos próximos {{ $dias }} dias.
                O link de acesso fica disponível no painel de quem está matriculado.
            </p>
        @endif
    </x-componentes.moldura-pagina>
</x-layouts.publico>
