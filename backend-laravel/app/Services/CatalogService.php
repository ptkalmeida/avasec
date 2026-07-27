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
            'description' => $data['description'],
            'link' => $data['link'],
            'image' => $data['image'] ?? null,
        ]);
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
