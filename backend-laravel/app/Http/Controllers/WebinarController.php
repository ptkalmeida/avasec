<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiRequestHelpers;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WebinarController extends Controller
{
    use ApiRequestHelpers;

    public function __construct(private readonly CatalogService $catalog) {}

    public function index(): JsonResponse
    {
        return response()->json($this->catalog->listWebinars());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        return response()->json($this->catalog->upsertWebinar($data), 201);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        // Espelha webinarSchema (Zod) do Node.
        return $this->validateInput($request, [
            'id' => ['sometimes', 'string', 'max:191'],
            'title' => ['required', 'string', 'max:200'],
            'date' => ['required', 'string', 'max:30'],
            'time' => ['required', 'string', 'max:20'],
            'description' => ['required', 'string', 'max:2000'],
            'link' => ['required', 'string', 'max:2000'],
            'image' => ['nullable', 'string', 'max:2000'],
        ]);

    }
}
