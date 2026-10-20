<?php

use App\Services\SitePageContentService;
use Livewire\Component;

/**
 * Busca geral do cabeçalho: cursos e notícias, num painel abaixo do campo.
 *
 * ## Por que esta é a primeira tela com Livewire de verdade
 *
 * O plano 16 previa que o Livewire estrearia no filtro do catálogo, "por
 * necessidade demonstrada". A skill `redesenhar-ava-lms` removeu os filtros do
 * catálogo, então a necessidade mudou de lugar: a busca do cabeçalho é a que
 * sobrou, e ela é a mais interativa das duas — consulta a cada tecla.
 *
 * ## Onde ela procura, e por que NÃO é no banco
 *
 * Nos mesmos dois lugares do React: a vitrine de `config/catalogo_publico.php`
 * e as notícias do `SitePageContentService`. A vitrine do portal nunca leu a
 * tabela `Course` — o arquivo de configuração explica o levantamento.
 *
 * Trocá-la pelo banco aqui pareceria um avanço e seria uma regressão: o
 * autocompletar público passaria a oferecer os registros de teste que existem
 * na tabela, e a busca do Blade devolveria resultados diferentes da busca do
 * React na mesma sessão da mesma pessoa.
 *
 * ## O que ela NÃO faz, e por quê
 *
 * Não existe página pública de detalhe do curso — o handoff registra isso como
 * tela que falta. O React resolvia abrindo o catálogo já filtrado naquele
 * curso; aqui o resultado leva ao catálogo com o nome no endereço
 * (`/cursos?curso=`), que é a mesma promessa, agora linkável. Prometer uma
 * página de curso que não existe seria o defeito de rótulo que o React já teve.
 *
 * ## Custo, dito antes de alguém descobrir em produção
 *
 * Cada tecla é uma ida ao servidor. `wire:model.live.debounce.300ms` junta as
 * teclas de uma digitação normal numa consulta só, e abaixo de dois caracteres
 * não se busca nada: "a" traria a vitrine inteira sem ajudar ninguém.
 */
new class extends Component
{
    /** Máximo de resultados por grupo. Painel é atalho, não página de busca. */
    private const LIMITE = 5;

    /** Nada é consultado abaixo disto. */
    private const MINIMO = 2;

    public string $termo = '';

    /** @var list<array{title:string,category:string}> */
    public array $cursos = [];

    /** @var list<array{title:string,tag:string|null}> */
    public array $noticias = [];

    public function updatedTermo(SitePageContentService $conteudo): void
    {
        $termo = trim($this->termo);

        if (mb_strlen($termo) < self::MINIMO) {
            $this->cursos = [];
            $this->noticias = [];

            return;
        }

        $this->cursos = $this->buscarCursos($termo);
        $this->noticias = $this->buscarNoticias($conteudo, $termo);
    }

    /**
     * Procura no título e na descrição — os mesmos dois campos do React. A
     * categoria fica de fora de propósito: lá ela também está fora, e incluí-la
     * faria "arte" devolver a vitrine inteira de uma categoria só.
     *
     * @return list<array{title:string,category:string}>
     */
    private function buscarCursos(string $termo): array
    {
        $achados = [];

        /** @var list<array<string, string>> $vitrine */
        $vitrine = config('catalogo_publico');

        foreach ($vitrine as $curso) {
            $alvo = $curso['title'].' '.$curso['description'];

            if (mb_stripos($alvo, $termo) === false) {
                continue;
            }

            $achados[] = ['title' => $curso['title'], 'category' => $curso['category']];

            if (count($achados) === self::LIMITE) {
                break;
            }
        }

        return $achados;
    }

    /** @return list<array{title:string,tag:string|null}> */
    private function buscarNoticias(SitePageContentService $conteudo, string $termo): array
    {
        $itens = $conteudo->get('noticias')['items'] ?? [];
        $achados = [];

        foreach ($itens as $item) {
            $alvo = ($item['title'] ?? '').' '.($item['description'] ?? '').' '.($item['tag'] ?? '');

            if (mb_stripos($alvo, $termo) === false) {
                continue;
            }

            $achados[] = [
                'title' => (string) ($item['title'] ?? ''),
                'tag' => isset($item['tag']) ? (string) $item['tag'] : null,
            ];

            if (count($achados) === self::LIMITE) {
                break;
            }
        }

        return $achados;
    }

    /** Fecha o painel e limpa o campo — o que o Escape fazia no React. */
    public function limpar(): void
    {
        $this->termo = '';
        $this->cursos = [];
        $this->noticias = [];
    }
}; ?>

