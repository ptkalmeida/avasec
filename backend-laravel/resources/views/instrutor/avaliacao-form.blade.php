{{--
    Formulário de avaliação — a mesma tela cria e edita.

    Substitui o `EditorAvaliacao` do `AvaliacoesManagePanel.tsx`. O que muda,
    de propósito:

    - o CURSO vem do endereço, e o formulário não tem campo de curso. O editor
      React tinha o seletor, e a API aceita `curso_id` no corpo; aqui uma prova
      não muda de curso por edição;
    - a validação mora no SERVIDOR (`QuestaoDeProva`). No React ela existia só
      no navegador, e a API gravava gabarito impossível;
    - o campo "Permitir tentar novamente esta questão" saiu: a prova do aluno é
      corrigida no fim, e o campo deixou de ter efeito. O valor que já estava
      gravado é preservado;
    - os dois textos da questão dizem PARA QUEM aparecem. Nos cursos
      cadastrados, a explicação é escrita para quem ACERTOU ("Correto! ...") e a
      dica para quem ERROU — e é assim que o resultado do aluno os usa.

    Adicionar e remover questões e alternativas usa Alpine: a lista é dinâmica
    por natureza, e é o único trecho desta tela que precisa de JavaScript.
--}}
<x-layouts.instrutor :titulo="($prova === null ? 'Nova avaliação' : 'Editar avaliação').' — '.$curso['titulo']" ativo="avaliacoes" :cursoDoMenu="$curso">

    @php
        $acao = $prova === null
            ? '/inst/curso/'.$ref.'/avaliacoes'
            : '/inst/curso/'.$ref.'/avaliacoes/'.rawurlencode($prova['id']);
        $erroDasQuestoes = collect($errors->getMessages())
            ->filter(fn ($_, $chave) => $chave === 'questoes' || str_starts_with((string) $chave, 'questoes.'))
            ->flatten()->all();
    @endphp

    <div class="mx-auto max-w-3xl space-y-6">

        <nav aria-label="Trilha de navegação" class="text-apoio text-escult-ink-3">
            <a href="/inst" class="underline-offset-[3px] hover:text-ava-acao hover:underline">Meus cursos</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <a href="/inst/curso/{{ $ref }}" class="underline-offset-[3px] hover:text-ava-acao hover:underline">{{ $curso['titulo'] }}</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <a href="/inst/curso/{{ $ref }}/avaliacoes" class="underline-offset-[3px] hover:text-ava-acao hover:underline">Avaliações</a>
        </nav>

        <h1 class="font-titulo text-3xl leading-tight tracking-tight text-ava-tinta">
            {{ $prova === null ? 'Nova avaliação' : 'Editar avaliação' }}
        </h1>

        @if ($prova === null)
            <p class="rounded-xl border border-ava-borda bg-ava-faixa p-4 text-apoio leading-relaxed text-ava-tinta">
                Assim que for criada, a avaliação vai para a turma e passa a ser <strong>exigida para o certificado</strong>
                de quem ainda não o recebeu. Aprovação: pelo menos {{ $minimo }}% de acertos.
            </p>
        @elseif ($jaResponderam > 0)
            <p role="note" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-apoio leading-relaxed text-amber-950">
                <strong>{{ $jaResponderam === 1 ? '1 aluno já respondeu' : $jaResponderam.' alunos já responderam' }}</strong> esta avaliação.
                Mudar um enunciado ou o gabarito <strong>não recalcula</strong> as notas já registradas: quem foi aprovado continua aprovado.
            </p>
        @endif

        @if ($erroDasQuestoes !== [] || $errors->has('titulo'))
            <div role="alert" class="space-y-1 rounded-xl border border-rose-200 bg-rose-50 p-4 text-apoio text-rose-900">
                <p class="font-semibold">Corrija antes de salvar:</p>
                <ul class="list-disc space-y-0.5 pl-5">
                    @if ($errors->has('titulo'))
                        <li>{{ $errors->first('titulo') }}</li>
                    @endif
                    @foreach ($erroDasQuestoes as $mensagem)
                        <li>{{ $mensagem }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $acao }}" class="space-y-6"
              x-data="{
                  proxima: 0,
                  questoes: [],
                  titulo: @js(old('titulo', $prova['titulo'] ?? '')),
                  nova(q = {}) {
                      return {
                          chave: ++this.proxima,
                          id: q.id ?? '',
                          enunciado: q.enunciado ?? '',
                          alternativas: (q.alternativas ?? ['', '', '', '']).map((t) => ({ chave: ++this.proxima, texto: t })),
                          correta: q.correta ?? 0,
                          explicacao: q.explicacao ?? '',
                          revisao: q.revisao ?? '',
                          modulo: q.modulo ?? '',
                      };
                  },
                  init() {
                      this.questoes = @js($inicial).map((q) => this.nova(q));
                  },
                  acrescentarQuestao() { this.questoes.push(this.nova()); },
                  removerQuestao(i) { if (this.questoes.length > 1) this.questoes.splice(i, 1); },
                  acrescentarAlternativa(q) { if (q.alternativas.length < 6) q.alternativas.push({ chave: ++this.proxima, texto: '' }); },
                  removerAlternativa(q, j) {
                      if (q.alternativas.length <= 2) return;
                      q.alternativas.splice(j, 1);
                      // Remover antes da correta a deslocaria; remover a própria a leva para a vizinha.
                      if (q.correta > j) q.correta -= 1;
                      else if (q.correta >= q.alternativas.length) q.correta = q.alternativas.length - 1;
                  },
                  letra(j) { return 'ABCDEF'[j]; },
                  enviando: false,
                  mexeu: false,
              }"
              x-on:input="mexeu = true"
              x-on:submit="enviando = true"
              x-on:beforeunload.window="if (mexeu && ! enviando) { $event.preventDefault(); $event.returnValue = ''; }">
            @csrf

            <div class="space-y-1.5">
                <label for="titulo" class="block text-rotulo font-semibold text-ava-tinta">Título da avaliação</label>
                <input id="titulo" name="titulo" type="text" required maxlength="200" x-model="titulo"
                       @error('titulo') aria-invalid="true" @enderror
                       class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
            </div>

            <ol role="list" class="space-y-5">
                <template x-for="(q, i) in questoes" :key="q.chave">
                    <li>
                        {{-- `min-w-0`: o <fieldset> nativo tem `min-width: min-content` e NÃO encolhe
                             como um bloco comum — a 320px ele estourava a largura da tela. --}}
                        <fieldset class="min-w-0 space-y-4 rounded-2xl border border-ava-borda bg-white p-5 shadow-3xs">
                            <legend class="sr-only" x-text="'Questão ' + (i + 1)"></legend>

                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sobretitulo uppercase text-escult-ink-3" aria-hidden="true" x-text="'Questão ' + (i + 1)"></p>
                                <button type="button" @click="removerQuestao(i)" x-show="questoes.length > 1"
                                        class="cursor-pointer rounded-[10px] px-3 py-1.5 text-apoio font-semibold text-rose-700 transition-colors hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
                                    Remover
                                    <span class="sr-only" x-text="'a questão ' + (i + 1)"></span>
                                </button>
                            </div>

                            <input type="hidden" :name="`questoes[${i}][id]`" :value="q.id">

                            <div class="space-y-1.5">
                                <label class="block text-rotulo font-semibold text-ava-tinta" :for="`enunciado-${q.chave}`">Enunciado</label>
                                <textarea :id="`enunciado-${q.chave}`" :name="`questoes[${i}][enunciado]`" rows="3" maxlength="2000" required
                                          x-model="q.enunciado"
                                          class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-apoio text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30"></textarea>
                            </div>

                            <div class="space-y-2" role="radiogroup" :aria-labelledby="`alternativas-${q.chave}`">
                                <p :id="`alternativas-${q.chave}`" class="text-rotulo font-semibold text-ava-tinta">
                                    Alternativas — marque a correta
                                </p>

                                <template x-for="(alt, j) in q.alternativas" :key="alt.chave">
                                    <div class="flex items-center gap-2">
                                        <input type="radio" :name="`questoes[${i}][correta]`" :value="j"
                                               :checked="q.correta === j" @change="q.correta = j"
                                               :aria-label="`Alternativa ${letra(j)} é a correta`"
                                               class="h-4 w-4 shrink-0 accent-[var(--color-ava-acao)]">
                                        <span class="w-5 shrink-0 text-rotulo font-bold text-escult-ink-2" aria-hidden="true" x-text="letra(j)"></span>
                                        <input type="text" :name="`questoes[${i}][alternativas][${j}]`" maxlength="500"
                                               x-model="alt.texto" :aria-label="`Texto da alternativa ${letra(j)}`"
                                               class="min-w-0 flex-1 rounded-[10px] border border-ava-borda bg-white px-3 py-2.5 text-apoio text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                                        <button type="button" @click="removerAlternativa(q, j)" x-show="q.alternativas.length > 2"
                                                :aria-label="`Excluir a alternativa ${letra(j)}`"
                                                class="cursor-pointer rounded-lg px-2 py-1 text-apoio font-bold text-escult-ink-2 hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500">
                                            ✕
                                        </button>
                                    </div>
                                </template>

                                <button type="button" @click="acrescentarAlternativa(q)" x-show="q.alternativas.length < 6"
                                        class="cursor-pointer text-apoio font-semibold text-ava-acao underline-offset-[3px] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                                    + Acrescentar alternativa
                                </button>
                            </div>

                            <details class="rounded-xl bg-ava-faixa p-3">
                                <summary class="cursor-pointer text-rotulo font-semibold text-ava-tinta">Retorno ao aluno (opcional)</summary>
                                <div class="mt-3 space-y-3">
                                    <div class="space-y-1.5">
                                        <label class="block text-rotulo font-semibold text-ava-tinta" :for="`explicacao-${q.chave}`">
                                            Explicação <span class="font-normal text-escult-ink-3">— aparece para quem ACERTOU</span>
                                        </label>
                                        <textarea :id="`explicacao-${q.chave}`" :name="`questoes[${i}][explicacao]`" rows="2" maxlength="2000"
                                                  x-model="q.explicacao"
                                                  class="w-full rounded-[10px] border border-ava-borda bg-white px-3 py-2.5 text-apoio text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30"></textarea>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-rotulo font-semibold text-ava-tinta" :for="`revisao-${q.chave}`">
                                            Dica <span class="font-normal text-escult-ink-3">— aparece para quem ERROU</span>
                                        </label>
                                        <input :id="`revisao-${q.chave}`" :name="`questoes[${i}][revisao]`" type="text" maxlength="2000"
                                               x-model="q.revisao"
                                               class="w-full rounded-[10px] border border-ava-borda bg-white px-3 py-2.5 text-apoio text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-rotulo font-semibold text-ava-tinta" :for="`modulo-${q.chave}`">
                                            Módulo ou aula para revisar <span class="font-normal text-escult-ink-3">— aparece para quem ERROU</span>
                                        </label>
                                        <input :id="`modulo-${q.chave}`" :name="`questoes[${i}][modulo]`" type="text" maxlength="200"
                                               x-model="q.modulo"
                                               class="w-full rounded-[10px] border border-ava-borda bg-white px-3 py-2.5 text-apoio text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                                    </div>
                                    <p class="text-apoio text-escult-ink-3">
                                        O aluno nunca vê qual era a alternativa certa — ele pode refazer a prova.
                                    </p>
                                </div>
                            </details>
                        </fieldset>
                    </li>
                </template>
            </ol>

            <button type="button" @click="acrescentarQuestao()"
                    class="cursor-pointer rounded-[10px] border border-dashed border-ava-borda bg-white px-5 py-3 text-rotulo font-semibold text-ava-acao transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                + Acrescentar questão
            </button>

            <div class="flex flex-col gap-3 border-t border-ava-borda pt-5 sm:flex-row">
                <button type="submit" x-bind:disabled="enviando"
                        class="cursor-pointer rounded-[10px] bg-ava-acao px-6 py-3 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2 disabled:opacity-60">
                    <span x-text="enviando ? 'Salvando…' : '{{ $prova === null ? 'Criar avaliação' : 'Salvar alterações' }}'">{{ $prova === null ? 'Criar avaliação' : 'Salvar alterações' }}</span>
                </button>
                <a href="/inst/curso/{{ $ref }}/avaliacoes"
                   class="inline-flex items-center justify-center rounded-[10px] border border-ava-borda bg-white px-6 py-3 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</x-layouts.instrutor>
