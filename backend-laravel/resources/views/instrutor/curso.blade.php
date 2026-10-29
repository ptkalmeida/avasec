{{--
    `/inst/curso/{ref}` — a Gestão do Curso.

    O que o curso é, o que tem dentro, e as duas alterações que cabem aqui:
    as informações do curso (editadas no próprio Panorama) e a vigência. Grade, avaliações e alunos têm tela própria.

    O que não veio do painel React: os webinars (fora do MVP, 21/10) e o botão
    "Acessar Sala", que não abria nada porque a sala interna está desligada.

    Os formulários abrem por `<details>`, e não por janela: formulário de edição
    dentro de modal fecha por clique fora e perde o que foi digitado — foi o que
    aconteceu com a prova no React, e a decisão de 19/10 foi tirar a prova do
    modal pelo mesmo motivo. Aberto ou fechado não é estado que precise
    sobreviver a um F5.
--}}
<x-layouts.instrutor :titulo="$curso['titulo']" ativo="gestao" :cursoDoMenu="$curso">

    {{-- O Panorama é lido e editado no mesmo lugar: `editando` troca a leitura pelo formulário.
         Erro de validação deixa o formulário aberto. --}}
    <div class="mx-auto max-w-4xl space-y-6"
         x-data="{ editando: {{ $errors->hasAny(['titulo', 'descricao', 'categoria', 'carga_horaria', 'frequencia_minima']) ? 'true' : 'false' }} }">

        <nav aria-label="Trilha de navegação" class="text-apoio text-escult-ink-3">
            <a href="/inst" class="underline-offset-[3px] hover:text-ava-acao hover:underline">Meus cursos</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <span class="text-ava-tinta">{{ $curso['titulo'] }}</span>
        </nav>

        @if (session('avisoDoCurso'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-apoio font-semibold text-emerald-800">
                {{ session('avisoDoCurso') }}
            </div>
        @endif

        <div class="space-y-2">
            @if (! empty($curso['categoria']))
                <p class="text-sobretitulo uppercase text-escult-ink-3">{{ $curso['categoria'] }}</p>
            @endif
            <h1 class="font-titulo text-3xl leading-tight tracking-tight text-ava-tinta break-words">{{ $curso['titulo'] }}</h1>
        </div>

        {{-- Vigência vencida: o que aconteceu e o que fazer, lado a lado. --}}
        @if ($vigenciaVencida)
            <section aria-labelledby="titulo-vigencia" class="space-y-3 rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <h2 id="titulo-vigencia" class="text-sobretitulo uppercase text-amber-900">Vigência de exibição encerrada</h2>
                <p class="text-apoio leading-relaxed text-amber-950">
                    A licença de exibição terminou em <strong>{{ $vigencia_ate }}</strong>. Enquanto isso durar, o curso
                    <strong>não é oferecido no catálogo</strong> e ninguém pode se matricular nele. Quem já está
                    matriculado continua com acesso.
                </p>
            </section>
        @endif

        {{-- Ambiente do aluno: ativar PUBLICA o curso, desativar o RESTRINGE (só gestor e admin veem).
             O curso criado pelo gestor nasce restrito (ADR 029). O estado vem por texto, não só por cor. --}}
        @php($publicado = (int) ($curso['status'] ?? 1) === \App\Support\Visibilidade::PUBLICADO)
        <section aria-labelledby="titulo-visibilidade"
                 class="space-y-3 rounded-2xl border p-5 shadow-3xs md:p-6 {{ $publicado ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="min-w-0 space-y-1">
                    <h2 id="titulo-visibilidade" class="text-sobretitulo uppercase {{ $publicado ? 'text-emerald-900' : 'text-amber-900' }}">
                        Ambiente do aluno: {{ $publicado ? 'ativo' : 'restrito' }}
                    </h2>
                    <p class="text-apoio leading-relaxed {{ $publicado ? 'text-emerald-950' : 'text-amber-950' }}">
                        @if ($publicado)
                            Os alunos veem este curso no catálogo e podem se matricular.
                        @else
                            Só você e o admin veem este curso. Os alunos não o encontram no catálogo.
                            @if (($aulas ?? 0) === 0)
                                Ele ainda não tem aulas: monte a grade antes de ativá-lo.
                            @endif
                        @endif
                    </p>
                </div>
                <form method="POST" action="/inst/curso/{{ $ref }}/visibilidade" class="shrink-0">
                    @csrf
                    <input type="hidden" name="acao" value="{{ $publicado ? 'desativar' : 'ativar' }}">
                    <button type="submit"
                            class="cursor-pointer rounded-[10px] px-5 py-2.5 text-rotulo font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2 {{ $publicado ? 'border border-ava-borda bg-white text-ava-tinta hover:bg-ava-faixa' : 'bg-ava-acao text-white hover:bg-ava-acao-escuro' }}">
                        {{ $publicado ? 'Desativar para os alunos' : 'Ativar para os alunos' }}
                    </button>
                </form>
            </div>
            @if ($errors->has('visibilidade'))
                <p role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-apoio font-semibold text-rose-800">{{ $errors->first('visibilidade') }}</p>
            @endif
        </section>

        {{-- Panorama --}}
        <section id="panorama" aria-labelledby="titulo-panorama" class="space-y-4 rounded-2xl border border-ava-borda bg-white p-5 shadow-3xs md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="titulo-panorama" class="font-titulo text-cartao text-ava-tinta">Panorama</h2>
                {{-- O texto do curso (descrição, frequência, certificado, carga horária...) se edita aqui mesmo:
                     o botão troca a leitura pelo formulário, e "Cancelar" volta à leitura. --}}
                <button type="button" @click="editando = ! editando" :aria-expanded="editando ? 'true' : 'false'"
                   class="shrink-0 rounded-[10px] border border-ava-borda px-3.5 py-2 text-apoio font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                       <span x-text="editando ? 'Fechar edição' : 'Editar informações do curso'">Editar informações do curso</span>
                   </button>
            </div>

            <div x-show="! editando" class="space-y-4">
                <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-apoio sm:grid-cols-3">
                    <div>
                        <dt class="text-escult-ink-2">Aulas</dt>
                        <dd class="font-semibold text-ava-tinta">{{ $aulas }}</dd>
                    </div>
                    <div>
                        <dt class="text-escult-ink-2">Avaliações</dt>
                        <dd class="font-semibold text-ava-tinta">{{ $avaliacoes }}</dd>
                    </div>
                    <div>
                        <dt class="text-escult-ink-2">Alunos</dt>
                        <dd class="font-semibold text-ava-tinta">{{ $alunos }}</dd>
                    </div>
                    <div>
                        <dt class="text-escult-ink-2">Frequência mínima</dt>
                        {{-- O número que a emissão usa. Quando o curso não define o
                             seu, vale o padrão da escola, e a tela diz qual é qual:
                             "70%" sem dizer de onde vem faz o gestor procurar no
                             curso um valor que não está lá. --}}
                        <dd class="font-semibold text-ava-tinta">
                            {{ $minimaEfetiva }}%
                            @if ($minimaDoCurso === null)
                                <span class="font-normal text-escult-ink-3">(padrão da escola)</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-escult-ink-2">Certificado</dt>
                        <dd class="font-semibold text-ava-tinta">{{ $emite_certificado ? 'Emite' : 'Não emite' }}</dd>
                    </div>
                    <div>
                        <dt class="text-escult-ink-2">Vigência</dt>
                        <dd class="font-semibold text-ava-tinta">{{ $vigencia_ate ?? 'Permanente' }}</dd>
                    </div>
                    @if (! empty($curso['carga_horaria']))
                        <div>
                            <dt class="text-escult-ink-2">Carga horária</dt>
                            <dd class="font-semibold text-ava-tinta">{{ $curso['carga_horaria'] }}h</dd>
                        </div>
                    @endif
                    @if (! empty($curso['modalidade']))
                        <div>
                            <dt class="text-escult-ink-2">Modalidade</dt>
                            <dd class="font-semibold text-ava-tinta break-words">{{ $curso['modalidade'] }}</dd>
                        </div>
                    @endif
                    @if (! empty($curso['nivel']))
                        <div>
                            <dt class="text-escult-ink-2">Nível</dt>
                            <dd class="font-semibold text-ava-tinta break-words">{{ $curso['nivel'] }}</dd>
                        </div>
                    @endif
                </dl>

                @if (! empty($curso['descricao']))
                    <div class="space-y-1 border-t border-ava-borda pt-4">
                        <h3 class="text-sobretitulo uppercase text-escult-ink-3">Descrição</h3>
                        <p class="whitespace-pre-line text-apoio leading-relaxed text-escult-ink-2 break-words">{{ $curso['descricao'] }}</p>
                    </div>
                @endif
            </div>

            <form method="POST" action="/inst/curso/{{ $ref }}/propriedades" x-show="editando" x-cloak class="space-y-4 border-t border-ava-borda pt-4">
                    @csrf

                    <div class="space-y-1.5">
                        <label for="titulo" class="block text-rotulo font-semibold text-ava-tinta">Título</label>
                        <input id="titulo" name="titulo" type="text" required minlength="3" maxlength="200"
                               value="{{ old('titulo', $curso['titulo']) }}"
                               @error('titulo') aria-invalid="true" aria-describedby="erro-title" @enderror
                               class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                        @error('titulo')
                            <p id="erro-title" class="text-apoio font-semibold text-rose-700">{{ $message }}</p>
                        @enderror
                        <p class="text-apoio text-escult-ink-3">Mudar o título muda o endereço do curso; o antigo continua levando até aqui.</p>
                    </div>

                    <div class="space-y-1.5">
                        <label for="categoria" class="block text-rotulo font-semibold text-ava-tinta">Categoria</label>
                        <input id="categoria" name="categoria" type="text" required maxlength="120"
                               value="{{ old('categoria', $curso['categoria']) }}"
                               class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                        @error('categoria')
                            <p class="text-apoio font-semibold text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="descricao" class="block text-rotulo font-semibold text-ava-tinta">Descrição</label>
                        <textarea id="descricao" name="descricao" rows="14" required minlength="10" maxlength="5000"
                                  class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-apoio text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">{{ old('descricao', $curso['descricao']) }}</textarea>
                        @error('descricao')
                            <p class="text-apoio font-semibold text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="carga_horaria" class="block text-rotulo font-semibold text-ava-tinta">Carga horária (horas)</label>
                            <input id="carga_horaria" name="carga_horaria" type="number" min="1" max="2000"
                                   value="{{ old('carga_horaria', $curso['carga_horaria']) }}"
                                   class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                            @error('carga_horaria')
                                <p class="text-apoio font-semibold text-rose-700">{{ $message }}</p>
                            @enderror
                            <p class="text-apoio text-escult-ink-3">Sai no certificado.</p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="frequencia_minima" class="block text-rotulo font-semibold text-ava-tinta">Frequência mínima (%)</label>
                            <input id="frequencia_minima" name="frequencia_minima" type="number" min="0" max="100"
                                   value="{{ old('frequencia_minima', $minimaDoCurso) }}"
                                   aria-describedby="dica-minima"
                                   class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                            @error('frequencia_minima')
                                <p class="text-apoio font-semibold text-rose-700">{{ $message }}</p>
                            @enderror
                            <p id="dica-minima" class="text-apoio text-escult-ink-3">Em branco usa o padrão da escola ({{ $minimaEfetiva }}%). Decide a emissão do certificado.</p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="modalidade" class="block text-rotulo font-semibold text-ava-tinta">Modalidade</label>
                            <input id="modalidade" name="modalidade" type="text" maxlength="60"
                                   value="{{ old('modalidade', $curso['modalidade']) }}"
                                   class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                        </div>

                        <div class="space-y-1.5">
                            <label for="nivel" class="block text-rotulo font-semibold text-ava-tinta">Nível</label>
                            <input id="nivel" name="nivel" type="text" maxlength="60"
                                   value="{{ old('nivel', $curso['nivel']) }}"
                                   class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                        </div>

                        <div class="space-y-1.5 sm:col-span-2">
                            <label for="area_tematica" class="block text-rotulo font-semibold text-ava-tinta">Área temática</label>
                            <input id="area_tematica" name="area_tematica" type="text" maxlength="150"
                                   value="{{ old('area_tematica', $curso['area_tematica']) }}"
                                   class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                        </div>
                    </div>

                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-ava-borda p-3.5 text-apoio text-ava-tinta hover:bg-ava-faixa has-[:checked]:border-ava-acao has-[:checked]:bg-ava-faixa">
                        <input type="checkbox" name="emite_certificado" value="1"
                               @checked(old('emite_certificado', ($curso['emite_certificado'] ?? null) !== false))
                               class="mt-0.5 h-4 w-4 shrink-0 accent-[var(--color-ava-acao)]">
                        <span>
                            Este curso emite certificado
                        </span>
                    </label>

                    <div class="flex flex-wrap items-center gap-3">
                    <button type="submit"
                            class="cursor-pointer rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                        Salvar alterações
                    </button>
                    <button type="button" @click="editando = false"
                            class="cursor-pointer rounded-[10px] border border-ava-borda px-5 py-2.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                        Cancelar
                    </button>
                    </div>
                </form>
        </section>

        {{-- Para onde ir --}}
        <section aria-labelledby="titulo-partes" class="space-y-3">
            <h2 id="titulo-partes" class="font-titulo text-cartao text-ava-tinta">Partes do curso</h2>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                @foreach (array_values(array_filter([
                    config('features.modulosAulas') === true ? ['/inst/curso/'.$ref.'/grade-curricular', 'Grade curricular', $aulas === 1 ? '1 aula' : $aulas.' aulas'] : null,
                    config('features.quizSimples') === true ? ['/inst/curso/'.$ref.'/avaliacoes', 'Avaliações', $avaliacoes === 1 ? '1 avaliação' : $avaliacoes.' avaliações'] : null,
                    config('features.dadosGerenciais') === true ? ['/inst/curso/'.$ref.'/alunos', 'Alunos', $alunos === 1 ? '1 aluno' : $alunos.' alunos'] : null,
                ])) as [$rota, $rotulo, $resumo])
                    <a href="{{ $rota }}"
                       class="rounded-2xl border border-ava-borda bg-white p-4 transition-colors hover:border-ava-acao/40 hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                        <span class="block text-rotulo font-semibold text-ava-tinta">{{ $rotulo }}</span>
                        <span class="mt-0.5 block text-apoio text-escult-ink-2">{{ $resumo }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- Encontros ao vivo, só leitura nesta fatia --}}
        @if ($encontros !== [])
            <section aria-labelledby="titulo-encontros" class="space-y-3 rounded-2xl border border-ava-borda bg-white p-5 shadow-3xs">
                <h2 id="titulo-encontros" class="font-titulo text-cartao text-ava-tinta">Encontros ao vivo</h2>
                <ul role="list" class="space-y-2">
                    @foreach ($encontros as $encontro)
                        <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-ava-borda pb-2 last:border-0 last:pb-0">
                            <span class="min-w-0 text-rotulo font-semibold text-ava-tinta break-words">{{ $encontro['titulo'] }}</span>
                            <span class="text-apoio text-escult-ink-2">
                                {{ $encontro['quando'] }}
                                @if ($encontro['link'] !== null)
                                    · <a href="{{ $encontro['link'] }}" target="_blank" rel="noopener noreferrer"
                                         class="font-semibold text-ava-acao underline-offset-[3px] hover:underline">Link do encontro</a>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Vigência --}}
        <section aria-labelledby="titulo-prazo" class="rounded-2xl border border-ava-borda bg-white shadow-3xs">
            <details class="group" @if ($vigenciaVencida || $errors->has('prazo')) open @endif>
                <summary class="cursor-pointer list-none rounded-2xl p-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                    <h2 id="titulo-prazo" class="inline font-titulo text-cartao text-ava-tinta">Vigência de exibição</h2>
                    <span class="ml-2 text-apoio text-escult-ink-3 group-open:hidden">alterar</span>
                </summary>

                <form method="POST" action="/inst/curso/{{ $ref }}/vigencia" class="space-y-4 border-t border-ava-borda p-5">
                    @csrf

                    @if ($errors->has('prazo') || $errors->has('ate'))
                        <p role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-apoio font-semibold text-rose-800">
                            {{ $errors->first('prazo') ?: $errors->first('ate') }}
                        </p>
                    @endif

                    <fieldset class="space-y-2">
                        <legend class="text-rotulo font-semibold text-ava-tinta">Até quando o curso fica no ar</legend>

                        @foreach ([['6-meses', 'Mais 6 meses'], ['1-ano', 'Mais 1 ano'], ['permanente', 'Sem prazo (permanente)'], ['data', 'Escolher a data']] as [$valor, $rotulo])
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-ava-borda p-3 text-apoio text-ava-tinta hover:bg-ava-faixa has-[:checked]:border-ava-acao has-[:checked]:bg-ava-faixa">
                                <input type="radio" name="prazo" value="{{ $valor }}" required
                                       @checked(old('prazo') === $valor)
                                       class="h-4 w-4 shrink-0 accent-[var(--color-ava-acao)]">
                                <span>{{ $rotulo }}</span>
                            </label>
                        @endforeach
                    </fieldset>

                    <div class="space-y-1.5">
                        <label for="ate" class="block text-rotulo font-semibold text-ava-tinta">Data (se escolheu “Escolher a data”)</label>
                        <input id="ate" name="ate" type="date" value="{{ old('ate') }}"
                               class="rounded-[10px] border border-ava-borda bg-white px-4 py-2.5 text-apoio text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                    </div>

                    <button type="submit"
                            class="cursor-pointer rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                        Salvar vigência
                    </button>
                </form>
            </details>
        </section>

        {{-- Capa: a mesma regra do painel do admin (PNG, JPEG ou WebP, até 4 MB). --}}
        <section aria-labelledby="titulo-capa" class="rounded-2xl border border-ava-borda bg-white shadow-3xs">
            <details class="group" @if ($errors->has('capa')) open @endif>
                <summary class="cursor-pointer list-none rounded-2xl p-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                    <h2 id="titulo-capa" class="inline font-titulo text-cartao text-ava-tinta">Capa do curso</h2>
                    <span class="ml-2 text-apoio text-escult-ink-3 group-open:hidden">alterar</span>
                </summary>

                <div class="space-y-4 border-t border-ava-borda p-5">
                    @if (! empty($curso['imagem_capa']))
                        <img src="{{ $curso['imagem_capa'] }}" alt="Capa atual do curso" loading="lazy" referrerpolicy="no-referrer"
                             class="max-h-48 w-auto rounded-xl border border-ava-borda bg-ava-faixa">
                    @else
                        <p class="text-apoio text-escult-ink-2">Este curso ainda não tem capa própria: o catálogo usa uma imagem padrão.</p>
                    @endif

                    @if ($errors->has('capa'))
                        <p role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-apoio font-semibold text-rose-800">{{ $errors->first('capa') }}</p>
                    @endif

                    <form method="POST" action="/inst/curso/{{ $ref }}/capa" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <div class="space-y-1.5">
                            <label for="capa" class="block text-rotulo font-semibold text-ava-tinta">Escolher imagem</label>
                            <input id="capa" name="capa" type="file" required accept="image/png,image/jpeg,image/webp"
                                   class="block w-full text-apoio text-ava-tinta file:mr-3 file:cursor-pointer file:rounded-[10px] file:border file:border-ava-borda file:bg-white file:px-4 file:py-2 file:text-rotulo file:font-semibold file:text-ava-tinta">
                            <p class="text-apoio text-escult-ink-2">PNG, JPEG ou WebP, até 4 MB.</p>
                        </div>
                        <button type="submit"
                                class="cursor-pointer rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                            Enviar capa
                        </button>
                    </form>

                    @if (! empty($curso['imagem_capa']))
                        <form method="POST" action="/inst/curso/{{ $ref }}/capa">
                            @csrf
                            <input type="hidden" name="remover" value="1">
                            <button type="submit"
                                    class="cursor-pointer rounded-[10px] border border-ava-borda bg-white px-5 py-2.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                                Remover capa
                            </button>
                        </form>
                    @endif
                </div>
            </details>
        </section>

        {{-- Retirar do ar. Inativa, não apaga (ADR 12). Duas etapas SEM JavaScript: o botão que age só
             aparece depois de abrir o `<details>` interno. O curso vai para "Cursos inativos". --}}
        <section aria-labelledby="titulo-retirar" class="rounded-2xl border border-rose-200 bg-white shadow-3xs">
            <details class="group" @if ($errors->has('retirar') || $errors->has('motivo')) open @endif>
                <summary class="cursor-pointer list-none rounded-2xl p-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                    <h2 id="titulo-retirar" class="inline font-titulo text-cartao text-rose-900">Retirar do ar</h2>
                    <span class="ml-2 text-apoio text-escult-ink-3 group-open:hidden">abrir</span>
                </summary>

                <form method="POST" action="/inst/curso/{{ $ref }}/retirar" class="space-y-4 border-t border-rose-200 p-5">
                    @csrf
                    <p class="text-apoio leading-relaxed text-escult-ink-2">
                        Retirar tira o curso do ar para todos. <strong>Nada é apagado</strong>: aulas, avaliações, matrículas e
                        o histórico dos alunos ficam guardados. Você o encontra em <em>Meus cursos &rsaquo; Cursos inativos</em>
                        e pode reativá-lo quando quiser.
                    </p>

                    @if ($errors->has('retirar') || $errors->has('motivo'))
                        <p role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-apoio font-semibold text-rose-800">
                            {{ $errors->first('retirar') ?: $errors->first('motivo') }}
                        </p>
                    @endif

                    <div class="space-y-1.5">
                        <label for="motivo" class="block text-rotulo font-semibold text-ava-tinta">
                            Motivo <span class="font-normal text-escult-ink-2">(opcional, fica registrado)</span>
                        </label>
                        <textarea id="motivo" name="motivo" rows="2" maxlength="500"
                                  class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-apoio text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">{{ old('motivo') }}</textarea>
                    </div>

                    <details>
                        <summary class="inline-flex cursor-pointer list-none items-center rounded-[10px] border border-rose-300 bg-white px-5 py-2.5 text-rotulo font-semibold text-rose-800 transition-colors hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-600">
                            Retirar este curso do ar
                        </summary>
                        <div class="mt-3 space-y-3 rounded-xl border border-rose-200 bg-rose-50 p-4">
                            <p class="text-apoio font-semibold text-rose-900">Confirma retirar “{{ $curso['titulo'] }}” do ar?</p>
                            <button type="submit"
                                    class="cursor-pointer rounded-[10px] bg-rose-700 px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-rose-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-600 focus-visible:ring-offset-2">
                                Sim, retirar do ar
                            </button>
                        </div>
                    </details>
                </form>
            </details>
        </section>
    </div>
</x-layouts.instrutor>
