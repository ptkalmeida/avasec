<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\AcademicRequest;
use App\Support\Identity;
use Carbon\CarbonImmutable;

/**
 * Solicitações acadêmicas ("justificativas") — espelha src/server/services/requestService.ts.
 * Aluno só cria/vê as próprias; instrutor/admin acompanham todas (secretaria centralizada).
 */
final class RequestService
{
    /**
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listAcademicRequests(array $requester): array
    {
        $q = AcademicRequest::query();
        if ($requester['role'] === 'student') {
            Identity::applyOwnRows($q, $requester);
        }

        return $q->orderBy('submittedAt', 'desc')->get()->map->toArray()->all();
    }

    /**
     * @param  array{studentName:string,type:string,description:string,courseTitle?:string|null}  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function createAcademicRequest(array $input, array $requester): array
    {
        if ($requester['role'] === 'student' && $input['studentName'] !== $requester['name']) {
            throw ApiException::forbidden('Você só pode enviar solicitações em seu próprio nome.');
        }

        $userId = Identity::resolveStudentUserId($input['studentName'], $requester);

        return AcademicRequest::query()->create([
            'id' => 'req-'.(int) round(microtime(true) * 1000),
            'studentName' => $input['studentName'],
            'userId' => $userId,
            'type' => $input['type'],
            'description' => $input['description'],
            'courseTitle' => $input['courseTitle'] ?? null,
            'status' => 'pending',
            'submittedAt' => CarbonImmutable::now()->format('d/m/Y'),
        ])->toArray();
    }

    /** @return array<string, mixed> */
    public function updateAcademicRequestStatus(string $id, string $status): array
    {
        $req = AcademicRequest::query()->find($id);
        if ($req === null) {
            throw ApiException::notFound('Solicitação acadêmica não encontrada.');
        }
        if (! in_array($status, ['pending', 'approved', 'rejected'], true)) {
            throw ApiException::validation('Status de solicitação inválido.');
        }
        $req->status = $status;
        $req->save();

        return $req->toArray();
    }
}
