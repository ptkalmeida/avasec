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
            // Data e hora precisam ser parseaveis: a aba Calendario monta a agenda dos
            // proximos 30 dias a partir delas (src/utils/liveSchedule.ts). Como texto
            // livre, um "junho de 2026" entraria no banco e sumiria da agenda em
            // silencio. O formato dd/mm/aaaa e o que os 4 webinars existentes ja usam.
            'date' => ['required', 'string', 'max:30', 'date_format:d/m/Y'],
            'time' => ['required', 'string', 'max:20', 'date_format:H:i'],
            'description' => ['required', 'string', 'max:2000'],
            'link' => ['required', 'string', 'max:2000'],
            'image' => ['nullable', 'string', 'max:2000'],
        ]);

    }
}
