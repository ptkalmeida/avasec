<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiRequestHelpers;
use App\Services\AuditLogger;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EnrollmentController extends Controller
{
    use ApiRequestHelpers;

    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly AuditLogger $audit,
    ) {}

    // ---------- Progresso ----------

    public function getProgress(Request $request): JsonResponse
    {
        $studentName = $request->query('studentName');

        return response()->json($this->enrollments->getProgress($studentName, $this->requester($request)));
    }

    public function upsertProgress(Request $request): JsonResponse
    {
        $data = $this->validateInput($request, [
            'studentName' => ['required', 'string', 'min:2', 'max:150'],
            'courseId' => ['required', 'string', 'max:191'],
            'completedLessons' => ['sometimes', 'array', 'max:2000'],
            'attendedLiveSessions' => ['sometimes', 'array', 'max:2000'],
        ]);
        $data['completedLessons'] ??= [];
        $data['attendedLiveSessions'] ??= [];

        return response()->json($this->enrollments->upsertProgress($data, $this->requester($request)));
    }

    // ---------- Matrícula ----------

    public function getEnrollments(Request $request): JsonResponse
    {
        return response()->json($this->enrollments->getEnrollments($this->requester($request)));
    }

    public function upsertEnrollment(Request $request, string $studentName): JsonResponse
    {
        $data = $this->validateInput($request, [
            'enrolledCourseId' => ['sometimes', 'nullable', 'string', 'max:191'],
            'enrolledAt' => ['sometimes', 'nullable', 'string'],
            'completedCourseIds' => ['sometimes', 'array', 'max:500'],
            'dropOutPenaltyUntil' => ['sometimes', 'nullable', 'string'],
        ]);

        $updated = $this->enrollments->upsertEnrollment($studentName, $data, $this->requester($request));
        $isCancellation = array_key_exists('enrolledCourseId', $data) && $data['enrolledCourseId'] === null;
        $this->audit->log(
            $request,
            $isCancellation ? 'Cancelamento de Inscrição' : 'Alteração de Matrícula',
            "Matrícula de \"{$studentName}\" atualizada.",
        );

        return response()->json($updated);
    }

    public function selfEnroll(Request $request): JsonResponse
    {
        $courseId = $this->courseId($request);
        $result = $this->enrollments->selfEnroll($courseId, $this->requester($request));
        $this->audit->log($request, 'Matrícula em Curso', "Aluno matriculou-se no curso {$courseId}.");

        return response()->json($result);
    }

    public function selfDrop(Request $request): JsonResponse
    {
        $courseId = $this->courseId($request);
        $result = $this->enrollments->selfDrop($courseId, $this->requester($request));
        $this->audit->log(
            $request,
            'Cancelamento de Inscrição',
            "Aluno cancelou a matrícula no curso {$courseId}".($result['penaltyApplied'] ? ' (restrição temporária aplicada)' : '').'.',
            $result['penaltyApplied'] ? 'WARNING' : 'SUCCESS',
        );

        return response()->json($result);
    }

    public function selfComplete(Request $request): JsonResponse
    {
        $courseId = $this->courseId($request);
        $result = $this->enrollments->selfComplete($courseId, $this->requester($request));
        $this->audit->log($request, 'Conclusão de Curso', "Aluno concluiu o curso {$courseId}.");

        return response()->json($result);
    }

    // ---------- Admissões ----------

    public function listAdmissions(Request $request): JsonResponse
    {
        return response()->json($this->enrollments->listAdmissions($this->requester($request)));
    }

    public function createAdmission(Request $request): JsonResponse
    {
        $data = $this->validateInput($request, [
            'id' => ['sometimes', 'string', 'max:191'],
            'studentName' => ['required', 'string', 'min:2', 'max:150'],
            'courseId' => ['required', 'string', 'max:191'],
            'status' => ['sometimes', 'in:pending,approved,rejected'],
        ]);

        $admission = $this->enrollments->createAdmission($data, $this->requester($request));
        $this->audit->log($request, 'Solicitação de Matrícula', "Matrícula solicitada por \"{$admission['studentName']}\" no curso {$admission['courseId']}.");

        return response()->json($admission, 201);
    }

    public function updateAdmissionStatus(Request $request, string $id): JsonResponse
    {
        $data = $this->validateInput($request, [
            'status' => ['required', 'in:approved,rejected'],
        ]);

        $updated = $this->enrollments->updateAdmissionStatus($id, $data['status'], $this->requester($request));
        $this->audit->log($request, 'Aprovação de Solicitação', "Matrícula {$id} ({$updated['studentName']}) marcada como \"{$updated['status']}\".");

        return response()->json($updated);
    }

    // ---------- helpers ----------

    private function courseId(Request $request): string
    {
        $data = $this->validateInput($request, ['courseId' => ['required', 'string', 'max:191']]);

        return $data['courseId'];
    }
}
