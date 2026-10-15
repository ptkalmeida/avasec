/*
 * JavaScript das telas em Blade.
 *
 * Alpine, e não Livewire, por decisão de 15/10/2026 registrada no plano 16. O
 * critério é o do `ui-02` da norma — "Livewire para interação dinâmica,
 * Alpine.js para comportamento simples" — aplicado ao que as 7 telas da M1
 * realmente fazem: carrossel de notícias, acordeão de dúvidas e dois botões de
 * navegação. Nenhum deles vai ao servidor.
 *
 * Livewire entra quando aparecer interação que PRECISE do servidor; até lá,
 * acrescentá-lo seria um ciclo de requisição por clique sem nada em troca.
 */
import Alpine from 'alpinejs';

declare global {
  interface Window {
    Alpine: typeof Alpine;
  }
}

window.Alpine = Alpine;
Alpine.start();
