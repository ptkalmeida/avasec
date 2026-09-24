/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';

/**
 * O elemento já saiu da tela pela rolagem?
 *
 * Existe para o cabeçalho fixo da aula: ele repetia o título que estava logo
 * abaixo, em letra grande — a aula abria com dois títulos. Agora o cabeçalho
 * só mostra o título quando o título grande sai da tela, que é quando ele
 * serve para alguma coisa.
 *
 * Começa em `false` (visível) e fica assim sem `IntersectionObserver`
 * (navegador antigo, jsdom): na falta dele, a aula mostra um título só, que
 * é o comportamento pedido — o custo é o cabeçalho não mostrar o título ao
 * rolar, e não o título aparecer duas vezes.
 *
 * `chave` refaz a observação quando o elemento troca (outra aula).
 * `margemTopo` desconta o que cobre o alto da tela (o próprio cabeçalho fixo):
 * título escondido atrás dele já está, para quem lê, fora da tela.
 */
export function useForaDaTela<T extends Element>(
  chave?: unknown,
  margemTopo = 0
): [React.RefObject<T | null>, boolean] {
  const ref = React.useRef<T | null>(null);
  const [foraDaTela, setForaDaTela] = React.useState(false);

  React.useEffect(() => {
    setForaDaTela(false);
    const alvo = ref.current;
    if (alvo === null || typeof IntersectionObserver === 'undefined') return;

    const observador = new IntersectionObserver(([entrada]) => {
      if (entrada !== undefined) setForaDaTela(!entrada.isIntersecting);
    }, { rootMargin: `-${margemTopo}px 0px 0px 0px` });
    observador.observe(alvo);

    return () => observador.disconnect();
  }, [chave, margemTopo]);

  return [ref, foraDaTela];
}
