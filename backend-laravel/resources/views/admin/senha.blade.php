{{--
    `/admin/perfil/senha` — o administrador troca a própria senha.

    A senha atual é obrigatória e é conferida no servidor; errar não bloqueia a
    conta. Mesma regra e mesma mensagem do formulário do aluno.
--}}
<x-layouts.admin titulo="Trocar senha" contexto="Use uma senha que você não usa em outro lugar">
    <section aria-labelledby="titulo-senha" class="max-w-xl rounded-[10px] border border-slate-200 bg-white p-5 shadow-card">
        <h2 id="titulo-senha" class="mb-4 text-[0.875rem] font-bold text-slate-900">Alterar senha</h2>

        @if (session('senhaAviso'))
            <div role="status" class="mb-4 flex items-center gap-2 rounded-[10px] border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ session('senhaAviso') }}
            </div>
        @endif

        <form method="POST" action="/admin/perfil/senha" class="space-y-4">
            @csrf

            <div class="space-y-1">
                <label for="senha-atual" class="block text-[0.8rem] font-semibold text-slate-700">Senha atual</label>
                <input id="senha-atual" name="senhaAtual" type="password" required autocomplete="current-password"
                       @if ($errors->senha->has('senhaAtual')) aria-invalid="true" aria-describedby="erro-senha-atual" @endif
                       class="w-full rounded-md border border-slate-200 px-3 py-2 text-slate-900 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                @if ($errors->senha->has('senhaAtual'))
                    <p id="erro-senha-atual" class="text-xs font-semibold text-red-600">{{ $errors->senha->first('senhaAtual') }}</p>
                @endif
            </div>

            <div class="space-y-1">
                <label for="nova-senha" class="block text-[0.8rem] font-semibold text-slate-700">Nova senha</label>
                <input id="nova-senha" name="novaSenha" type="password" required minlength="8" autocomplete="new-password"
                       aria-describedby="regra-nova-senha{{ $errors->senha->has('novaSenha') ? ' erro-nova-senha' : '' }}"
                       @if ($errors->senha->has('novaSenha')) aria-invalid="true" @endif
                       class="w-full rounded-md border border-slate-200 px-3 py-2 text-slate-900 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                <p id="regra-nova-senha" class="text-xs text-slate-500">{{ $regraDaSenha }}</p>
                @if ($errors->senha->has('novaSenha'))
                    <p id="erro-nova-senha" class="text-xs font-semibold text-red-600">{{ $errors->senha->first('novaSenha') }}</p>
                @endif
            </div>

            <div class="space-y-1">
                <label for="confirma-senha" class="block text-[0.8rem] font-semibold text-slate-700">Confirme a nova senha</label>
                <input id="confirma-senha" name="novaSenha_confirmation" type="password" required autocomplete="new-password"
                       class="w-full rounded-md border border-slate-200 px-3 py-2 text-slate-900 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
            </div>

            <div class="flex items-center gap-2">
                <button type="submit"
                        class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                    Alterar senha
                </button>
                <a href="/admin/perfil"
                   class="rounded-md border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                    Cancelar
                </a>
            </div>
        </form>
    </section>
</x-layouts.admin>
