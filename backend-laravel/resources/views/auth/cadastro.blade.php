{{--
    Cadastro público.

    A conta nasce como **aluno** e **pendente de confirmação** — quem libera o
    acesso é a coordenação. A tela diz isso antes de a pessoa preencher, e não
    depois: descobrir que o acesso não é imediato só ao final é o que faz alguém
    tentar entrar três vezes e concluir que o sistema está quebrado.

    ## Os campos são os do cadastro que já estava no ar

    Nome, CPF, e-mail e senha obrigatórios; nome social, identidade, celular,
    CEP e endereço opcionais — os "dados cadastrais completos" da ADR 11, que o
    formulário do React enviava. A primeira versão desta tela (19/10) pedia
    município, UF e área de interesse no lugar deles: divergia nos dois
    sentidos, e quem se cadastrou por ela chegou à coordenação sem celular,
    endereço e nome social. Os três campos antigos continuam ACEITOS pelo
    servidor, para não quebrar nada; só deixaram de ser pedidos.

    O nome social vem logo depois do nome, e não no fim: é como a pessoa quer
    ser chamada, e é direito dela (Decreto 8.727/2016) — não um detalhe opcional
    escondido entre endereço e CEP.
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
                /* nome, rótulo, tipo, obrigatório, autocomplete, inputmode, dica */
                $campos = [
                    ['nome', 'Nome completo', 'text', true, 'name', null, null],
                    ['nome_social', 'Nome social (opcional)', 'text', false, 'nickname', null, 'Como você prefere ser chamado(a).'],
                    ['cpf', 'CPF', 'text', true, 'off', 'numeric', 'É com ele que você vai entrar na plataforma.'],
                    ['identidade', 'Identidade / RG (opcional)', 'text', false, 'off', null, null],
                    ['email', 'E-mail', 'email', true, 'email', null, null],
                    ['celular', 'Celular (opcional)', 'tel', false, 'tel', 'numeric', null],
                    ['cep', 'CEP (opcional)', 'text', false, 'postal-code', 'numeric', 'Oito dígitos.'],
                    ['endereco', 'Endereço (opcional)', 'text', false, 'street-address', null, 'Rua, número, complemento e bairro.'],
                ];
            @endphp

            @foreach ($campos as [$nome, $rotulo, $tipo, $obrigatorio, $autocomplete, $modo, $dica])
                <div class="space-y-1.5">
                    <label for="{{ $nome }}" class="block text-rotulo font-semibold text-ava-tinta">{{ $rotulo }}</label>
                    <input id="{{ $nome }}" name="{{ $nome }}" type="{{ $tipo }}"
                           value="{{ old($nome) }}" autocomplete="{{ $autocomplete }}"
                           @if ($modo) inputmode="{{ $modo }}" @endif
                           @if ($obrigatorio) required @endif
                           @if ($dica) aria-describedby="dica-{{ $nome }}{{ $errors->has($nome) ? ' erro-'.$nome : '' }}" @elseif ($errors->has($nome)) aria-describedby="erro-{{ $nome }}" @endif
                           @error($nome) aria-invalid="true" @enderror
                           class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                    @if ($dica)
                        <p id="dica-{{ $nome }}" class="text-apoio text-escult-ink-3">{{ $dica }}</p>
                    @endif
                    @error($nome)
                        <p id="erro-{{ $nome }}" class="text-apoio text-ava-rosa">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div class="space-y-1.5">
                <label for="password" class="block text-rotulo font-semibold text-ava-tinta">Senha</label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                       aria-describedby="regra-senha"
                       class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                {{-- A regra vem ANTES da tentativa, não como erro depois dela. --}}
                <p id="regra-senha" class="text-apoio text-escult-ink-3">
                    {{-- É a regra do servidor (StrongPasswordRule), dita como ela é.
                         Dizia "maiúscula, minúscula e número": mais exigente que
                         a regra real, e portanto falso. --}}
                    Pelo menos 8 caracteres, com ao menos uma letra e um número.
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
