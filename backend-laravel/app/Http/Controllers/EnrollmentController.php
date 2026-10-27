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
        $usuario_id = $request->query('usuario_id');
        $usuario_id = is_string($usuario_id) ? $usuario_id : null;

        return response()->json($this->enrollments->getProgress($usuario_id, $this->requester($request)));
    }

    public function upsertProgress(Request $request): JsonResponse
    {
        $data = $this->validateInput($request, [
            'usuario_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'curso_id' => ['required', 'string', 'max:191'],
            'aulas_concluidas' => ['sometimes', 'array', 'max:2000'],
            'sessoes_ao_vivo_assistidas' => ['sometimes', 'array', 'max:2000'],
        ]);

        return response()->json($this->enrollments->upsertProgress([
            'usuario_id' => $this->optionalString($data, 'usuario_id'),
            'curso_id' => $this->stringField($data, 'curso_id'),
            'aulas_concluidas' => $this->stringList($data, 'aulas_concluidas'),
            'sessoes_ao_vivo_assistidas' => $this->stringList($data, 'sessoes_ao_vivo_assistidas'),
        ], $this->requester($request)));
    }

    // ---------- Matrícula ----------

    public function getEnrollments(Request $request): JsonResponse
    {
        return response()->json($this->enrollments->getEnrollments($this->requester($request)));
    }

    public function upsertEnrollment(Request $request, string $usuario_id): JsonResponse
    {
        $data = $this->validateInput($request, [
            'curso_matriculado_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'matriculado_em_texto' => ['sometimes', 'nullable', 'string'],
            'cursos_concluidos_ids' => ['sometimes', 'array', 'max:500'],
            'penalidade_desistencia_ate' => ['sometimes', 'nullable', 'string'],
            'pode_multimatricula' => ['sometimes', 'boolean'],
        ]);

        // Presença de chave importa: o service só mescla campos enviados, e
        // curso_matriculado_id presente-e-nulo significa cancelamento.
        $updates = [];
        if (array_key_exists('curso_matriculado_id', $data)) {
            $updates['curso_matriculado_id'] = $this->optionalString($data, 'curso_matriculado_id');
        }
        if (array_key_exists('matriculado_em_texto', $data)) {
            $updates['matriculado_em_texto'] = $this->optionalString($data, 'matriculado_em_texto');
        }
        if (array_key_exists('cursos_concluidos_ids', $data)) {
            $updates['cursos_concluidos_ids'] = $this->stringList($data, 'cursos_concluidos_ids');
        }
        if (array_key_exists('penalidade_desistencia_ate', $data)) {
            $updates['penalidade_desistencia_ate'] = $this->optionalString($data, 'penalidade_desistencia_ate');
        }
        if (array_key_exists('pode_multimatricula', $data)) {
            $updates['pode_multimatricula'] = $request->boolean('pode_multimatricula');
        }

        $updated = $this->enrollments->upsertEnrollment($usuario_id, $updates, $this->requester($request));
        $isCancellation = array_key_exists('curso_matriculado_id', $updates) && $updates['curso_matriculado_id'] === null;
        $displayName = $this->optionalString($updated, 'nome_aluno') ?? $usuario_id;
        $this->audit->log(
            $request,
            $isCancellation ? 'Cancelamento de Inscrição' : 'Alteração de Matrícula',
            "Matrícula de \"{$displayName}\" atualizada.",
        );

        return response()->json($updated);
    }

    public function selfEnroll(Request $request): JsonResponse
    {
        $curso_id = $this->curso_id($request);
        $result = $this->enrollments->selfEnroll($curso_id, $this->requester($request));
        $this->audit->log($request, 'Matrícula em Curso', "Aluno matriculou-se no curso {$curso_id}.");

        return response()->json($result);
    }

    public function selfDrop(Request $request): JsonResponse
    {
        $curso_id = $this->curso_id($request);
        $result = $this->enrollments->selfDrop($curso_id, $this->requester($request));
        $this->audit->log(
            $request,
            'Cancelamento de Inscrição',
            "Aluno cancelou a matrícula no curso {$curso_id}".($result['penaltyApplied'] ? ' (restrição temporária aplicada)' : '').'.',
            $result['penaltyApplied'] ? 'WARNING' : 'SUCCESS',
        );

        return response()->json($result);
    }

    public function selfComplete(Request $request): JsonResponse
    {
        $curso_id = $this->curso_id($request);
        $result = $this->enrollments->selfComplete($curso_id, $this->requester($request));
        $this->audit->log($request, 'Conclusão de Curso', "Aluno concluiu o curso {$curso_id}.");

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
            'usuario_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'curso_id' => ['required', 'string', 'max:191'],
            'status' => ['sometimes', 'in:pending,approved,rejected'],
        ]);

        $curso_id = $this->stringField($data, 'curso_id');
        $admission = $this->enrollments->createAdmission([
            'id' => $this->optionalString($data, 'id'),
            'usuario_id' => $this->optionalString($data, 'usuario_id'),
            'curso_id' => $curso_id,
        ], $this->requester($request));
        $nome_aluno = $this->optionalString($admission, 'nome_aluno') ?? '';
        $this->audit->log($request, 'Solicitação de Matrícula', "Matrícula solicitada por \"{$nome_aluno}\" no curso {$curso_id}.");

        return response()->json($admission, 201);
    }

    public function updateAdmissionStatus(Request $request, string $id): JsonResponse
    {
        $data = $this->validateInput($request, [
            'status' => ['required', 'in:approved,rejected'],
        ]);

        $status = $this->stringField($data, 'status');
        $updated = $this->enrollments->updateAdmissionStatus($id, $status, $this->requester($request));
        $nome_aluno = $this->optionalString($updated, 'nome_aluno') ?? '';
        $this->audit->log($request, 'Aprovação de Solicitação', "Matrícula {$id} ({$nome_aluno}) marcada como \"{$status}\".");

        return response()->json($updated);
    }

    // ---------- helpers ----------

    private function curso_id(Request $request): string
    {
        $data = $this->validateInput($request, ['curso_id' => ['required', 'string', 'max:191']]);

        return $this->stringField($data, 'curso_id');
    }
}
