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
            'usuario_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'tipo' => ['required', 'in:certificado,historico,matricula,outro'],
            'descricao' => ['required', 'string', 'min:10', 'max:4000'],
            'titulo_curso' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $type = $this->stringField($data, 'tipo');
        $req = $this->requests->createAcademicRequest([
            'usuario_id' => $this->optionalString($data, 'usuario_id'),
            'tipo' => $type,
            'descricao' => $this->stringField($data, 'descricao'),
            'titulo_curso' => $this->optionalString($data, 'titulo_curso'),
        ], $this->requester($request));
        $nome_aluno = $this->optionalString($req, 'nome_aluno') ?? '';
        $this->audit->log($request, 'Solicitação Acadêmica', "Solicitação de \"{$type}\" enviada por {$nome_aluno}.");

        return response()->json($req, 201);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $data = $this->validateInput($request, ['status' => ['required', 'in:approved,rejected']]);
        $status = $this->stringField($data, 'status');
        $updated = $this->requests->updateAcademicRequestStatus($id, $status);
        $nome_aluno = $this->optionalString($updated, 'nome_aluno') ?? '';
        $this->audit->log($request, 'Aprovação de Solicitação', "Solicitação acadêmica {$id} ({$nome_aluno}) marcada como \"{$status}\".");

        return response()->json($updated);
    }
}
