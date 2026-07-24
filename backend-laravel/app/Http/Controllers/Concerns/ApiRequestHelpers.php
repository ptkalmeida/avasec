<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Helpers compartilhados pelos controllers da API (DRY — antes duplicados em cada
 * controller): validação com erro padronizado, identidade autenticada e paginação
 * no formato do contrato (`{ items, pagination }`).
 */
trait ApiRequestHelpers
{
    /**
     * Valida e retorna somente os campos validados.
     *
     * @param  array<string, array<int, string>>  $rules
     * @return array<string, mixed>
     */
    protected function validateInput(Request $request, array $rules): array
    {
        return $this->runValidator($request, $rules)->validated();
    }

    /**
     * Valida e retorna o payload completo — para rotas com estruturas aninhadas
     * (cursos/quizzes) em que o corpo inteiro é repassado ao service, espelhando
     * o contrato do backend Node.
     *
     * @param  array<string, array<int, string>>  $rules
     * @return array<string, mixed>
     */
    protected function validateKeepingAll(Request $request, array $rules): array
    {
        $this->runValidator($request, $rules);

        return $request->all();
    }

    /**
     * Identidade autenticada anexada pelo middleware JwtAuthenticate.
     *
     * @return array{sub: string, name: mixed, role: mixed}
     */
    protected function requester(Request $request): array
    {
        return $request->attributes->get('auth_user');
    }

    /**
     * Parâmetros de paginação (page/pageSize com limites) — mesmo comportamento
     * do utils/pagination.ts do Node.
     *
     * @return array{0: int, 1: int, 2: int, 3: int} [page, pageSize, skip, take]
     */
    protected function pageParams(Request $request): array
    {
        $page = max(1, (int) $request->query('page', '1') ?: 1);
        $requested = (int) $request->query('pageSize', '20') ?: 20;
        $pageSize = min(max(1, $requested), 100);

        return [$page, $pageSize, ($page - 1) * $pageSize, $pageSize];
    }

    /**
     * Resposta paginada padrão `{ items, pagination }`.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    protected function paginated(array $items, int $total, int $page, int $pageSize): array
    {
        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'totalPages' => max(1, (int) ceil($total / $pageSize)),
            ],
        ];
    }

    /**
     * @param  array<string, array<int, string>>  $rules
     */
    private function runValidator(Request $request, array $rules): \Illuminate\Validation\Validator
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw ApiException::validation($validator->errors()->first());
        }

        return $validator;
    }
}