@php($temTermo = mb_strlen(trim($termo)) >= 2)

<div class="relative z-50">
    <div class="flex w-[230px] items-center rounded-[10px] border border-ava-borda bg-ava-faixa py-2.5 pl-3 pr-2 transition-colors focus-within:ring-2 focus-within:ring-ava-acao/40">
        <svg class="mr-2 h-4 w-4 shrink-0 text-escult-ink-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 3.473 9.772l3.377 3.378a.75.75 0 1 0 1.061-1.06l-3.378-3.378A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" clip-rule="evenodd" />
        </svg>

        <label for="busca-do-portal" class="sr-only">Buscar cursos e notícias no portal</label>
        <input
            id="busca-do-portal"
            type="search"
            wire:model.live.debounce.300ms="termo"
            wire:keydown.escape="limpar"
            placeholder="Buscar cursos e notícias"
            autocomplete="off"
            role="combobox"
            aria-expanded="{{ $temTermo ? 'true' : 'false' }}"
            aria-controls="resultados-da-busca"
            class="w-full border-0 bg-transparent text-apoio font-medium text-ava-tinta outline-none placeholder:text-escult-ink-3"
        >

        @if ($termo !== '')
            <button type="button" wire:click="limpar" aria-label="Limpar busca"
                    class="shrink-0 cursor-pointer rounded-full p-0.5 text-escult-ink-3 hover:bg-ava-borda hover:text-ava-tinta">
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                </svg>
            </button>
        @endif
    </div>

    @if ($temTermo)
        <div id="resultados-da-busca" role="listbox"
             class="absolute right-0 top-[calc(100%+8px)] z-[100] max-h-[350px] w-80 space-y-3.5 overflow-y-auto rounded-[14px] border border-ava-borda bg-white p-4 text-left shadow-xl">

            <span class="block text-apoio font-semibold text-escult-ink-3" wire:loading.remove>Resultados da busca</span>
            <span class="block text-apoio font-semibold text-escult-ink-3" wire:loading>Buscando…</span>

            @if ($cursos === [] && $noticias === [])
                <p class="py-2 text-apoio text-escult-ink-2">Nada encontrado para "{{ $termo }}".</p>
            @endif

            @if ($cursos !== [])
                <div class="space-y-1.5">
                    <span class="block text-apoio font-semibold text-ava-acao">Cursos ({{ count($cursos) }})</span>
                    @foreach ($cursos as $curso)
                        <a wire:key="curso-{{ $loop->index }}"
                           href="/cursos?curso={{ urlencode($curso['title']) }}"
                           class="flex items-center gap-2 rounded-[10px] p-2 transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-ava-lilas-fundo text-xs font-bold text-ava-lilas" aria-hidden="true">C</span>
                            <span class="min-w-0">
                                <span class="block truncate text-apoio font-semibold leading-snug text-ava-tinta">{{ $curso['title'] }}</span>
                                <span class="block truncate text-apoio text-escult-ink-3">{{ $curso['category'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($noticias !== [])
                <div class="space-y-1.5 border-t border-ava-borda pt-2">
                    <span class="block text-apoio font-semibold text-ava-acao">Notícias ({{ count($noticias) }})</span>
                    @foreach ($noticias as $noticia)
                        {{-- O termo vai junto: a página de notícias filtra por `?busca=`,
                             e sem ele o resultado clicado abriria a lista inteira. --}}
                        <a wire:key="noticia-{{ $loop->index }}"
                           href="/noticias?busca={{ urlencode($noticia['title']) }}"
                           class="flex items-center gap-2 rounded-[10px] p-2 transition-colors hover:bg-ava-faixa focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-ava-icone-fundo text-xs font-bold text-ava-acao" aria-hidden="true">N</span>
                            <span class="min-w-0">
                                <span class="block truncate text-apoio font-semibold leading-snug text-ava-tinta">{{ $noticia['title'] }}</span>
                                <span class="block truncate text-apoio text-escult-ink-3">{{ $noticia['tag'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
