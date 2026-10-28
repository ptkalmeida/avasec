<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiRequestHelpers;
use App\Rules\SafeUrlRule;
use App\Services\LearningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LearningController extends Controller
{
    use ApiRequestHelpers;

    public function __construct(private readonly LearningService $learning) {}

    // Quizzes
    public function listQuizzes(Request $request): JsonResponse
    {
        return response()->json($this->learning->listQuizzes($this->requester($request)));
    }

    public function createQuiz(Request $request): JsonResponse
    {
        $data = $this->validateKeepingAll($request, [
            'id' => ['sometimes', 'string', 'max:191'],
            'curso_id' => ['required', 'string', 'max:191'],
            'titulo' => ['required', 'string', 'min:1', 'max:200'],
            'questoes' => ['required', 'array', 'min:1'],
            'questoes.*.id' => ['sometimes', 'string', 'max:191'],
            'questoes.*.texto_questao' => ['required', 'string', 'min:1', 'max:2000'],
            'questoes.*.opcoes' => ['required', 'array', 'min:2', 'max:10'],
            'questoes.*.opcoes.*' => ['required', 'string', 'min:1', 'max:500'],
            'questoes.*.indice_opcao_correta' => ['required', 'integer', 'min:0'],
            'questoes.*.explicacao' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'questoes.*.mensagem_revisao' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'questoes.*.modulo_recomendado' => ['sometimes', 'nullable', 'string', 'max:200'],
            'questoes.*.permite_nova_tentativa' => ['sometimes', 'boolean'],
        ]);

        return response()->json($this->learning->createQuiz($data, $this->requester($request)), 201);
    }

    public function deleteQuiz(Request $request, string $id): JsonResponse
    {
        $this->learning->deleteQuiz($id, $this->requester($request));

        return response()->json(['success' => true]);
    }

    // Avaliacao submissions
    public function listQuizSubmissions(Request $request): JsonResponse
    {
        return response()->json($this->learning->listQuizSubmissions($this->requester($request)));
    }

    public function submitQuiz(Request $request): JsonResponse
    {
        // A nota é recalculada no servidor a partir de `answers` (questionId => índice
        // escolhido). percentual_nota/aprovado do corpo, se enviados, são ignorados; curso_id
        // é derivado do quiz. Aceita-se `curso_id` legado no payload sem usá-lo.
        $data = $this->validateInput($request, [
            'curso_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'avaliacao_id' => ['required', 'string', 'max:191'],
            'answers' => ['required', 'array', 'max:200'],
            'answers.*' => ['integer', 'min:0', 'max:100'],
        ]);

        /** @var array<string,int> $answers */
        $answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];

        return response()->json($this->learning->submitQuiz([
            'avaliacao_id' => $this->stringField($data, 'avaliacao_id'),
            'answers' => $answers,
        ], $this->requester($request)), 201);
    }

    // Fórum
    public function listForumMessages(Request $request): JsonResponse
    {
        return response()->json($this->learning->listForumMessages($this->requester($request)));
    }

    public function createForumMessage(Request $request): JsonResponse
    {
        $data = $this->validateKeepingAll($request, [
            'curso_id' => ['required', 'string', 'max:191'],
            'texto' => ['required', 'string', 'min:1', 'max:3000'],
        ]);

        return response()->json($this->learning->createForumMessage([
            'curso_id' => $this->stringField($data, 'curso_id'),
            'texto' => $this->stringField($data, 'texto'),
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
    public function listExercises(Request $request): JsonResponse
    {
        return response()->json($this->learning->listExercises($this->requester($request)));
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
            'exercicio_id' => ['required', 'string', 'max:191'],
            'texto_entrega' => ['required', 'string', 'min:1', 'max:10000'],
            // Vira link na tela de correção: `javascript:` aqui executaria na sessão de quem corrige.
            'url_arquivo' => ['sometimes', 'nullable', 'string', 'max:2000', new SafeUrlRule],
            'nome_arquivo' => ['sometimes', 'nullable', 'string', 'max:300'],
        ]);

        return response()->json($this->learning->submitExercise($data, $this->requester($request)), 201);
    }

    public function gradeSubmission(Request $request, string $id): JsonResponse
    {
        $data = $this->validateKeepingAll($request, [
            'nota' => ['required', 'numeric', 'min:0', 'max:1000'],
            'devolutiva' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'status' => ['required', 'in:approved,rejected,revision'],
        ]);
        $data['devolutiva'] ??= '';

        return response()->json($this->learning->gradeSubmission($id, $data, $this->requester($request)));
    }

    /** @return array<string, array<int, string>> */
    private function exerciseRules(bool $partial): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'id' => ['sometimes', 'string', 'max:191'],
            'curso_id' => [$req, 'string', 'max:191'],
            'titulo' => [$req, 'string', 'min:1', 'max:200'],
            'descricao' => [$req, 'string', 'min:1', 'max:5000'],
            'instrucoes' => [$req, 'string', 'min:1', 'max:5000'],
            'pontuacao_maxima' => [$req, 'integer', 'min:1', 'max:1000'],
            // Prazo precisa ser parseavel: a pagina de exercicios do aluno calcula
            // "vence em N dias" / "atrasado" a partir dele (src/utils/exerciseStatus.ts).
            // Como texto livre, um "julho de 2026" entrava no banco e a tela nunca
            // mostrava vencimento nenhum. dd/mm/aaaa e o formato que as linhas
            // existentes ja usam.
            'prazo_texto' => ['sometimes', 'nullable', 'string', 'max:30', 'date_format:d/m/Y'],
        ];
    }
}
