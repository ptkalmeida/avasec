{{--
    `/admin/perfil` — os dados da conta do administrador.

    Só leitura: nome e e-mail são o que a conta tem. A senha tem página própria
    (`/admin/perfil/senha`), que é onde o menu do usuário a procura.
--}}
<x-layouts.admin titulo="Meu perfil" contexto="Dados da sua conta">
    <section aria-labelledby="titulo-dados" class="max-w-xl rounded-[10px] border border-slate-200 bg-white p-5 shadow-card">
        <h2 id="titulo-dados" class="mb-4 text-[0.875rem] font-bold text-slate-900">Dados da conta</h2>

        <dl class="space-y-3">
            <div>
                <dt class="text-[0.7rem] font-bold uppercase tracking-[0.06em] text-slate-500">Nome</dt>
                <dd class="mt-0.5 text-slate-900">{{ $conta['nome'] }}</dd>
            </div>
            <div>
                <dt class="text-[0.7rem] font-bold uppercase tracking-[0.06em] text-slate-500">E-mail</dt>
                <dd class="mt-0.5 text-slate-900">{{ $conta['email'] ?? 'Não informado' }}</dd>
            </div>
            <div>
                <dt class="text-[0.7rem] font-bold uppercase tracking-[0.06em] text-slate-500">Perfil</dt>
                <dd class="mt-0.5">
                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide text-blue-600">Administrador</span>
                </dd>
            </div>
        </dl>

        <a href="/admin/perfil/senha"
           class="mt-5 inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
            <i class="fa-solid fa-key" aria-hidden="true"></i> Trocar senha
        </a>
    </section>
</x-layouts.admin>
