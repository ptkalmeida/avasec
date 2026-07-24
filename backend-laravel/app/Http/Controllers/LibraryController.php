<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiRequestHelpers;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LibraryController extends Controller
{
    use ApiRequestHelpers;

    public function __construct(private readonly CatalogService $catalog) {}

    public function index(): JsonResponse
    {
        // Contrato do Node: array JSON puro (sem envelope { data: ... }).
        return response()->json($this->catalog->listLibraryItems());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        return response()->json($this->catalog->upsertLibraryItem($data), 201);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        // Espelha libraryItemSchema (Zod) do Node — mesmos limites e enum.
        return $this->validateInput($request, [
            'id' => ['sometimes', 'string', 'max:191'],
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', 'in:pdf,video,link'],
            'category' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'url' => ['required', 'string', 'max:2000'],
        ]);

    }
}
