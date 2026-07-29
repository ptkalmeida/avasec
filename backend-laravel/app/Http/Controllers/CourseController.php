<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiRequestHelpers;
use App\Rules\VideoUrlRule;
use App\Services\AuditLogger;
use App\Services\CourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CourseController extends Controller
{
    use ApiRequestHelpers;

    public function __construct(
        private readonly CourseService $courses,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->courses->listCourses());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, false);
        $course = $this->courses->createCourse($data, $this->requester($request));
        $title = $this->optionalString($course, 'title') ?? '';
        $this->audit->log($request, 'Criação de Curso', "Curso \"{$title}\" criado.");

        return response()->json($course, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $this->validated($request, true);
        $course = $this->courses->updateCourse($id, $data, $this->requester($request));
        $title = $this->optionalString($course, 'title') ?? '';
        $this->audit->log($request, 'Alteração de Curso', "Curso \"{$title}\" ({$id}) atualizado.");

        return response()->json($course);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->courses->deleteCourse($id, $this->requester($request));
        $this->audit->log($request, 'Exclusão de Curso', "Curso {$id} excluído.", 'WARNING');

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial): array
    {
        // Espelha createCourseSchema / updateCourseSchema (Zod). No update (partial),
        // os campos de topo tornam-se opcionais (sometimes).
        $req = $partial ? 'sometimes' : 'required';
        $rules = [
            'id' => ['sometimes', 'string', 'max:191'],
            'title' => [$req, 'string', 'min:3', 'max:200'],
            'description' => [$req, 'string', 'min:10', 'max:5000'],
            'category' => [$req, 'string', 'min:1', 'max:120'],
            'thumbnail' => [$req, 'string', 'min:1', 'max:2000'],
            // ADR 10: autoria por instructorId (admin); instructorName é aceito
            // apenas por compat de payload e IGNORADO — display deriva do User.
            'instructorId' => ['sometimes', 'nullable', 'string', 'max:191'],
            'instructorName' => ['sometimes', 'nullable', 'string', 'max:150'],
            'coverImage' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'courseType' => ['sometimes', 'in:fixo,ao_vivo'],
            'hasChat' => ['sometimes', 'boolean'],
            'minAttendance' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'contractExpirationDate' => ['sometimes', 'nullable', 'string', 'max:60'],
            'areaTematica' => ['sometimes', 'nullable', 'string', 'max:150'],
            'cargaHoraria' => ['sometimes', 'integer', 'min:1', 'max:2000'],
            'modalidade' => ['sometimes', 'nullable', 'string', 'max:60'],
            'nivel' => ['sometimes', 'nullable', 'string', 'max:60'],
            'emiteCertificado' => ['sometimes', 'boolean'],
            'statusCurso' => ['sometimes', 'nullable', 'string', 'max:60'],
            // Aulas aninhadas
            'lessons' => ['sometimes', 'array'],
            'lessons.*.id' => ['sometimes', 'string', 'max:191'],
            'lessons.*.title' => ['required_with:lessons', 'string', 'min:1', 'max:200'],
            'lessons.*.duration' => ['required_with:lessons', 'string', 'min:1', 'max:30'],
            'lessons.*.videoUrl' => ['sometimes', 'nullable', 'string', 'max:2000', new VideoUrlRule],
            'lessons.*.content' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'lessons.*.order' => ['required_with:lessons', 'integer', 'min:0'],
            'lessons.*.documents' => ['sometimes', 'array'],
            'lessons.*.documents.*.title' => ['required_with:lessons.*.documents', 'string', 'min:1', 'max:200'],
            'lessons.*.documents.*.type' => ['required_with:lessons.*.documents', 'in:pdf,doc,url,drive,outro'],
            'lessons.*.documents.*.url' => ['required_with:lessons.*.documents', 'string', 'min:1', 'max:2000'],
            'lessons.*.documents.*.size' => ['sometimes', 'nullable', 'string', 'max:30'],
            // Sessões ao vivo aninhadas
            'liveSessions' => ['sometimes', 'array'],
            'liveSessions.*.id' => ['sometimes', 'string', 'max:191'],
            'liveSessions.*.title' => ['required_with:liveSessions', 'string', 'min:1', 'max:200'],
            'liveSessions.*.scheduledAt' => ['required_with:liveSessions', 'string', 'min:1', 'max:100'],
            'liveSessions.*.durationMinutes' => ['required_with:liveSessions', 'integer', 'min:1', 'max:600'],
            'liveSessions.*.meetingLink' => ['required_with:liveSessions', 'string', 'min:1', 'max:2000'],
            'liveSessions.*.isLive' => ['sometimes', 'boolean'],
        ];

        return $this->validateKeepingAll($request, $rules);
    }
}
