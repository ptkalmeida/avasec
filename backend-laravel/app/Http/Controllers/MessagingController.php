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
        return response()->json($this->messaging->listChatMessages($request->query('sessionId')));
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
        return response()->json($this->messaging->listDirectMessages($this->requester($request), $request->query('studentName')));
    }

    public function createDirectMessage(Request $request): JsonResponse
    {
        $data = $this->validateInput($request, [
            'studentName' => ['required', 'string', 'min:2', 'max:150'],
            'text' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        return response()->json($this->messaging->createDirectMessage([
            'studentName' => $this->stringField($data, 'studentName'),
            'text' => $this->stringField($data, 'text'),
        ], $this->requester($request)), 201);
    }
}
