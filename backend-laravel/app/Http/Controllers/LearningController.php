<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiRequestHelpers;
use App\Services\LearningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LearningController extends Controller
{
    use ApiRequestHelpers;

    public function __construct(private readonly LearningService $learning) {}

    // Quizzes
    public function listQuizzes(): JsonResponse
    {
        return response()->json($this->learning->listQuizzes());
    }

    public function createQuiz(Request $request): JsonResponse
    {
        $data = $this->validateKeepingAll($request, [
            'id' => ['sometimes', 'string', 'max:191'],
            'courseId' => ['required', 'string', 'max:191'],
            'title' => ['required', 'string', 'min:1', 'max:200'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.id' => ['sometimes', 'string', 'max:191'],
            'questions.*.questionText' => ['required', 'string', 'min:1', 'max:2000'],
            'questions.*.options' => ['required', 'array', 'min:2', 'max:10'],
            'questions.*.options.*' => ['required', 'string', 'min:1', 'max:500'],
            'questions.*.correctOptionIndex' => ['required', 'integer', 'min:0'],
            'questions.*.explanation' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'questions.*.reviewMessage' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'questions.*.recommendedModule' => ['sometimes', 'nullable', 'string', 'max:200'],
            'questions.*.allowRetry' => ['sometimes', 'boolean'],
        ]);

        return response()->json($this->learning->createQuiz($data, $this->requester($request)), 201);
    }

    public function deleteQuiz(Request $request, string $id): JsonResponse
    {
        $this->learning->deleteQuiz($id, $this->requester($request));

        return response()->json(['success' => true]);
    }

    // Quiz submissions
    public function listQuizSubmissions(Request $request): JsonResponse
    {
        return response()->json($this->learning->listQuizSubmissions($this->requester($request)));
    }

    public function submitQuiz(Request $request): JsonResponse
    {
        // A nota é recalculada no servidor a partir de `answers` (questionId => índice
        // escolhido). scorePercent/passed do corpo, se enviados, são ignorados; courseId
        // é derivado do quiz. Aceita-se `courseId` legado no payload sem usá-lo.
        $data = $this->validateInput($request, [
            'courseId' => ['sometimes', 'nullable', 'string', 'max:191'],
            'quizId' => ['required', 'string', 'max:191'],
            'answers' => ['required', 'array', 'max:200'],
            'answers.*' => ['integer', 'min:0', 'max:100'],
        ]);

        /** @var array<string,int> $answers */
        $answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];

        return response()->json($this->learning->submitQuiz([
            'quizId' => $this->stringField($data, 'quizId'),
            'answers' => $answers,
        ], $this->requester($request)), 201);
    }

    // Fórum
    public function listForumMessages(): JsonResponse
    {
        return response()->json($this->learning->listForumMessages());
    }

    public function createForumMessage(Request $request): JsonResponse
    {
        $data = $this->validateKeepingAll($request, [
            'courseId' => ['required', 'string', 'max:191'],
            'text' => ['required', 'string', 'min:1', 'max:3000'],
        ]);

        return response()->json($this->learning->createForumMessage([
            'courseId' => $this->stringField($data, 'courseId'),
            'text' => $this->stringField($data, 'text'),
        ], $this->requester($request)), 201);
    }

    public function toggleForumLike(Request $request, string $id): JsonResponse
    {
        return response()->json($this->learning->toggleForumLike($id, $this->requester($request)));
    }

    public function deleteForumMessage(Request $request, string $id): JsonResponse
    {
        $this->learning->deleteForumMessage($id, $this->requester($request));

        return response()->json(['success' => true]);
    }

    // Exercícios
    public function listExercises(): JsonResponse
    {
        return response()->json($this->learning->listExercises());
    }

    public function createExercise(Request $request): JsonResponse
    {
        $data = $this->validateKeepingAll($request, $this->exerciseRules(false));

        return response()->json($this->learning->createExercise($data, $this->requester($request)), 201);
    }

    public function updateExercise(Request $request, string $id): JsonResponse
    {
        $data = $this->validateKeepingAll($request, $this->exerciseRules(true));

        return response()->json($this->learning->updateExercise($id, $data, $this->requester($request)));
    }

    public function deleteExercise(Request $request, string $id): JsonResponse
    {
        $this->learning->deleteExercise($id, $this->requester($request));

        return response()->json(['success' => true]);
    }

    // Entregas de exercício
    public function listExerciseSubmissions(Request $request): JsonResponse
    {
        return response()->json($this->learning->listExerciseSubmissions($this->requester($request)));
    }

    public function submitExercise(Request $request): JsonResponse
    {
        $data = $this->validateKeepingAll($request, [
            'exerciseId' => ['required', 'string', 'max:191'],
            'submissionText' => ['required', 'string', 'min:1', 'max:10000'],
            'fileUrl' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'fileName' => ['sometimes', 'nullable', 'string', 'max:300'],
        ]);

        return response()->json($this->learning->submitExercise($data, $this->requester($request)), 201);
    }

    public function gradeSubmission(Request $request, string $id): JsonResponse
    {
        $data = $this->validateKeepingAll($request, [
            'score' => ['required', 'numeric', 'min:0', 'max:1000'],
            'feedback' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'status' => ['required', 'in:approved,rejected,revision'],
        ]);
        $data['feedback'] ??= '';

        return response()->json($this->learning->gradeSubmission($id, $data, $this->requester($request)));
    }

    /** @return array<string, array<int, string>> */
    private function exerciseRules(bool $partial): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'id' => ['sometimes', 'string', 'max:191'],
            'courseId' => [$req, 'string', 'max:191'],
            'title' => [$req, 'string', 'min:1', 'max:200'],
            'description' => [$req, 'string', 'min:1', 'max:5000'],
            'instructions' => [$req, 'string', 'min:1', 'max:5000'],
            'maxPoints' => [$req, 'integer', 'min:1', 'max:1000'],
            'dueDate' => ['sometimes', 'nullable', 'string', 'max:30'],
        ];
    }
}
