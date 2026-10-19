{{--
    Entrar — a porta de entrada do sistema, agora como endereço.

    Era um modal dentro do SPA, alcançável só depois de carregar o React
    inteiro. Como tela, ela pode ser destino de link, de favorito e do
    redirecionamento de uma rota protegida — que é o que a área do aluno já faz.

    O seletor de papel troca o campo de identificador: aluno entra por CPF,
    gestão e admin por e-mail (ADR 11). Ele é Alpine puro, sem ida ao servidor:
    é mudança de rótulo e de teclado, não de dado.
--}}
<x-layouts.publico ativo="" titulo="Entrar" descricao="Acesse o Ambiente Virtual de Aprendizagem da Escola Estadual da Cultura RJ.">

    <div class="mx-auto max-w-md px-4 py-12 md:py-16">

        <div class="space-y-2 text-center">
            <p class="filete text-sobretitulo uppercase text-ava-tinta">Acesso</p>
            <h1 class="font-titulo text-3xl tracking-tight text-ava-tinta">Entrar no AVASEC</h1>
        </div>

        @if (session('aviso'))
            <div role="status" class="mt-6 rounded-xl border border-ava-lilas-borda bg-ava-lilas-fundo p-4 text-apoio leading-relaxed text-ava-tinta">
                {{ session('aviso') }}
            </div>
        @endif

        @error('entrada')
            {{-- `role="alert"` e foco: quem usa leitor de tela precisa saber que a
                 tentativa falhou sem ter de reler a página inteira. --}}
            <div role="alert" tabindex="-1" x-init="$el.focus()"
                 class="mt-6 rounded-xl border border-ava-rosa/40 bg-ava-rosa/5 p-4 text-apoio leading-relaxed text-ava-tinta">
                {{ $message }}
            </div>
        @enderror

        <form method="POST" action="/entrar" class="mt-7 space-y-5"
              x-data="{ papel: '{{ old('papel', $papel) }}' }">
            @csrf

            <fieldset>
                <legend class="mb-2 text-rotulo font-semibold text-ava-tinta">Quem está entrando</legend>

                {{-- O rádio é `sr-only` (o controle visível é o rótulo), e por
                     isso ele precisa de `peer`: sem as classes `peer-focus-*`,
                     quem navega por teclado move o foco entre as três opções e
                     **nada muda na tela** — o anel de foco ficaria no elemento
                     de 1px que ninguém vê. --}}
                <div class="grid grid-cols-3 gap-1 rounded-xl border border-ava-borda bg-ava-faixa p-1">
                    @foreach (['student' => 'Aluno', 'instructor' => 'Gestão', 'admin' => 'Admin'] as $valor => $rotulo)
                        <div>
                            <input type="radio" id="papel-{{ $valor }}" name="papel" value="{{ $valor }}"
                                   x-model="papel" class="peer sr-only">
                            <label for="papel-{{ $valor }}"
                                   class="block cursor-pointer rounded-[10px] px-2 py-2.5 text-center text-rotulo font-semibold transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-ava-acao peer-focus-visible:ring-offset-2"
                                   :class="papel === '{{ $valor }}' ? 'bg-ava-acao text-white' : 'text-ava-tinta hover:bg-white'">
                                {{ $rotulo }}
                            </label>
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <div class="space-y-1.5">
                <label for="identificador" class="block text-rotulo font-semibold text-ava-tinta">
                    <span x-show="papel === 'student'">CPF</span>
                    <span x-show="papel !== 'student'" x-cloak>E-mail</span>
                </label>

                {{-- `inputmode` muda o teclado do celular: numérico para CPF,
                     e-mail para o resto. `autocomplete="username"` deixa o
                     gerenciador de senhas preencher. --}}
                <input id="identificador" name="identificador" required
                       value="{{ old('identificador') }}"
                       autocomplete="username"
                       :type="papel === 'student' ? 'text' : 'email'"
                       :inputmode="papel === 'student' ? 'numeric' : 'email'"
                       :placeholder="papel === 'student' ? '000.000.000-00' : 'nome@exemplo.gov.br'"
                       @error('identificador') aria-invalid="true" aria-describedby="erro-identificador" @enderror
                       class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">

                @error('identificador')
                    <p id="erro-identificador" class="text-apoio text-ava-rosa">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-1.5" x-data="{ visivel: false }">
                <label for="senha" class="block text-rotulo font-semibold text-ava-tinta">Senha</label>

                <div class="flex items-center gap-2">
                    <input id="senha" name="senha" required autocomplete="current-password"
                           :type="visivel ? 'text' : 'password'"
                           @error('senha') aria-invalid="true" aria-describedby="erro-senha" @enderror
                           class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">

                    {{-- Mostrar a senha é acessibilidade, não conveniência: quem
                         digita com dificuldade erra e não descobre onde. --}}
                    <button type="button" @click="visivel = !visivel"
                            :aria-pressed="visivel ? 'true' : 'false'"
                            class="shrink-0 cursor-pointer rounded-[10px] border border-ava-borda px-3 py-3 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                        <span x-show="!visivel">Mostrar</span>
                        <span x-show="visivel" x-cloak>Ocultar</span>
                    </button>
                </div>

                @error('senha')
                    <p id="erro-senha" class="text-apoio text-ava-rosa">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="w-full cursor-pointer rounded-[10px] bg-ava-acao px-6 py-3 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                Entrar
            </button>
        </form>

        <p class="mt-6 text-center text-apoio text-escult-ink-2">
            Ainda não tem cadastro?
            <a href="/cadastro" class="font-semibold text-ava-acao underline-offset-[3px] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                Cadastre-se
            </a>
        </p>
    </div>
</x-layouts.publico>
