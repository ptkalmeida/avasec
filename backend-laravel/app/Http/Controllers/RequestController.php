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
        return response()->json($this->requests->listAcademicRequests($request->attributes->get('auth_user')));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateInput($request, [
            'studentName' => ['required', 'string', 'min:2', 'max:150'],
            'type' => ['required', 'in:certificado,historico,matricula,outro'],
            'description' => ['required', 'string', 'min:10', 'max:4000'],
            'courseTitle' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $req = $this->requests->createAcademicRequest($data, $request->attributes->get('auth_user'));
        $this->audit->log($request, 'Solicitação Acadêmica', "Solicitação de \"{$req['type']}\" enviada por {$req['studentName']}.");

        return response()->json($req, 201);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $data = $this->validateInput($request, ['status' => ['required', 'in:approved,rejected']]);
        $updated = $this->requests->updateAcademicRequestStatus($id, $data['status']);
        $this->audit->log($request, 'Aprovação de Solicitação', "Solicitação acadêmica {$id} ({$updated['studentName']}) marcada como \"{$updated['status']}\".");

        return response()->json($updated);
    }
}
