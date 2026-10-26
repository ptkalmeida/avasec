<?php

use App\Services\SitePageContentService;
use Livewire\Component;

/**
 * Acordeão das dúvidas frequentes.
 *
 * Componente de arquivo único do Livewire 4: a classe e a marcação no mesmo
 * lugar, que é o formato que o `make:livewire` gera nesta versão.
 *
 * O conteúdo vem do mesmo `SitePageContentService` que alimentava o React —
 * mesma chave de página, mesmos padrões de fábrica, mesma edição pelo admin.
 *
 * ## O que custa, dito com clareza
 *
 * Abrir e fechar uma pergunta é uma ida ao servidor. No React era estado local
 * (`useState`) e respondia sem rede. A decisão de usar Livewire em vez de
 * comportamento de navegador foi do responsável, em 15/10/2026, e está
 * registrada no plano 16 — fica anotado aqui porque é o tipo de custo que
 * desaparece do código e reaparece como "o site está lento" meses depois.
 *
 * Duas mitigações já aplicadas: `wire:loading.attr="disabled"` evita a fila de
 * cliques repetidos enquanto a resposta não chega, e o `wire:key` em cada item
 * impede o Livewire de reaproveitar o nó errado ao recompor a lista.
 */
new class extends Component
{
    /** Id da pergunta aberta. `null` = todas fechadas, que é o estado inicial. */
    public ?string $abertaId = null;

    /** @var array<string, mixed> */
    public array $pagina = [];

    public function mount(SitePageContentService $conteudo): void
    {
        $this->pagina = $conteudo->get('duvidas');
    }

    /** Clicar na pergunta aberta fecha; em outra, troca. */
    public function alternar(string $id): void
    {
        $this->abertaId = $this->abertaId === $id ? null : $id;
    }
}; ?>

<div class="space-y-3 max-w-3xl mx-auto text-left">
    @foreach ($pagina['items'] as $faq)
        @php($aberta = $abertaId === $faq['id'])

        {{-- `wire:key` obrigatório em lista: sem ele o Livewire pode reaproveitar
             o nó de outro item ao recompor, e a resposta aberta migra de pergunta. --}}
        <div wire:key="faq-{{ $faq['id'] }}" class="border border-ava-borda rounded-xl bg-white transition-all">
            <button
                type="button"
                wire:click="alternar('{{ $faq['id'] }}')"
                wire:loading.attr="disabled"
                aria-expanded="{{ $aberta ? 'true' : 'false' }}"
                aria-controls="resposta-{{ $faq['id'] }}"
                class="w-full flex justify-between items-center gap-3 px-5 py-4 text-rotulo font-bold text-ava-tinta hover:text-ava-acao text-left cursor-pointer rounded-xl"
            >
                <span>{{ $faq['question'] }}</span>

                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true"
                     @class([
                         'h-5 w-5 text-ava-acao shrink-0 transition-transform',
                         'rotate-180' => $aberta,
                     ])>
                    <path d="m6 9 6 6 6-6"/>
                </svg>
            </button>

            @if ($aberta)
                <div id="resposta-{{ $faq['id'] }}"
                     class="px-5 pb-4 pt-3 border-t border-ava-borda text-apoio text-escult-ink-2 leading-relaxed">
                    {{ $faq['answer'] }}
                </div>
            @endif
        </div>
    @endforeach
</div>
