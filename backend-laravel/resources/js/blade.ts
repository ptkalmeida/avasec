/*
 * JavaScript das telas em Blade.
 *
 * Está vazio de propósito, e isso é o esperado.
 *
 * A interatividade das telas migradas é do **Livewire** (decisão de 15/10/2026),
 * e o Livewire traz o próprio pacote: ele injeta o `livewire.js`, que já embute
 * o Alpine. Importar Alpine aqui também carregaria o runtime DUAS vezes na mesma
 * página — duas instâncias disputando os mesmos atributos do DOM.
 *
 * Este arquivo continua existindo porque é a entrada do Vite para scripts que
 * não sejam de componente (telemetria, polyfill, comportamento global). Quando
 * houver o primeiro, ele entra aqui.
 */
export {};
