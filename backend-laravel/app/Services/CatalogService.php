<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventoWebinar;
use App\Models\ItemBiblioteca;
use App\Support\DataTexto;
use App\Support\SafeUrl;
use App\Support\Visibilidade;
use Illuminate\Database\Eloquent\Collection;

/**
 * Biblioteca digital e webinars — espelha src/server/services/catalogService.ts.
 * Conteúdo de catálogo de visibilidade pública; criação/atualização por upsert
 * idempotente pelo id (id informado ou gerado com prefixo + timestamp em ms).
 */
final class CatalogService
{
    /**
     * Itens da biblioteca na escada de visibilidade do papel (nulo = visitante, só o publicado).
     * Esta lista alimenta `GET /api/biblioteca`, que é público: antes ela devolvia tudo,
     * inclusive item restrito ou em rascunho, a qualquer visitante anônimo.
     *
     * @return Collection<int, ItemBiblioteca>
     */
    public function listLibraryItems(?string $role = null): Collection
    {
        $consulta = ItemBiblioteca::query();
        Visibilidade::aplicar($consulta, $role);

        return $consulta->get();
    }

    /**
     * A biblioteca como um papel a enxerga: só o que está no ar para ele, na
     * escada de `Visibilidade`, em ordem de categoria e título.
     *
     * `listLibraryItems` (a da API) devolve tudo o que não está inativado,
     * inclusive item restrito ou em rascunho — está sinalizado no plano 18
     * §21 e fica como está até o React sair, porque o painel do admin o usa.
     *
     * `href` é a URL quando ela é segura e aponta para algum lugar. Item
     * cadastrado com `#` (os seis de demonstração) sai com `href` nulo, e a
     * tela diz que o arquivo ainda não está disponível em vez de oferecer um
     * "Baixar" que não baixa nada.
     *
     * @return list<array{id:string,titulo:string,tipo:string,categoria:string,descricao:string|null,href:string|null}>
     */
    public function listLibraryItemsFor(?string $role): array
    {
        $consulta = ItemBiblioteca::query();
        Visibilidade::aplicar($consulta, $role);

        $itens = [];
        foreach ($consulta->orderBy('categoria')->orderBy('titulo')->get() as $item) {
            $url = SafeUrl::sanitize(is_string($item->url) ? $item->url : null);
            $itens[] = [
                'id' => (string) $item->id,
                'titulo' => (string) $item->titulo,
                'tipo' => (string) $item->tipo,
                'categoria' => (string) $item->categoria,
                'descricao' => is_string($item->descricao) && $item->descricao !== '' ? $item->descricao : null,
                'href' => $url,
            ];
        }

        return $itens;
    }

    /** @param array<string, mixed> $data */
    public function upsertLibraryItem(array $data): ItemBiblioteca
    {
        $id = is_string($data['id'] ?? null) ? $data['id'] : ('lib-'.$this->nowMs());

        return $this->upsert(ItemBiblioteca::class, $id, [
            'id' => $id,
            'titulo' => $data['titulo'],
            'tipo' => $data['tipo'],
            'categoria' => $data['categoria'],
            'descricao' => $data['descricao'] ?? null,
            'url' => $data['url'],
        ]);
    }

    /**
     * Webinars na escada de visibilidade do papel (nulo = visitante, só o publicado).
     * `GET /api/webinars` é público; webinar restrito ou em rascunho não pode sair dele.
     *
     * @return Collection<int, EventoWebinar>
     */
    public function listWebinars(?string $role = null): Collection
    {
        $consulta = EventoWebinar::query();
        Visibilidade::aplicar($consulta, $role);

        return $consulta->get();
    }

    /** @param array<string, mixed> $data */
    public function upsertWebinar(array $data): EventoWebinar
    {
        $id = is_string($data['id'] ?? null) ? $data['id'] : ('webinar-'.$this->nowMs());

        return $this->upsert(EventoWebinar::class, $id, [
            'id' => $id,
            'titulo' => $data['titulo'],
            'data_texto' => $data['data_texto'],
            'horario' => $data['horario'],
            /*
             * `data_evento` combina as duas colunas de texto num instante (Norma
             * C.2.4). `date` e `time` continuam como estão — são o que a agenda
             * pública exibe. Data ou hora irreconhecível deixa a coluna NULL em vez
             * de agendar o webinar num instante inventado.
             */
            'data_evento' => DataTexto::instante(
                is_string($data['data_texto']) ? $data['data_texto'] : null,
                is_string($data['horario']) ? $data['horario'] : null,
            ),
            'descricao' => $data['descricao'],
            'link' => $data['link'],
            'imagem' => $data['imagem'] ?? null,
        ]);
    }

    /**
     * Remove um webinar. Existe para a área de gestão de webinars poder desmarcar um
     * evento — antes só havia criar e listar, então um webinar agendado por engano
     * ficava na agenda pública para sempre.
     */
    public function deleteWebinar(string $id, ?string $porUserId = null, ?string $motivo = null): void
    {
        // Inativa em vez de apagar (ADR 12): o evento sai da agenda pública, mas
        // fica registrado que existiu, quem desmarcou e por quê.
        EventoWebinar::query()->find($id)?->inativar($porUserId, $motivo);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $modelClass
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    private function upsert(string $modelClass, string $id, array $attributes)
    {
        $existing = $modelClass::query()->whereKey($id)->first();
        if ($existing instanceof $modelClass) {
            $existing->fill($attributes);
            $existing->save();

            return $existing;
        }

        $model = new $modelClass;
        $model->fill($attributes);
        $model->save();

        return $model;
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
