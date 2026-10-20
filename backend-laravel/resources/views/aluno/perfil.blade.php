{{--
    Perfil do aluno — os dados da conta, o nome e a senha.

    Só o que existe no servidor. O que no React vivia apenas no navegador de
    quem olhava (avatar, foto, frase de status, um "e-mail" montado a partir do
    nome, o selo Online/Offline) não veio — ver PerfilDoAlunoController.

    ## O "Histórico Escolar Oficial" não veio, e o motivo é sério

    O React oferecia a todo aluno um documento para imprimir intitulado
    "Histórico Escolar Oficial", "validado eletronicamente", com código de
    barras decorativo e as assinaturas de duas pessoas ESCRITAS NO CÓDIGO — com
    cargos que nem batem com os do modelo de certificado que o admin edita.
    Nada nele é verificável: não há código que o autenticador público
    reconheça. Um papel com cara de documento oficial e assinatura que ninguém
    deu não é algo que uma escola pública possa entregar. A decisão de
    reconstruí-lo com dados e signatários reais é do responsável (plano 18).
--}}
<x-layouts.aluno titulo="Perfil" ativo="perfil">

    <div class="mx-auto max-w-2xl space-y-8">

        <h1 class="font-titulo text-3xl tracking-tight text-ava-tinta">Perfil</h1>

        @if (session('perfilAviso'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-apoio font-semibold text-emerald-800">
                {{ session('perfilAviso') }}
            </div>
        @endif

        {{-- Dados da conta --}}
        <section aria-labelledby="titulo-dados" class="space-y-4 rounded-2xl border border-ava-borda bg-white p-5 shadow-3xs">
            <h2 id="titulo-dados" class="font-titulo text-cartao text-ava-tinta">Seus dados</h2>

            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-apoio sm:grid-cols-2">
                <div>
                    <dt class="text-escult-ink-2">Nome</dt>
                    <dd class="font-semibold text-ava-tinta break-words">{{ $conta['nome'] }}</dd>
                </div>
                @if ($conta['nomeSocial'] !== null)
                    <div>
                        <dt class="text-escult-ink-2">Nome social</dt>
                        <dd class="font-semibold text-ava-tinta break-words">{{ $conta['nomeSocial'] }}</dd>
                    </div>
                @endif
                @if ($conta['cpf'] !== null)
                    <div>
                        <dt class="text-escult-ink-2">CPF</dt>
                        {{-- Mascarado: o bastante para reconhecer, pouco para quem
                             olha por cima do ombro num laboratório. --}}
                        <dd class="font-semibold text-ava-tinta">{{ $conta['cpf'] }}</dd>
                    </div>
                @endif
                @if ($conta['email'] !== null)
                    <div>
                        <dt class="text-escult-ink-2">E-mail</dt>
                        <dd class="font-semibold text-ava-tinta break-all">{{ $conta['email'] }}</dd>
                    </div>
                @endif
            </dl>

            <p class="text-apoio text-escult-ink-3">CPF e e-mail não são alterados por esta tela.</p>
        </section>

        {{-- Nome --}}
        <section aria-labelledby="titulo-nome" class="space-y-4 rounded-2xl border border-ava-borda bg-white p-5 shadow-3xs">
            <div class="space-y-1">
                <h2 id="titulo-nome" class="font-titulo text-cartao text-ava-tinta">Alterar nome</h2>
                <p class="text-apoio text-escult-ink-2">
                    É o nome que sai no seu próximo certificado. Certificados já emitidos não mudam.
                </p>
            </div>

            <form method="POST" action="/aluno/perfil/nome" class="space-y-4">
                @csrf
                <div class="space-y-1.5">
                    <label for="nome" class="block text-rotulo font-semibold text-ava-tinta">Nome completo</label>
                    <input id="nome" name="nome" type="text" required minlength="2" maxlength="150" autocomplete="name"
                           value="{{ old('nome', $conta['nome']) }}"
                           @if ($errors->nome->has('nome')) aria-invalid="true" aria-describedby="erro-nome" @endif
                           class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                    @if ($errors->nome->has('nome'))
                        <p id="erro-nome" class="text-apoio font-semibold text-rose-700">{{ $errors->nome->first('nome') }}</p>
                    @endif
                </div>

                <div class="space-y-1.5">
                    <label for="nome-senha" class="block text-rotulo font-semibold text-ava-tinta">Sua senha atual</label>
                    <input id="nome-senha" name="senhaAtual" type="password" required autocomplete="current-password"
                           aria-describedby="nome-senha-dica{{ $errors->nome->has('senhaAtual') ? ' erro-nome-senha' : '' }}"
                           @if ($errors->nome->has('senhaAtual')) aria-invalid="true" @endif
                           class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                    <p id="nome-senha-dica" class="text-apoio text-escult-ink-3">Para confirmar que é você.</p>
                    @if ($errors->nome->has('senhaAtual'))
                        <p id="erro-nome-senha" class="text-apoio font-semibold text-rose-700">{{ $errors->nome->first('senhaAtual') }}</p>
                    @endif
                </div>

                <button type="submit"
                        class="rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                    Salvar nome
                </button>
            </form>
        </section>

        {{-- Senha --}}
        <section aria-labelledby="titulo-senha" class="space-y-4 rounded-2xl border border-ava-borda bg-white p-5 shadow-3xs">
            <h2 id="titulo-senha" class="font-titulo text-cartao text-ava-tinta">Alterar senha</h2>

            <form method="POST" action="/aluno/perfil/senha" class="space-y-4">
                @csrf
                <div class="space-y-1.5">
                    <label for="senha-atual" class="block text-rotulo font-semibold text-ava-tinta">Senha atual</label>
                    <input id="senha-atual" name="senhaAtual" type="password" required autocomplete="current-password"
                           @if ($errors->senha->has('senhaAtual')) aria-invalid="true" aria-describedby="erro-senha-atual" @endif
                           class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                    @if ($errors->senha->has('senhaAtual'))
                        <p id="erro-senha-atual" class="text-apoio font-semibold text-rose-700">{{ $errors->senha->first('senhaAtual') }}</p>
                    @endif
                </div>

                <div class="space-y-1.5">
                    <label for="nova-senha" class="block text-rotulo font-semibold text-ava-tinta">Nova senha</label>
                    <input id="nova-senha" name="novaSenha" type="password" required minlength="8" autocomplete="new-password"
                           aria-describedby="regra-nova-senha{{ $errors->senha->has('novaSenha') ? ' erro-nova-senha' : '' }}"
                           @if ($errors->senha->has('novaSenha')) aria-invalid="true" @endif
                           class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                    {{-- A regra do servidor, dita ANTES da tentativa. O React dizia
                         "mínimo 6 caracteres" e o servidor recusava com 7. --}}
                    <p id="regra-nova-senha" class="text-apoio text-escult-ink-3">{{ $regraDaSenha }}</p>
                    @if ($errors->senha->has('novaSenha'))
                        <p id="erro-nova-senha" class="text-apoio font-semibold text-rose-700">{{ $errors->senha->first('novaSenha') }}</p>
                    @endif
                </div>

                <div class="space-y-1.5">
                    <label for="confirma-senha" class="block text-rotulo font-semibold text-ava-tinta">Confirme a nova senha</label>
                    <input id="confirma-senha" name="novaSenha_confirmation" type="password" required autocomplete="new-password"
                           class="w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30">
                </div>

                <button type="submit"
                        class="rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                    Alterar senha
                </button>
            </form>
        </section>

        {{-- Sessão --}}
        <section aria-labelledby="titulo-sessao" class="space-y-3 rounded-2xl border border-ava-borda bg-white p-5 shadow-3xs">
            <h2 id="titulo-sessao" class="font-titulo text-cartao text-ava-tinta">Sessão</h2>
            <p class="text-apoio text-escult-ink-2">Num computador compartilhado, saia ao terminar.</p>
            <form method="POST" action="/sair">
                @csrf
                <button type="submit"
                        class="rounded-[10px] border border-ava-borda bg-white px-5 py-2.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                    Sair da conta
                </button>
            </form>
        </section>
    </div>
</x-layouts.aluno>
