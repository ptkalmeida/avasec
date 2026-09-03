<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Controllers\Concerns\ApiRequestHelpers;
use App\Http\Middleware\JwtAuthenticate;
use App\Rules\CepRule;
use App\Rules\CpfRule;
use App\Rules\StrongPasswordRule;
use App\Services\AuditLogger;
use App\Services\AuthService;
use App\Support\Jwt;
use Illuminate\Contracts\Validation\ValidationRule;
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

    /**
     * Política de senha única para cadastro e troca de senha (ADR 11): senha
     * real, não mais o PIN numérico de 4 dígitos do MVP de demonstração.
     * Ver StrongPasswordRule — sem dependência nova.
     *
     * @return list<ValidationRule|string>
     */
    private function passwordRules(): array
    {
        return ['required', 'string', 'max:128', new StrongPasswordRule];
    }

    public function register(Request $request): JsonResponse
    {
        // CPF é o identificador de login do ALUNO (ADR 11), então é obrigatório
        // para conta de aluno. Conta de staff (instrutor/admin) entra por e-mail
        // e pode nascer sem CPF — só o admin consegue provisionar essas.
        $isStaffAccount = $this->optionalRequester($request)['role'] ?? null;
        $isStaffAccount = $isStaffAccount === 'admin'
            && in_array($request->input('role'), ['instructor', 'admin'], true);

        $data = $this->validateInput($request, [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email', 'max:254'],
            'password' => $this->passwordRules(),
            'role' => ['sometimes', 'in:student,instructor,admin'],
            'cpf' => $isStaffAccount
                ? ['sometimes', 'nullable', 'string', 'max:20', new CpfRule]
                : ['required', 'string', 'max:20', new CpfRule],
            'municipio' => ['nullable', 'string', 'max:120'],
            'uf' => ['nullable', 'string', 'max:2'],
            'areaInteresse' => ['nullable', 'string', 'max:120'],
            'dataCadastro' => ['nullable', 'string', 'max:30'],
            // Dados cadastrais adicionais: validados quando enviados, não exigidos.
            'celular' => ['sometimes', 'nullable', 'string', 'max:20'],
            'cep' => ['sometimes', 'nullable', 'string', 'max:20', new CepRule],
            'endereco' => ['sometimes', 'nullable', 'string', 'max:191'],
            'nomeSocial' => ['sometimes', 'nullable', 'string', 'max:150'],
            'identidade' => ['sometimes', 'nullable', 'string', 'max:40'],
        ]);
        $result = $this->auth->register([
            'name' => $this->stringField($data, 'name'),
            'email' => mb_strtolower(trim($this->stringField($data, 'email'))),
            'password' => $this->stringField($data, 'password'),
            'role' => $this->optionalString($data, 'role'),
            'cpf' => $this->optionalString($data, 'cpf'),
            'municipio' => $this->optionalString($data, 'municipio'),
            'uf' => $this->optionalString($data, 'uf'),
            'areaInteresse' => $this->optionalString($data, 'areaInteresse'),
            'dataCadastro' => $this->optionalString($data, 'dataCadastro'),
            'celular' => $this->optionalString($data, 'celular'),
            'cep' => $this->optionalString($data, 'cep'),
            'endereco' => $this->optionalString($data, 'endereco'),
            'nomeSocial' => $this->optionalString($data, 'nomeSocial'),
            'identidade' => $this->optionalString($data, 'identidade'),
        ], $this->optionalRequester($request));
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
        // Três identificadores aceitos (ADR 11): CPF (aluno), e-mail (admin e
        // gestor) e `name` (contas demo internas). A senha NÃO tem política
        // mínima aqui de propósito: aplicar a política no login trancaria fora
        // quem tem senha antiga — ela vale onde a senha é DEFINIDA.
        $data = $this->validateInput($request, [
            'name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'email' => ['sometimes', 'nullable', 'email', 'max:254'],
            'cpf' => ['sometimes', 'nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:1', 'max:128'],
        ]);
        if (empty($data['name']) && empty($data['email']) && empty($data['cpf'])) {
            throw ApiException::validation('Informe CPF, e-mail ou nome para login.');
        }
        if (! empty($data['email'])) {
            $data['email'] = mb_strtolower(trim($this->stringField($data, 'email')));
        }

        $identifier = $this->optionalString($data, 'cpf')
            ?? $this->optionalString($data, 'email')
            ?? $this->optionalString($data, 'name')
            ?? '';

        try {
            $result = $this->auth->login([
                'name' => $this->optionalString($data, 'name'),
                'email' => $this->optionalString($data, 'email'),
                'cpf' => $this->optionalString($data, 'cpf'),
                'password' => $this->stringField($data, 'password'),
            ]);
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
        $sub = $this->requester($request)['sub'];

        return response()->json($this->auth->getCurrentUser($sub));
    }

    public function changePassword(Request $request): JsonResponse
    {
        // currentPassword é OBRIGATÓRIO: sem ele, uma sessão sequestrada trocaria a
        // senha e faria takeover permanente da conta sem nunca conhecer a original.
        $data = $this->validateInput($request, [
            'currentPassword' => ['required', 'string', 'min:1', 'max:128'],
            'newPassword' => $this->passwordRules(),
        ]);

        $sub = $this->requester($request)['sub'];
        $this->auth->changePassword($sub, $this->stringField($data, 'newPassword'), $this->stringField($data, 'currentPassword'));
        $this->audit->log($request, 'Alteração de Senha', 'Senha alterada pelo próprio usuário.');

        return response()->json(['success' => true]);
    }

    /**
     * Redefinição de senha por admin (sem a senha atual, que o admin não conhece).
     * A senha nova é gerada e exibida pelo cliente; o servidor guarda só o hash e
     * registra na auditoria QUEM redefiniu de QUEM — nunca o valor da senha.
     */
    public function adminResetPassword(Request $request, string $id): JsonResponse
    {
        $data = $this->validateInput($request, [
            'newPassword' => $this->passwordRules(),
        ]);

        $updated = $this->auth->adminResetPassword($id, $this->stringField($data, 'newPassword'));
        $nome = $this->optionalString($updated, 'name') ?? '(sem nome)';
        $this->audit->log(
            $request,
            'Redefinição de Senha',
            "Senha de \"{$nome}\" ({$id}) redefinida pela coordenação.",
            'WARNING'
        );

        return response()->json(['success' => true]);
    }

    public function listUsers(Request $request): JsonResponse
    {
        $role = $request->query('role');
        if ($role !== null && ! in_array($role, ['student', 'instructor', 'admin'], true)) {
            throw ApiException::validation('Perfil inválido.');
        }

        [$page, $pageSize, $skip, $take] = $this->pageParams($request);
        $authUser = $this->requester($request);

        // Escopo por perfil (a rota já barra aluno): instrutor SÓ enxerga os próprios
        // alunos, qualquer que seja o `role` pedido — nunca a lista de outros instrutores
        // ou admins. Somente admin consulta usuários por perfil livremente.
        if ($authUser['role'] === 'instructor') {
            $result = $this->auth->listStudentsForInstructor($authUser['sub'], $authUser['name'], $skip, $take);

            return response()->json($this->paginated($result['items'], $result['total'], $page, $pageSize));
        }

        $result = $this->auth->listUsersByRole($role, $skip, $take);

        return response()->json($this->paginated($result['items'], $result['total'], $page, $pageSize));
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $data = $this->validateInput($request, [
            'status' => ['required', 'in:active,blocked,pending_confirmation'],
        ]);

        $status = $this->stringField($data, 'status');
        $updated = $this->auth->updateAccountStatus($id, $status);
        $this->audit->log($request, 'Alteração de Status de Conta', "Status de \"{$updated['name']}\" alterado para \"{$status}\".");

        return response()->json($updated);
    }

    public function renameUser(Request $request, string $id): JsonResponse
    {
        $data = $this->validateInput($request, [
            'name' => ['required', 'string', 'min:2', 'max:150'],
        ]);

        $newName = $this->stringField($data, 'name');
        $updated = $this->auth->renameUser($id, $newName, $this->requester($request));
        $this->audit->log($request, 'Alteração de Nome', "Usuário {$id} renomeado para \"{$newName}\".");

        return response()->json($updated);
    }

    public function removeUser(Request $request, string $id): JsonResponse
    {
        $sub = $this->requester($request)['sub'];
        if ($id === $sub) {
            throw new ApiException(400, 'BAD_REQUEST', 'Você não pode remover a própria conta administrativa.');
        }
        // Motivo opcional no corpo: a auditoria pergunta por quê, não só quem.
        $motivo = $request->input('motivo');
        $this->auth->deleteUser(
            $id,
            $this->requester($request),
            is_string($motivo) && trim($motivo) !== '' ? trim($motivo) : null
        );
        $this->audit->log($request, 'Inativação de Usuário', "Usuário {$id} inativado (registro preservado).");

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
