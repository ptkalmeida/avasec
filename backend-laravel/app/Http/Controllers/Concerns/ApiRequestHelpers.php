<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
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
     * @param  array<string, array<int, string|ValidationRule>>  $rules
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
     * @param  array<string, array<int, string|ValidationRule>>  $rules
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
     * @return array{sub: string, name: string, role: string}
     */
    protected function requester(Request $request): array
    {
        $user = $this->optionalRequester($request);
        if ($user === null) {
            throw ApiException::unauthorized('Não autenticado.');
        }

        return $user;
    }

    /**
     * Identidade autenticada, se houver — para rotas públicas em que o token é
     * opcional (registro com provisionamento por admin, telemetria).
     *
     * @return array{sub: string, name: string, role: string}|null
     */
    protected function optionalRequester(Request $request): ?array
    {
        $user = $request->attributes->get('auth_user');
        if (! is_array($user) || ! is_string($user['sub'] ?? null)) {
            return null;
        }

        return [
            'sub' => $user['sub'],
            'name' => is_string($user['name'] ?? null) ? $user['name'] : '',
            'role' => is_string($user['role'] ?? null) ? $user['role'] : '',
        ];
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
     * Extrai um campo string obrigatório de um payload já validado — faz o
     * narrowing em runtime que o validator garante mas o tipo não expressa.
     *
     * @param  array<string, mixed>  $data
     */
    protected function stringField(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value)) {
            throw ApiException::validation("Campo obrigatório ausente ou inválido: {$key}.");
        }

        return $value;
    }

    /**
     * Extrai um campo string opcional (ausente ou não-string vira null).
     *
     * @param  array<string, mixed>  $data
     */
    protected function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Extrai uma lista de strings de um payload já validado (entradas
     * não-string são descartadas; campo ausente vira lista vazia).
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function stringList(array $data, string $key): array
    {
        $value = $data[$key] ?? [];
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }

    /**
     * @param  array<string, array<int, string|ValidationRule>>  $rules
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
