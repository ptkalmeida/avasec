<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiRequestHelpers;
use App\Services\AuditLogger;
use App\Services\RequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RequestController extends Controller
{
    use ApiRequestHelpers;

    public function __construct(
        private readonly RequestService $requests,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->requests->listAcademicRequests($this->requester($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateInput($request, [
            'userId' => ['sometimes', 'nullable', 'string', 'max:191'],
            'type' => ['required', 'in:certificado,historico,matricula,outro'],
            'description' => ['required', 'string', 'min:10', 'max:4000'],
            'courseTitle' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $type = $this->stringField($data, 'type');
        $req = $this->requests->createAcademicRequest([
            'userId' => $this->optionalString($data, 'userId'),
            'type' => $type,
            'description' => $this->stringField($data, 'description'),
            'courseTitle' => $this->optionalString($data, 'courseTitle'),
        ], $this->requester($request));
        $studentName = $this->optionalString($req, 'studentName') ?? '';
        $this->audit->log($request, 'Solicitação Acadêmica', "Solicitação de \"{$type}\" enviada por {$studentName}.");

        return response()->json($req, 201);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $data = $this->validateInput($request, ['status' => ['required', 'in:approved,rejected']]);
        $status = $this->stringField($data, 'status');
        $updated = $this->requests->updateAcademicRequestStatus($id, $status);
        $studentName = $this->optionalString($updated, 'studentName') ?? '';
        $this->audit->log($request, 'Aprovação de Solicitação', "Solicitação acadêmica {$id} ({$studentName}) marcada como \"{$status}\".");

        return response()->json($updated);
    }
}
