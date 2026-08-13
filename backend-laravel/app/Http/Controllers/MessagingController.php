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
        $sessionId = $request->query('sessionId');
        $sessionId = is_string($sessionId) ? $sessionId : null;

        return response()->json($this->messaging->listChatMessages($sessionId, $this->requester($request)));
    }

    public function createChatMessage(Request $request): JsonResponse
    {
        $data = $this->validateInput($request, [
            'sessionId' => ['required', 'string', 'max:191'],
            'text' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        return response()->json($this->messaging->createChatMessage([
            'sessionId' => $this->stringField($data, 'sessionId'),
            'text' => $this->stringField($data, 'text'),
        ], $this->requester($request)), 201);
    }

    public function listDirectMessages(Request $request): JsonResponse
    {
        $studentUserId = $request->query('studentUserId');
        $studentUserId = is_string($studentUserId) ? $studentUserId : null;

        return response()->json($this->messaging->listDirectMessages($this->requester($request), $studentUserId));
    }

    public function createDirectMessage(Request $request): JsonResponse
    {
        $data = $this->validateInput($request, [
            'studentUserId' => ['sometimes', 'nullable', 'string', 'max:191'],
            'text' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        return response()->json($this->messaging->createDirectMessage([
            'studentUserId' => $this->optionalString($data, 'studentUserId'),
            'text' => $this->stringField($data, 'text'),
        ], $this->requester($request)), 201);
    }
}
