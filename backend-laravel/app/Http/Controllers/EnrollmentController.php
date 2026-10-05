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
        $userId = $request->query('userId');
        $userId = is_string($userId) ? $userId : null;

        return response()->json($this->enrollments->getProgress($userId, $this->requester($request)));
    }

    public function upsertProgress(Request $request): JsonResponse
    {
        $data = $this->validateInput($request, [
            'userId' => ['sometimes', 'nullable', 'string', 'max:191'],
            'courseId' => ['required', 'string', 'max:191'],
            'completedLessons' => ['sometimes', 'array', 'max:2000'],
            'attendedLiveSessions' => ['sometimes', 'array', 'max:2000'],
        ]);

        return response()->json($this->enrollments->upsertProgress([
            'userId' => $this->optionalString($data, 'userId'),
            'courseId' => $this->stringField($data, 'courseId'),
            'completedLessons' => $this->stringList($data, 'completedLessons'),
            'attendedLiveSessions' => $this->stringList($data, 'attendedLiveSessions'),
        ], $this->requester($request)));
    }

    // ---------- Matrícula ----------

    public function getEnrollments(Request $request): JsonResponse
    {
        return response()->json($this->enrollments->getEnrollments($this->requester($request)));
    }

    public function upsertEnrollment(Request $request, string $userId): JsonResponse
    {
        $data = $this->validateInput($request, [
            'enrolledCourseId' => ['sometimes', 'nullable', 'string', 'max:191'],
            'enrolledAt' => ['sometimes', 'nullable', 'string'],
            'completedCourseIds' => ['sometimes', 'array', 'max:500'],
            'dropOutPenaltyUntil' => ['sometimes', 'nullable', 'string'],
            'canMultiEnroll' => ['sometimes', 'boolean'],
        ]);

        // Presença de chave importa: o service só mescla campos enviados, e
        // enrolledCourseId presente-e-nulo significa cancelamento.
        $updates = [];
        if (array_key_exists('enrolledCourseId', $data)) {
            $updates['enrolledCourseId'] = $this->optionalString($data, 'enrolledCourseId');
        }
        if (array_key_exists('enrolledAt', $data)) {
            $updates['enrolledAt'] = $this->optionalString($data, 'enrolledAt');
        }
        if (array_key_exists('completedCourseIds', $data)) {
            $updates['completedCourseIds'] = $this->stringList($data, 'completedCourseIds');
        }
        if (array_key_exists('dropOutPenaltyUntil', $data)) {
            $updates['dropOutPenaltyUntil'] = $this->optionalString($data, 'dropOutPenaltyUntil');
        }
        if (array_key_exists('canMultiEnroll', $data)) {
            $updates['canMultiEnroll'] = $request->boolean('canMultiEnroll');
        }

        $updated = $this->enrollments->upsertEnrollment($userId, $updates, $this->requester($request));
        $isCancellation = array_key_exists('enrolledCourseId', $updates) && $updates['enrolledCourseId'] === null;
        $displayName = $this->optionalString($updated, 'studentName') ?? $userId;
        $this->audit->log(
            $request,
            $isCancellation ? 'Cancelamento de Inscrição' : 'Alteração de Matrícula',
            "Matrícula de \"{$displayName}\" atualizada.",
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
            'userId' => ['sometimes', 'nullable', 'string', 'max:191'],
            'courseId' => ['required', 'string', 'max:191'],
            'status' => ['sometimes', 'in:pending,approved,rejected'],
        ]);

        $courseId = $this->stringField($data, 'courseId');
        $admission = $this->enrollments->createAdmission([
            'id' => $this->optionalString($data, 'id'),
            'userId' => $this->optionalString($data, 'userId'),
            'courseId' => $courseId,
        ], $this->requester($request));
        $studentName = $this->optionalString($admission, 'studentName') ?? '';
        $this->audit->log($request, 'Solicitação de Matrícula', "Matrícula solicitada por \"{$studentName}\" no curso {$courseId}.");

        return response()->json($admission, 201);
    }

    public function updateAdmissionStatus(Request $request, string $id): JsonResponse
    {
        $data = $this->validateInput($request, [
            'status' => ['required', 'in:approved,rejected'],
        ]);

        $status = $this->stringField($data, 'status');
        $updated = $this->enrollments->updateAdmissionStatus($id, $status, $this->requester($request));
        $studentName = $this->optionalString($updated, 'studentName') ?? '';
        $this->audit->log($request, 'Aprovação de Solicitação', "Matrícula {$id} ({$studentName}) marcada como \"{$status}\".");

        return response()->json($updated);
    }

    // ---------- helpers ----------

    private function courseId(Request $request): string
    {
        $data = $this->validateInput($request, ['courseId' => ['required', 'string', 'max:191']]);

        return $this->stringField($data, 'courseId');
    }
}
