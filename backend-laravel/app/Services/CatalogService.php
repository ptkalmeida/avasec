<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LibraryItem;
use App\Models\WebinarEvent;
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
    /** @return Collection<int, LibraryItem> */
    public function listLibraryItems(): Collection
    {
        return LibraryItem::all();
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
     * @return list<array{id:string,title:string,type:string,category:string,description:string|null,href:string|null}>
     */
    public function listLibraryItemsFor(?string $role): array
    {
        $consulta = LibraryItem::query();
        Visibilidade::aplicar($consulta, $role);

        $itens = [];
        foreach ($consulta->orderBy('category')->orderBy('title')->get() as $item) {
            $url = SafeUrl::sanitize(is_string($item->url) ? $item->url : null);
            $itens[] = [
                'id' => (string) $item->id,
                'title' => (string) $item->title,
                'type' => (string) $item->type,
                'category' => (string) $item->category,
                'description' => is_string($item->description) && $item->description !== '' ? $item->description : null,
                'href' => $url,
            ];
        }

        return $itens;
    }

    /** @param array<string, mixed> $data */
    public function upsertLibraryItem(array $data): LibraryItem
    {
        $id = is_string($data['id'] ?? null) ? $data['id'] : ('lib-'.$this->nowMs());

        return $this->upsert(LibraryItem::class, $id, [
            'id' => $id,
            'title' => $data['title'],
            'type' => $data['type'],
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'url' => $data['url'],
        ]);
    }

    /** @return Collection<int, WebinarEvent> */
    public function listWebinars(): Collection
    {
        return WebinarEvent::all();
    }

    /** @param array<string, mixed> $data */
    public function upsertWebinar(array $data): WebinarEvent
    {
        $id = is_string($data['id'] ?? null) ? $data['id'] : ('webinar-'.$this->nowMs());

        return $this->upsert(WebinarEvent::class, $id, [
            'id' => $id,
            'title' => $data['title'],
            'date' => $data['date'],
            'time' => $data['time'],
            /*
             * `dataEvento` combina as duas colunas de texto num instante (Norma
             * C.2.4). `date` e `time` continuam como estão — são o que a agenda
             * pública exibe. Data ou hora irreconhecível deixa a coluna NULL em vez
             * de agendar o webinar num instante inventado.
             */
            'dataEvento' => DataTexto::instante(
                is_string($data['date']) ? $data['date'] : null,
                is_string($data['time']) ? $data['time'] : null,
            ),
            'description' => $data['description'],
            'link' => $data['link'],
            'image' => $data['image'] ?? null,
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
        WebinarEvent::query()->find($id)?->inativar($porUserId, $motivo);
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
