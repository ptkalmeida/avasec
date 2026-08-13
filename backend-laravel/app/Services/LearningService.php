<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\ExerciseSubmission;
use App\Models\ForumMessage;
use App\Models\PracticalExercise;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizSubmission;
use App\Support\BusinessRules;
use App\Support\Identity;
use App\Support\InstructorScope;
use App\Support\Payload;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Quizzes, fórum e exercícios práticos — espelha src/server/services/learningService.ts.
 * senderName/studentName sempre vêm da identidade autenticada, nunca do corpo.
 *
 * $requester = ['sub'=>id, 'name'=>..., 'role'=>...].
 */
final class LearningService
{
    // ---------- QUIZZES ----------

    /** @return array<int, array<string, mixed>> */
    public function listQuizzes(): array
    {
        return Quiz::query()->with('questions')->get()->map->toArray()->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function createQuiz(array $input, array $requester): array
    {
        $courseId = is_string($input['courseId'] ?? null) ? $input['courseId'] : null;
        $this->assertInstructorOwnsCourse($courseId, $requester);

        $id = is_string($input['id'] ?? null) ? $input['id'] : ('quiz-'.$this->nowMs());
        $quiz = Quiz::query()->find($id);
        // Um quiz existente não pode ser sequestrado para outro curso alheio.
        if ($quiz !== null && is_string($quiz->courseId)) {
            $this->assertInstructorOwnsCourse($quiz->courseId, $requester);
        }
        if ($quiz !== null) {
            $quiz->fill(['courseId' => $input['courseId'], 'title' => $input['title']])->save();
        } else {
            Quiz::query()->create(['id' => $id, 'courseId' => $input['courseId'], 'title' => $input['title']]);
        }

        $questions = Payload::assocList($input['questions'] ?? []);
        $keptIds = array_values(array_filter(array_map(fn ($q) => $q['id'] ?? null, $questions)));
        QuizQuestion::query()->where('quizId', $id)
            ->when(count($keptIds) > 0, fn ($q) => $q->whereNotIn('id', $keptIds))
            ->delete();

        foreach ($questions as $q) {
            $qId = is_string($q['id'] ?? null) ? $q['id'] : ($id.'-q-'.$this->nowMs().'-'.Str::lower(Str::random(4)));
            $data = [
                'quizId' => $id,
                'questionText' => $q['questionText'],
                'options' => $q['options'],
                'correctOptionIndex' => is_numeric($q['correctOptionIndex'] ?? null) ? (int) $q['correctOptionIndex'] : 0,
                'explanation' => $q['explanation'] ?? null,
                'reviewMessage' => $q['reviewMessage'] ?? null,
                'recommendedModule' => $q['recommendedModule'] ?? null,
                'allowRetry' => $q['allowRetry'] ?? null,
            ];
            $existing = QuizQuestion::query()->find($qId);
            if ($existing !== null) {
                $existing->fill($data)->save();
            } else {
                $data['id'] = $qId;
                QuizQuestion::query()->create($data);
            }
        }

        return Quiz::query()->with('questions')->find($id)?->toArray() ?? [];
    }

    /** @param array{sub:string,name:string,role:string} $requester */
    public function deleteQuiz(string $id, array $requester): void
    {
        $quiz = Quiz::query()->find($id);
        if ($quiz === null) {
            return;
        }
        $this->assertInstructorOwnsCourse(is_string($quiz->courseId) ? $quiz->courseId : null, $requester);
        QuizSubmission::query()->where('quizId', $id)->delete();
        Quiz::query()->where('id', $id)->delete();
    }

    /**
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listQuizSubmissions(array $requester): array
    {
        $q = QuizSubmission::query();
        if ($requester['role'] === 'student') {
            Identity::applyOwnRows($q, $requester);
        } elseif ($requester['role'] === 'instructor') {
            $q->whereIn('courseId', InstructorScope::courseIds($requester));
        }

        return $q->get()->map->toArray()->all();
    }

    /**
     * A nota NUNCA vem do cliente: é recalculada no servidor comparando as respostas
     * enviadas ($input['answers']: questionId => índice escolhido) com o
     * correctOptionIndex de cada QuizQuestion. O courseId também é derivado do quiz,
     * não do corpo. Isso impede o aluno de auto-declarar scorePercent/passed.
     *
     * @param  array{quizId:string,answers:array<string,int>}  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function submitQuiz(array $input, array $requester): array
    {
        if ($requester['role'] !== 'student') {
            throw ApiException::forbidden('Somente alunos podem responder quizzes.');
        }

        $quiz = Quiz::query()->with('questions')->find($input['quizId']);
        if ($quiz === null) {
            throw ApiException::notFound('Quiz não encontrado.');
        }

        $total = $quiz->questions->count();
        $correct = 0;
        foreach ($quiz->questions as $question) {
            $given = $input['answers'][$question->id] ?? null;
            if (is_int($given) && $given === $question->correctOptionIndex) {
                $correct++;
            }
        }
        $scorePercent = $total === 0 ? 0 : (int) round(($correct / $total) * 100);
        $passed = $scorePercent >= BusinessRules::quizPassThreshold();

        QuizSubmission::query()->where('userId', $requester['sub'])->where('quizId', $input['quizId'])->delete();

        return QuizSubmission::query()->create([
            'id' => 'sub-'.$this->nowMs().'-'.random_int(0, 999),
            'studentName' => $requester['name'],
            'userId' => $requester['sub'],
            'courseId' => $quiz->courseId,
            'quizId' => $quiz->id,
            'scorePercent' => $scorePercent,
            'passed' => $passed,
            'submittedAt' => CarbonImmutable::now()->format('d/m/Y').' às '.CarbonImmutable::now()->format('H:i'),
        ])->toArray();
    }

    // ---------- FÓRUM ----------

    /** @return array<int, array<string, mixed>> */
    public function listForumMessages(): array
    {
        return ForumMessage::query()->get()->map->toArray()->all();
    }

    /**
     * @param  array{courseId:string,text:string}  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function createForumMessage(array $input, array $requester): array
    {
        return ForumMessage::query()->create([
            'id' => 'forum-msg-'.$this->nowMs(),
            'courseId' => $input['courseId'],
            'senderName' => $requester['name'],
            'senderUserId' => $requester['sub'],
            'senderRole' => $requester['role'],
            'text' => $input['text'],
            'timestamp' => CarbonImmutable::now()->format('d/m/Y H:i'),
            'likes' => 0,
            'likedBy' => [],
        ])->toArray();
    }

    /**
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function toggleForumLike(string $messageId, array $requester): array
    {
        $msg = ForumMessage::query()->find($messageId);
        if ($msg === null) {
            throw ApiException::notFound('Mensagem não encontrada.');
        }
        // likedBy é um array de userIds desde a ADR 10 (backfill converteu nomes).
        $likedBy = is_array($msg->likedBy) ? $msg->likedBy : [];
        $hasLiked = in_array($requester['sub'], $likedBy, true);
        $next = $hasLiked
            ? array_values(array_filter($likedBy, fn ($u) => $u !== $requester['sub']))
            : [...$likedBy, $requester['sub']];
        $msg->likedBy = $next;
        $msg->likes = count($next);
        $msg->save();

        return $msg->toArray();
    }

    /** @param array{sub:string,name:string,role:string} $requester */
    public function deleteForumMessage(string $id, array $requester): void
    {
        $msg = ForumMessage::query()->find($id);
        if ($msg === null) {
            return;
        }
        $owns = Identity::ownsRow($msg->senderUserId, $requester);
        if ($requester['role'] !== 'admin' && ! $owns) {
            throw ApiException::forbidden('Você só pode remover as próprias mensagens.');
        }
        $msg->delete();
    }

    // ---------- EXERCÍCIOS PRÁTICOS ----------

    /** @return array<int, array<string, mixed>> */
    public function listExercises(): array
    {
        return PracticalExercise::query()->get()->map->toArray()->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function createExercise(array $input, array $requester): array
    {
        $this->assertInstructorOwnsCourse(is_string($input['courseId'] ?? null) ? $input['courseId'] : null, $requester);

        $id = $input['id'] ?? ('exercise-'.$this->nowMs());
        $data = $this->exerciseScalar($input);
        $data['id'] = $id;

        return PracticalExercise::query()->create($data)->toArray();
    }

    /**
     * @param  array<string, mixed>  $updates
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function updateExercise(string $id, array $updates, array $requester): array
    {
        $exercise = PracticalExercise::query()->find($id);
        if ($exercise === null) {
            throw ApiException::notFound('Exercício não encontrado.');
        }
        // Posse do curso atual do exercício e, se o payload mudar o curso, também do novo.
        $this->assertInstructorOwnsCourse(is_string($exercise->courseId) ? $exercise->courseId : null, $requester);
        if (is_string($updates['courseId'] ?? null) && $updates['courseId'] !== $exercise->courseId) {
            $this->assertInstructorOwnsCourse($updates['courseId'], $requester);
        }
        $exercise->fill($this->exerciseScalar($updates))->save();

        return $exercise->toArray();
    }

    /** @param array{sub:string,name:string,role:string} $requester */
    public function deleteExercise(string $id, array $requester): void
    {
        $exercise = PracticalExercise::query()->find($id);
        if ($exercise === null) {
            return;
        }
        $this->assertInstructorOwnsCourse(is_string($exercise->courseId) ? $exercise->courseId : null, $requester);
        $exercise->delete();
    }

    /**
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listExerciseSubmissions(array $requester): array
    {
        $q = ExerciseSubmission::query();
        if ($requester['role'] === 'student') {
            Identity::applyOwnRows($q, $requester);
        } elseif ($requester['role'] === 'instructor') {
            // ExerciseSubmission não tem courseId; escopo via exercícios dos cursos do instrutor.
            $exerciseIds = PracticalExercise::query()
                ->whereIn('courseId', InstructorScope::courseIds($requester))
                ->pluck('id')->all();
            $q->whereIn('exerciseId', $exerciseIds);
        }

        return $q->get()->map->toArray()->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function submitExercise(array $input, array $requester): array
    {
        if ($requester['role'] !== 'student') {
            throw ApiException::forbidden('Somente alunos podem enviar entregas de exercícios.');
        }
        $data = [
            'exerciseId' => $input['exerciseId'],
            'studentName' => $requester['name'],
            'userId' => $requester['sub'],
            'submissionText' => $input['submissionText'],
            'fileUrl' => $input['fileUrl'] ?? null,
            'fileName' => $input['fileName'] ?? null,
            'submittedAt' => CarbonImmutable::now()->format('d/m/Y H:i:s'),
            'status' => 'pending',
        ];
        $existing = ExerciseSubmission::query()
            ->where('exerciseId', $input['exerciseId'])->where('userId', $requester['sub'])->first();
        if ($existing !== null) {
            $existing->fill($data)->save();

            return $existing->toArray();
        }
        $data['id'] = 'sub-'.$this->nowMs();

        return ExerciseSubmission::query()->create($data)->toArray();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function gradeSubmission(string $submissionId, array $input, array $requester): array
    {
        if ($requester['role'] === 'student') {
            throw ApiException::forbidden('Alunos não podem corrigir entregas.');
        }
        $submission = ExerciseSubmission::query()->find($submissionId);
        if ($submission === null) {
            throw ApiException::notFound('Entrega não encontrada.');
        }
        // Instrutor só corrige entregas de exercícios dos seus cursos (antes: qualquer um).
        $exerciseCourseId = PracticalExercise::query()->whereKey($submission->exerciseId)->value('courseId');
        $this->assertInstructorOwnsCourse(is_string($exerciseCourseId) ? $exerciseCourseId : null, $requester);
        $submission->fill([
            'score' => is_numeric($input['score'] ?? null) ? (int) $input['score'] : 0,
            'feedback' => $input['feedback'],
            'status' => $input['status'],
            'gradedAt' => CarbonImmutable::now()->format('d/m/Y H:i:s'),
            'gradedBy' => $requester['name'],
        ])->save();

        return $submission->toArray();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function exerciseScalar(array $input): array
    {
        return array_intersect_key($input, array_flip(['courseId', 'title', 'description', 'instructions', 'maxPoints', 'dueDate']));
    }

    /**
     * Posse de curso do instrutor (ADR 10): admin é irrestrito; instrutor só age em
     * curso que leciona; qualquer outro caso é 403. Evita que um instrutor gerencie
     * quizzes/exercícios/notas de cursos de terceiros.
     *
     * @param  array{sub:string,name:string,role:string}  $requester
     */
    private function assertInstructorOwnsCourse(?string $courseId, array $requester): void
    {
        if ($requester['role'] === 'admin') {
            return;
        }
        if ($requester['role'] === 'instructor'
            && $courseId !== null
            && in_array($courseId, InstructorScope::courseIds($requester), true)) {
            return;
        }
        throw ApiException::forbidden('Você só pode gerenciar conteúdo dos seus próprios cursos.');
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
