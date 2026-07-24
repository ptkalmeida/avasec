<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Controllers\Concerns\ApiRequestHelpers;
use App\Http\Middleware\JwtAuthenticate;
use App\Services\AuditLogger;
use App\Services\AuthService;
use App\Support\Jwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;

final class AuthController extends Controller
{
    use ApiRequestHelpers;

    public function __construct(
        private readonly AuthService $auth,
        private readonly AuditLogger $audit,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $data = $this->validateInput($request, [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'min:6', 'max:128'],
            'role' => ['sometimes', 'in:student,instructor,admin'],
            'cpf' => ['nullable', 'string', 'max:20'],
            'municipio' => ['nullable', 'string', 'max:120'],
            'uf' => ['nullable', 'string', 'max:2'],
            'areaInteresse' => ['nullable', 'string', 'max:120'],
            'dataCadastro' => ['nullable', 'string', 'max:30'],
        ]);
        $data['email'] = mb_strtolower(trim((string) $data['email']));

        $result = $this->auth->register($data, $request->attributes->get('auth_user'));
        $this->audit->log(
            $request,
            'Cadastro de Usuário',
            "Nova conta criada: {$result['user']['name']} ({$result['user']['role']}, status {$result['user']['status']}).",
        );

        $response = response()->json($result, 201);
        if ($result['token'] !== null) {
            $response->withCookie($this->sessionCookie($result['token']));
        }

        return $response;
    }

    public function login(Request $request): JsonResponse
    {
        $data = $this->validateInput($request, [
            'name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'email' => ['sometimes', 'nullable', 'email', 'max:254'],
            'password' => ['required', 'string', 'min:1', 'max:128'],
        ]);
        if (empty($data['name']) && empty($data['email'])) {
            throw ApiException::validation('Informe nome ou e-mail para login.');
        }
        if (! empty($data['email'])) {
            $data['email'] = mb_strtolower(trim((string) $data['email']));
        }

        $identifier = $data['email'] ?? $data['name'] ?? '';

        try {
            $result = $this->auth->login($data);
        } catch (Throwable $err) {
            $this->audit->log($request, 'Tentativa Fracassada', "Falha de login para o identificador: {$identifier}.", 'FAILED');
            throw $err;
        }

        $this->audit->log($request, 'Autenticação no Sistema', 'Login efetuado com sucesso.', 'SUCCESS', [
            'name' => $result['user']['name'],
            'role' => $result['user']['role'],
        ]);

        return response()->json($result)->withCookie($this->sessionCookie($result['token']));
    }

    public function logout(): JsonResponse
    {
        return response()->json(['success' => true])->withCookie(cookie()->forget(JwtAuthenticate::SESSION_COOKIE, '/'));
    }

    public function me(Request $request): JsonResponse
    {
        $sub = $request->attributes->get('auth_user')['sub'];

        return response()->json($this->auth->getCurrentUser($sub));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $this->validateInput($request, [
            'currentPassword' => ['sometimes', 'nullable', 'string', 'max:128'],
            'newPassword' => ['required', 'string', 'min:6', 'max:128'],
        ]);

        $sub = $request->attributes->get('auth_user')['sub'];
        $this->auth->changePassword($sub, $data['newPassword'], $data['currentPassword'] ?? null);
        $this->audit->log($request, 'Alteração de Senha', 'Senha alterada pelo próprio usuário.');

        return response()->json(['success' => true]);
    }

    public function listUsers(Request $request): JsonResponse
    {
        $role = $request->query('role');
        if ($role !== null && ! in_array($role, ['student', 'instructor', 'admin'], true)) {
            throw ApiException::validation('Perfil inválido.');
        }

        [$page, $pageSize, $skip, $take] = $this->pageParams($request);
        $authUser = $request->attributes->get('auth_user');

        // Escopo por perfil: aluno não lista outros alunos; instrutor só vê os próprios; admin vê tudo.
        if ($role === 'student') {
            if ($authUser['role'] === 'student') {
                throw ApiException::forbidden('Alunos não podem listar dados de outros alunos.');
            }
            if ($authUser['role'] === 'instructor') {
                $result = $this->auth->listStudentsForInstructor($authUser['sub'], (string) $authUser['name'], $skip, $take);

                return response()->json($this->paginated($result['items'], $result['total'], $page, $pageSize));
            }
        }

        $result = $this->auth->listUsersByRole($role, $skip, $take);

        return response()->json($this->paginated($result['items'], $result['total'], $page, $pageSize));
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $data = $this->validateInput($request, [
            'status' => ['required', 'in:active,blocked,pending_confirmation'],
        ]);

        $updated = $this->auth->updateAccountStatus($id, $data['status']);
        $this->audit->log($request, 'Alteração de Status de Conta', "Status de \"{$updated['name']}\" alterado para \"{$data['status']}\".");

        return response()->json($updated);
    }

    public function removeUser(Request $request, string $id): JsonResponse
    {
        $sub = $request->attributes->get('auth_user')['sub'];
        if ($id === $sub) {
            throw new ApiException(400, 'BAD_REQUEST', 'Você não pode remover a própria conta administrativa.');
        }
        $this->auth->deleteUser($id);
        $this->audit->log($request, 'Exclusão de Usuário', "Usuário {$id} removido.");

        return response()->json(['success' => true]);
    }

    private function sessionCookie(string $token): Cookie
    {
        // Espelha SESSION_COOKIE_OPTIONS do Node: HttpOnly, SameSite=Lax, path=/,
        // secure só em produção, expiração de 12h (em minutos para o helper do Laravel).
        return cookie(
            name: JwtAuthenticate::SESSION_COOKIE,
            value: $token,
            minutes: Jwt::TTL_SECONDS / 60,
            path: '/',
            domain: null,
            secure: app()->environment('production'),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }
}
