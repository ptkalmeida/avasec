{{--
    `/inst/cursos/novo` — o formulário de curso (disciplina) novo.

    Substitui o "Cadastrar Novo Curso" do painel React, que ficou de fora quando `/inst`
    virou Blade. Os campos são os da criação do admin, sem o de professor: o curso nasce
    em nome de quem está logado (regra do `CourseService`, não desta tela).

    Depois de criado, o gestor cai na tela do curso, onde monta a grade e completa a ementa.
--}}
@php
    $campo = 'w-full rounded-[10px] border border-ava-borda bg-white px-4 py-3 text-corpo text-ava-tinta focus:border-ava-acao focus:outline-none focus:ring-2 focus:ring-ava-acao/30';
    $rotulo = 'block text-rotulo font-semibold text-ava-tinta';
    $erro = 'text-apoio font-semibold text-rose-800';
@endphp
<x-layouts.instrutor titulo="Novo curso" ativo="cursos">

    <div class="mx-auto max-w-3xl space-y-6">

        <nav aria-label="Trilha de navegação" class="text-apoio text-escult-ink-3">
            <a href="/inst" class="underline-offset-[3px] hover:text-ava-acao hover:underline">Meus cursos</a>
            <span aria-hidden="true" class="px-1.5">/</span>
            <span class="text-ava-tinta">Novo curso</span>
        </nav>

        <div class="space-y-2">
            <h1 class="font-titulo text-3xl leading-tight tracking-tight text-ava-tinta">Cadastrar novo curso</h1>
            <p class="text-apoio leading-relaxed text-escult-ink-2">
                O curso é criado em seu nome. Depois de salvar, você monta a grade curricular e completa a ementa.
            </p>
        </div>

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-apoio font-semibold text-rose-800">
                Confira os campos abaixo.
            </div>
        @endif

        <form method="POST" action="/inst/cursos" class="space-y-5 rounded-2xl border border-ava-borda bg-white p-4 shadow-3xs md:p-6">
            @csrf

            <div class="space-y-1.5">
                <label for="titulo" class="{{ $rotulo }}">Título do curso</label>
                <input id="titulo" name="titulo" type="text" required minlength="3" maxlength="200"
                       value="{{ old('titulo') }}" placeholder="Ex.: Produção cultural para editais"
                       @if ($errors->has('titulo')) aria-invalid="true" aria-describedby="erro-titulo" @endif
                       class="{{ $campo }}">
                @error('titulo') <p id="erro-titulo" class="{{ $erro }}">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-1.5">
                <label for="categoria" class="{{ $rotulo }}">Área acadêmica / categoria</label>
                <input id="categoria" name="categoria" type="text" required maxlength="120" list="areas-existentes"
                       value="{{ old('categoria') }}" placeholder="Escolha uma área existente ou digite uma nova"
                       @if ($errors->has('categoria')) aria-invalid="true" aria-describedby="erro-categoria" @endif
                       class="{{ $campo }}">
                <datalist id="areas-existentes">
                    @foreach ($categorias as $area)
                        <option value="{{ $area }}"></option>
                    @endforeach
                </datalist>
                @error('categoria') <p id="erro-categoria" class="{{ $erro }}">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-1.5">
                <label for="descricao" class="{{ $rotulo }}">
                    Ementa / descrição curta <span class="font-normal text-escult-ink-2">(opcional; pode completar depois)</span>
                </label>
                <textarea id="descricao" name="descricao" rows="4" maxlength="5000"
                          @if ($errors->has('descricao')) aria-invalid="true" aria-describedby="erro-descricao" @endif
                          class="{{ $campo }}">{{ old('descricao') }}</textarea>
                @error('descricao') <p id="erro-descricao" class="{{ $erro }}">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="min-w-0 space-y-1.5">
                    <label for="tipo_curso" class="{{ $rotulo }}">Tipo de curso</label>
                    <select id="tipo_curso" name="tipo_curso" class="{{ $campo }}">
                        <option value="fixo" @selected(old('tipo_curso', 'fixo') === 'fixo')>Fixo (aulas gravadas)</option>
                        <option value="ao_vivo" @selected(old('tipo_curso') === 'ao_vivo')>Ao vivo</option>
                    </select>
                    @error('tipo_curso') <p class="{{ $erro }}">{{ $message }}</p> @enderror
                </div>

                <div class="min-w-0 space-y-1.5">
                    <label for="vigencia" class="{{ $rotulo }}">
                        Vigência de exibição <span class="font-normal text-escult-ink-2">(opcional)</span>
                    </label>
                    <input id="vigencia" name="vigencia" type="date" value="{{ old('vigencia') }}"
                           @if ($errors->has('vigencia')) aria-invalid="true" aria-describedby="erro-vigencia" @endif
                           class="{{ $campo }}">
                    @error('vigencia') <p id="erro-vigencia" class="{{ $erro }}">{{ $message }}</p> @enderror
                </div>
            </div>

            <label class="flex cursor-pointer items-center gap-2.5">
                <input type="checkbox" name="tem_chat" value="1" @checked(old('tem_chat', '1') === '1')
                       class="h-4 w-4 rounded border-ava-borda text-ava-acao focus:ring-ava-acao">
                <span class="text-corpo text-ava-tinta">Ativar o chat do curso</span>
            </label>

            <div class="flex flex-wrap justify-end gap-2">
                <a href="/inst"
                   class="inline-flex items-center rounded-[10px] border border-ava-borda bg-white px-5 py-2.5 text-rotulo font-semibold text-ava-tinta transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                    Cancelar
                </a>
                <button type="submit"
                        class="cursor-pointer rounded-[10px] bg-ava-acao px-5 py-2.5 text-rotulo font-semibold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
                    Criar curso
                </button>
            </div>
        </form>
    </div>
</x-layouts.instrutor>
