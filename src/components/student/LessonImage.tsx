/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState } from 'react';
import { ImageOff } from 'lucide-react';
import { safeUrl } from '../../utils/safeUrl';

interface LessonImageProps {
  url: string;
  /** Descrição obrigatória: é o que resta quando a figura não chega. */
  alt: string;
  caption: string | null;
  /** Classes vindas de TONES, para a paleta continuar num lugar só. */
  frameClass: string;
  captionClass: string;
}

/**
 * Imagem no corpo da aula.
 *
 * Duas barreiras, porque o endereço vem de conteúdo autorado: `safeUrl` recusa
 * esquema perigoso antes de montar o `src` — este é o primeiro `src` do projeto a
 * passar por ele, até aqui só `href` era filtrado — e o `onError` cobre o que a
 * validação não alcança (arquivo removido do disco, rede da escola bloqueando).
 * Nos dois casos o aluno lê a descrição no lugar da figura, em vez de encarar o
 * ícone quebrado do navegador.
 *
 * Sem `React.lazy` de propósito, ao contrário do bloco de código: não há
 * dependência pesada a adiar, e o Suspense acrescentaria justamente o salto de
 * layout que o `min-h` abaixo tenta conter.
 *
 * A legenda é escrita ACIMA no texto da aula (mesma regra do bloco de código) e
 * renderizada ABAIXO da figura, que é a convenção de material didático. A
 * divergência com o bloco de código é intencional — não "conserte".
 */
export const LessonImage: React.FC<LessonImageProps> = ({ url, alt, caption, frameClass, captionClass }) => {
  const src = safeUrl(url);
  const [falhou, setFalhou] = useState(false);
  const [carregou, setCarregou] = useState(false);

  const indisponivel = src === null || falhou;

  return (
    <figure className="my-5 space-y-2">
      <div
        className={`overflow-hidden rounded-xl border ${frameClass} ${carregou || indisponivel ? '' : 'min-h-[140px]'}`}
      >
        {indisponivel ? (
          <div className="flex flex-col items-center justify-center gap-1.5 px-4 py-8 text-center">
            <ImageOff className="h-7 w-7 text-escult-ink-2" aria-hidden />
            <span className="text-apoio font-bold text-escult-ink-2">Imagem indisponível</span>
            {alt.trim() !== '' && <span className="text-apoio text-escult-ink-2">{alt}</span>}
          </div>
        ) : (
          <img
            src={src}
            alt={alt}
            loading="lazy"
            decoding="async"
            referrerPolicy="no-referrer"
            onLoad={() => setCarregou(true)}
            onError={() => setFalhou(true)}
            // Altura limitada: foto de celular em retrato ocuparia três telas.
            // A eliminação real do salto pediria width/height vindos do upload.
            className="mx-auto block h-auto w-full max-h-[70vh] object-contain"
          />
        )}
      </div>

      {caption !== null && (
        <figcaption className={`text-apoio font-bold ${captionClass}`}>{caption}</figcaption>
      )}
    </figure>
  );
};
