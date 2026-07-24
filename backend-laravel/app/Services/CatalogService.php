<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LibraryItem;
use App\Models\WebinarEvent;
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

    /** @param array<string, mixed> $data */
    public function upsertLibraryItem(array $data): LibraryItem
    {
        $id = $data['id'] ?? ('lib-'.$this->nowMs());

        return $this->upsert(new LibraryItem, $id, [
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
        $id = $data['id'] ?? ('webinar-'.$this->nowMs());

        return $this->upsert(new WebinarEvent, $id, [
            'id' => $id,
            'title' => $data['title'],
            'date' => $data['date'],
            'time' => $data['time'],
            'description' => $data['description'],
            'link' => $data['link'],
            'image' => $data['image'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  TModel  $model
     * @return TModel
     */
    private function upsert($model, string $id, array $attributes)
    {
        $existing = $model->newQuery()->find($id);
        if ($existing !== null) {
            $existing->fill($attributes);
            $existing->save();

            return $existing;
        }

        $model->fill($attributes);
        $model->save();

        return $model;
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
