<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\ChatMessage;
use App\Models\DirectMessage;
use App\Models\LiveSession;
use App\Support\CourseAccess;
use App\Support\Identity;
use App\Support\InstructorScope;
use Carbon\CarbonImmutable;

/**
 * Chat de aulas ao vivo e mensagens diretas — espelha src/server/services/messagingService.ts.
 * senderName/senderRole sempre da identidade autenticada. Leitura de DMs do aluno usa FK-first
 * com fallback por nome.
 */
final class MessagingService
{
    /**
     * Chat de aula ao vivo escopado por curso (ADR 10): o requester só lê o chat de
     * sessões de cursos aos quais pertence. Antes, qualquer autenticado enumerava
     * sessionId e lia/escrevia em turmas alheias.
     *
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listChatMessages(?string $sessionId, array $requester): array
    {
        if ($sessionId !== null) {
            $courseId = LiveSession::query()->whereKey($sessionId)->value('courseId');
            if (! is_string($courseId) || ! CourseAccess::canAccess($requester, $courseId)) {
                return [];
            }

            return ChatMessage::query()->where('sessionId', $sessionId)
                ->orderBy('timestamp')->get()->map->toArray()->all();
        }

        // Sem sessionId: admin vê tudo; demais recebem só o chat das sessões de cursos
        // a que têm acesso (o frontend carrega o chat em massa e filtra por sessão).
        $query = ChatMessage::query();
        if ($requester['role'] !== 'admin') {
            $accessibleSessionIds = $this->accessibleSessionIds($requester);
            $query->whereIn('sessionId', $accessibleSessionIds);
        }

        return $query->orderBy('timestamp')->get()->map->toArray()->all();
    }

    /**
     * @param  array{sessionId:string,text:string}  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function createChatMessage(array $input, array $requester): array
    {
        $courseId = LiveSession::query()->whereKey($input['sessionId'])->value('courseId');
        if (! is_string($courseId) || ! CourseAccess::canAccess($requester, $courseId)) {
            throw ApiException::forbidden('Você não participa desta turma.');
        }

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

        // Instrutor só lê DMs dos próprios alunos; admin, todas. Antes qualquer instrutor
        // fazia dump de toda a caixa de mensagens diretas da escola.
        $query = DirectMessage::query();
        if ($requester['role'] === 'instructor') {
            $scoped = InstructorScope::studentIds($requester);
            if ($studentUserId !== null) {
                $scoped = in_array($studentUserId, $scoped, true) ? [$studentUserId] : [];
            }
            $query->whereIn('studentUserId', $scoped);
        } elseif ($studentUserId !== null) {
            $query->where('studentUserId', $studentUserId);
        }

        return $query->orderBy('timestamp')->get()->map->toArray()->all();
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

    /**
     * IDs das sessões ao vivo dos cursos acessíveis ao requester (não-admin).
     *
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return list<string>
     */
    private function accessibleSessionIds(array $requester): array
    {
        $courseIds = CourseAccess::accessibleCourseIds($requester);
        if (count($courseIds) === 0) {
            return [];
        }

        return array_values(array_filter(
            LiveSession::query()->whereIn('courseId', $courseIds)->pluck('id')->all(),
            static fn ($id): bool => is_string($id),
        ));
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
