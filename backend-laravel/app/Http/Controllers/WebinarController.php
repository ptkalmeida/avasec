<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiRequestHelpers;
use App\Rules\SafeUrlRule;
use App\Services\AuditLogger;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WebinarController extends Controller
{
    use ApiRequestHelpers;

    public function __construct(
        private readonly CatalogService $catalog,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->catalog->listWebinars());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        return response()->json($this->catalog->upsertWebinar($data), 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->catalog->deleteWebinar($id);
        $this->audit->log($request, 'Exclusão de Webinar', "Webinar {$id} removido da agenda.", 'WARNING');

        return response()->json(['success' => true]);
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
            // O link passou a ser RENDERIZADO num href (botao "Acessar sala" da aba
            // Calendario), entao entra na mesma regra das outras URLs do sistema:
            // sem isso, `javascript:...` num webinar executaria na sessao de quem
            // clicasse. Os 4 webinars da carga inicial tem link '#', que a regra
            // recusa — ao editar um deles, e preciso informar o link de verdade.
            'link' => ['required', 'string', 'max:2000', new SafeUrlRule],
            'image' => ['nullable', 'string', 'max:2000'],
        ]);

    }
}
