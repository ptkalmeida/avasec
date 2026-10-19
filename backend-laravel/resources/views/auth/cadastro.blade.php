{{--
    Cadastro público.

    A conta nasce como **aluno** e **pendente de confirmação** — quem libera o
    acesso é a coordenação. A tela diz isso antes de a pessoa preencher, e não
    depois: descobrir que o acesso não é imediato só ao final é o que faz alguém
    tentar entrar três vezes e concluir que o sistema está quebrado.

    Os campos opcionais (município, UF, área de interesse) continuam opcionais —
    o `AuthService` já os aceita assim, e exigir mais dados do que o sistema usa
    é coleta sem finalidade.
--}}
<x-layouts.publico ativo="" titulo="Cadastre-se" descricao="Crie seu cadastro no Ambiente Virtual de Aprendizagem da Escola Estadual da Cultura RJ.">

    <div class="mx-auto max-w-lg px-4 py-12 md:py-16">

        <div class="space-y-2 text-center">
            <p class="filete text-sobretitulo uppercase text-ava-tinta">Cadastro</p>
            <h1 class="font-titulo text-3xl tracking-tight text-ava-tinta">Criar meu cadastro</h1>
            <p class="text-apoio leading-relaxed text-escult-ink-2">
                O acesso é liberado pela coordenação depois da confirmação da matrícula.
            </p>
        </div>

        @error('entrada')
            <div role="alert" tabindex="-1" x-init="$el.focus()"
                 class="mt-6 rounded-xl border border-ava-rosa/40 bg-ava-rosa/5 p-4 text-apoio leading-relaxed text-ava-tinta">
                {{ $message }}
            </div>
        @enderror

        <form method="POST" action="/cadastro" class="mt-7 space-y-5">
            @csrf

            @php
                $campos = [
                    ['name', 'Nome completo', 'text', true, 'name'],
                    ['cpf', 'CPF', 'text', true, 'off'],
                    ['email', 'E-mail', 'email', true, 'email'],
                    ['municipio', 'Município (opcional)', 'text', false, 'address-level2'],
                    ['areaInteresse', 'Área de interesse (opcional)', 'text', false, 'off'],
                ];
            @endphp

            @foreach ($campos as [$nome, $rotulo, $tipo, $obrigatorio, $autocomplete])
                <div class="space-y-1.5">
                    <label for="{{ $nome }}" class="block text-rotulo font-semibold text-ava-tinta">{{ $rotulo }}</label>
                    <input id="{{ $nome }}" name="{{ $nome }}" type="{{ $tipo }}"
                           value="{{ old($nome) }}" autocomplete="{{ $autocomplete }}"
                           @if ($obrigatorio) required @endif
                           @error($nome) aria-invalid="true" aria-describedby="erro-{{ $nome }}" @enderror
                           class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                    @error($nome)
                        <p id="erro-{{ $nome }}" class="text-apoio text-ava-rosa">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div class="space-y-1.5">
                <label for="uf" class="block text-rotulo font-semibold text-ava-tinta">UF (opcional)</label>
                <input id="uf" name="uf" type="text" maxlength="2" value="{{ old('uf') }}"
                       autocomplete="address-level1"
                       class="w-24 rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo uppercase text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                @error('uf')
                    <p class="text-apoio text-ava-rosa">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-1.5">
                <label for="password" class="block text-rotulo font-semibold text-ava-tinta">Senha</label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                       aria-describedby="regra-senha"
                       class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                {{-- A regra vem ANTES da tentativa, não como erro depois dela. --}}
                <p id="regra-senha" class="text-apoio text-escult-ink-3">
                    Pelo menos 8 caracteres, com letra maiúscula, minúscula e número.
                </p>
                @error('password')
                    <p class="text-apoio text-ava-rosa">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="w-full cursor-pointer rounded-[10px] bg-ava-acao px-6 py-3 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                Enviar cadastro
            </button>
        </form>

        <p class="mt-6 text-center text-apoio text-escult-ink-2">
            Já tem cadastro?
            <a href="/entrar" class="font-semibold text-ava-acao underline-offset-[3px] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                Entrar
            </a>
        </p>
    </div>
</x-layouts.publico>
