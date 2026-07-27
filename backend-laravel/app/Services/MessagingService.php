<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\ChatMessage;
use App\Models\DirectMessage;
use App\Support\Identity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

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
    public function listDirectMessages(array $requester, ?string $studentName): array
    {
        if ($requester['role'] === 'student') {
            return DirectMessage::query()
                ->where(function (Builder $q) use ($requester): void {
                    $q->where('studentUserId', $requester['sub'])
                        ->orWhere(fn (Builder $q2) => $q2->whereNull('studentUserId')->where('studentName', $requester['name']));
                })
                ->orderBy('timestamp')->get()->map->toArray()->all();
        }

        return DirectMessage::query()
            ->when($studentName !== null, fn ($q) => $q->where('studentName', $studentName))
            ->orderBy('timestamp')->get()->map->toArray()->all();
    }

    /**
     * @param  array{studentName:string,text:string}  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function createDirectMessage(array $input, array $requester): array
    {
        if ($requester['role'] === 'student' && $input['studentName'] !== $requester['name']) {
            throw ApiException::forbidden('Você só pode enviar mensagens no seu próprio canal de atendimento.');
        }
        $studentUserId = Identity::resolveStudentUserId($input['studentName'], $requester);

        return DirectMessage::query()->create([
            'id' => 'dm-'.$this->nowMs(),
            'studentName' => $input['studentName'],
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
