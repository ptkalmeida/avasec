<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiRequestHelpers;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuditController extends Controller
{
    use ApiRequestHelpers;

    public function __construct(private readonly AuditLogService $audit) {}

    public function listSecurityLogs(Request $request): JsonResponse
    {
        [$page, $pageSize, $skip, $take] = $this->pageParams($request);
        $result = $this->audit->listSecurityLogs($skip, $take);

        return response()->json($this->paginated($result['items'], $result['total'], $page, $pageSize));
    }

    public function clearSecurityLogs(): JsonResponse
    {
        $this->audit->clearSecurityLogs();

        return response()->json(['success' => true, 'message' => 'Logs de auditoria zerados com sucesso.']);
    }

    public function recordClientEvent(Request $request): JsonResponse
    {
        $data = $this->validateInput($request, [
            'action' => ['required', 'string', 'min:1', 'max:200'],
            'details' => ['required', 'string', 'min:1', 'max:1000'],
            'status' => ['sometimes', 'in:SUCCESS,WARNING,FAILED'],
        ]);

        // A identidade registrada vem do token (se houver), nunca do corpo.
        $authUser = $this->optionalRequester($request);
        $actor = $authUser !== null
            ? ['name' => $authUser['name'] !== '' ? $authUser['name'] : 'Visitante Anônimo', 'role' => $authUser['role'] !== '' ? $authUser['role'] : 'anonymous']
            : ['name' => 'Visitante Anônimo', 'role' => 'anonymous'];

        $forwarded = $request->header('X-Forwarded-For');
        $ip = is_string($forwarded) && $forwarded !== '' ? trim(explode(',', $forwarded)[0]) : ($request->ip() ?? 'desconhecido');

        $event = $this->audit->recordClientEvent([
            'action' => $this->stringField($data, 'action'),
            'details' => $this->stringField($data, 'details'),
            'status' => $this->optionalString($data, 'status'),
        ], $actor, $ip, (string) $request->userAgent());

        return response()->json($event, 201);
    }

    public function listClientEvents(Request $request): JsonResponse
    {
        [$page, $pageSize, $skip, $take] = $this->pageParams($request);
        $result = $this->audit->listClientEvents($skip, $take);

        return response()->json($this->paginated($result['items'], $result['total'], $page, $pageSize));
    }
}
