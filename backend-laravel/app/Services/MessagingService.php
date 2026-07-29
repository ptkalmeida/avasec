<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\DirectMessage;
use App\Support\Identity;
use Carbon\CarbonImmutable;

/**
 * Chat de aulas ao vivo e mensagens diretas — espelha src/server/services/messagingService.ts.
 * senderName/senderRole sempre da identidade autenticada. Leitura de DMs do aluno usa FK-first
 * com fallback por nome.
 */
final class MessagingService
{
    /** @return array<int, array<string, mixed>> */
    public function listChatMessages(?string $sessionId): array
    {
        return ChatMessage::query()
            ->when($sessionId !== null, fn ($q) => $q->where('sessionId', $sessionId))
            ->orderBy('timestamp')->get()->map->toArray()->all();
    }

    /**
     * @param  array{sessionId:string,text:string}  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function createChatMessage(array $input, array $requester): array
    {
        return ChatMessage::query()->create([
            'id' => 'chat-'.$this->nowMs(),
            'sessionId' => $input['sessionId'],
            'senderName' => $requester['name'],
            'senderUserId' => $requester['sub'],
            'senderRole' => $requester['role'],
            'text' => $input['text'],
            'timestamp' => CarbonImmutable::now()->toIso8601String(),
        ])->toArray();
    }

    /**
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listDirectMessages(array $requester, ?string $studentUserId): array
    {
        if ($requester['role'] === 'student') {
            return Identity::applyOwnRows(DirectMessage::query(), $requester, 'studentUserId')
                ->orderBy('timestamp')->get()->map->toArray()->all();
        }

        return DirectMessage::query()
            ->when($studentUserId !== null, fn ($q) => $q->where('studentUserId', $studentUserId))
            ->orderBy('timestamp')->get()->map->toArray()->all();
    }

    /**
     * @param  array{studentUserId?:string|null,text:string}  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function createDirectMessage(array $input, array $requester): array
    {
        // Aluno só escreve no próprio canal; staff endereça o aluno por userId (ADR 10).
        $studentUserId = Identity::resolveActorUserId($requester, $input['studentUserId'] ?? null);

        return DirectMessage::query()->create([
            'id' => 'dm-'.$this->nowMs(),
            'studentName' => Identity::displayName($studentUserId, $requester),
            'studentUserId' => $studentUserId,
            'senderName' => $requester['name'],
            'senderUserId' => $requester['sub'],
            'senderRole' => $requester['role'],
            'text' => $input['text'],
            'timestamp' => CarbonImmutable::now()->toIso8601String(),
        ])->toArray();
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
