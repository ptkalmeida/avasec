@props(['url' => null, 'titulo' => 'Vídeo da aula'])

{{--
    O vídeo da aula.

    Traduz `src/components/shared/VideoPlayer.tsx`. Quem decide o provedor é a
    própria URL (ADR 08), e quem a lê é `App\Support\VideoSource` — o mesmo
    `parse` que a API já usa, e não uma segunda interpretação escrita aqui.

    YouTube vira `iframe` no domínio `-nocookie`; arquivo vira `<video>` nativo.
    URL que o parser recusa não vira player nenhum: aparece o aviso. Um `iframe`
    com endereço inválido mostraria o erro do YouTube dentro da aula, e o aluno
    leria aquilo como defeito da escola.

    Sem `autoplay`: vídeo que começa sozinho atropela leitor de tela e consome
    dados de quem está no celular.
--}}

@php($fonte = \App\Support\VideoSource::tryParse($url))

@if ($fonte === null)
    <div class="flex aspect-video w-full flex-col items-center justify-center gap-2 rounded-xl border border-ava-borda bg-ava-faixa text-escult-ink-3">
        <span class="text-rotulo font-semibold">Vídeo indisponível</span>
        <span class="text-apoio">O endereço cadastrado para este vídeo não pôde ser lido.</span>
    </div>
@elseif ($fonte->provider === \App\Support\VideoProvider::Youtube)
    <div class="aspect-video w-full overflow-hidden rounded-xl border border-ava-borda bg-ava-marinho">
        <iframe
            class="h-full w-full"
            src="https://www.youtube-nocookie.com/embed/{{ $fonte->videoId }}?autoplay=0&rel=0&modestbranding=1"
            title="{{ $titulo }}"
            loading="lazy"
            referrerpolicy="strict-origin-when-cross-origin"
            allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowfullscreen></iframe>
    </div>
@else
    <div class="aspect-video w-full overflow-hidden rounded-xl border border-ava-borda bg-black">
        <video class="h-full w-full object-contain" controls playsinline preload="metadata"
               src="{{ $fonte->canonicalUrl }}" title="{{ $titulo }}">
            <p class="p-4 text-apoio text-white">
                Seu navegador não reproduz este vídeo.
                <a href="{{ $fonte->canonicalUrl }}" class="underline">Baixar o arquivo</a>.
            </p>
        </video>
    </div>
@endif
