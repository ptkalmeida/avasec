<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiRequestHelpers;
use App\Services\MessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MessagingController extends Controller
{
    use ApiRequestHelpers;

    public function __construct(private readonly MessagingService $messaging) {}

    public function listChatMessages(Request $request): JsonResponse
    {
        $sessao_id = $request->query('sessao_id');
        $sessao_id = is_string($sessao_id) ? $sessao_id : null;

        return response()->json($this->messaging->listChatMessages($sessao_id, $this->requester($request)));
    }

    public function createChatMessage(Request $request): JsonResponse
    {
        $data = $this->validateInput($request, [
            'sessao_id' => ['required', 'string', 'max:191'],
            'texto' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        return response()->json($this->messaging->createChatMessage([
            'sessao_id' => $this->stringField($data, 'sessao_id'),
            'texto' => $this->stringField($data, 'texto'),
        ], $this->requester($request)), 201);
    }

    public function listDirectMessages(Request $request): JsonResponse
    {
        $aluno_usuario_id = $request->query('aluno_usuario_id');
        $aluno_usuario_id = is_string($aluno_usuario_id) ? $aluno_usuario_id : null;

        return response()->json($this->messaging->listDirectMessages($this->requester($request), $aluno_usuario_id));
    }

    public function createDirectMessage(Request $request): JsonResponse
    {
        $data = $this->validateInput($request, [
            'aluno_usuario_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'texto' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        return response()->json($this->messaging->createDirectMessage([
            'aluno_usuario_id' => $this->optionalString($data, 'aluno_usuario_id'),
            'texto' => $this->stringField($data, 'texto'),
        ], $this->requester($request)), 201);
    }
}
