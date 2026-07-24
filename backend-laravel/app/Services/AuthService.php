<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Support\Jwt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Regras de negócio de autenticação — espelha src/server/services/authService.ts.
 *
 * Hash de senha usa as funções NATIVAS do PHP (password_verify/password_hash com
 * PASSWORD_BCRYPT cost 10), não o Hash facade do Laravel: isso garante interop com
 * os hashes $2a$ gerados pelo bcryptjs do Node (o facade rejeita esse prefixo) e
 * gera hashes $2y$ que o bcryptjs do Node também consegue verificar.
 */
final class AuthService
{
    private const MAX_FAILED_ATTEMPTS = 5;

    private const LOCKOUT_MINUTES = 15;

    private const BCRYPT_COST = 10;

    /**
     * @param  array<string, mixed>  $input
     * @param  array{sub:string,name:mixed,role:mixed}|null  $requester
     * @return array{token: string|null, user: array<string, mixed>}
     */
    public function register(array $input, ?array $requester): array
    {
        $isAdminProvisioning = ($requester['role'] ?? null) === 'admin';

        $requestedRole = $input['role'] ?? null;
        if (! $isAdminProvisioning && $requestedRole !== null && $requestedRole !== 'student') {
            throw ApiException::forbidden('Apenas administradores podem criar contas de instrutor ou administrador.');
        }

        if (User::query()->where('email', $input['email'])->exists()) {
            throw new ApiException(409, 'CONFLICT', 'Já existe um usuário cadastrado com este e-mail.');
        }

        $role = $isAdminProvisioning ? ($requestedRole ?? 'student') : 'student';
        $status = $isAdminProvisioning ? 'active' : 'pending_confirmation';

        $user = new User;
        $user->id = $this->generateId();
        $user->name = $input['name'];
        $user->email = $input['email'];
        $user->passwordHash = $this->hash($input['password']);
        $user->role = $role;
        $user->status = $status;
        $user->cpf = $input['cpf'] ?? null;
        $user->municipio = $input['municipio'] ?? null;
        $user->uf = $input['uf'] ?? null;
        $user->areaInteresse = $input['areaInteresse'] ?? null;
        $user->dataCadastro = $input['dataCadastro'] ?? CarbonImmutable::now()->format('Y-m-d');
        $user->failedLoginAttempts = 0;
        $user->lockedUntil = null;
        $user->save();

        // Token só para a própria pessoa quando a conta nasce ativa (cadastro público
        // vira pending -> sem token; provisionamento por admin é conta de terceiro -> sem token).
        $token = ($status === 'active' && ! $isAdminProvisioning)
            ? Jwt::issue($user->id, $user->name, $user->role)
            : null;

        return ['token' => $token, 'user' => $this->toPublicUser($user)];
    }

    /**
     * @param  array{name?:string,email?:string,password:string}  $input
     * @return array{token: string, user: array<string, mixed>}
     */
    public function login(array $input): array
    {
        $user = ! empty($input['email'])
            ? User::query()->where('email', $input['email'])->first()
            : User::query()->where('name', $input['name'] ?? '')->first();

        // Mensagem genérica sempre — nunca revela se o identificador existe.
        if ($user === null) {
            throw ApiException::unauthorized('Usuário ou senha inválidos.');
        }

        if ($user->lockedUntil !== null && $user->lockedUntil->isFuture()) {
            throw new ApiException(
                429,
                'ACCOUNT_LOCKED',
                'Conta temporariamente bloqueada após várias tentativas de login. Tente novamente em alguns minutos.',
            );
        }

        if (! password_verify($input['password'], $user->passwordHash)) {
            $nextAttempts = $user->failedLoginAttempts + 1;
            $shouldLock = $nextAttempts >= self::MAX_FAILED_ATTEMPTS;
            $user->failedLoginAttempts = $shouldLock ? 0 : $nextAttempts;
            $user->lockedUntil = $shouldLock
                ? CarbonImmutable::now()->addMinutes(self::LOCKOUT_MINUTES)
                : null;
            $user->save();
            throw ApiException::unauthorized('Usuário ou senha inválidos.');
        }

        if ($user->failedLoginAttempts > 0 || $user->lockedUntil !== null) {
            $user->failedLoginAttempts = 0;
            $user->lockedUntil = null;
            $user->save();
        }

        // Senha correta mas conta sem acesso: NÃO emite token (distingue o motivo).
        if ($user->status === 'blocked') {
            throw ApiException::accountBlocked();
        }
        if ($user->status === 'pending_confirmation') {
            throw new ApiException(
                403,
                'ACCOUNT_PENDING_CONFIRMATION',
                'Seu cadastro ainda está aguardando confirmação da coordenação. Você será liberado para acessar a plataforma assim que for homologado.',
            );
        }

        $token = Jwt::issue($user->id, $user->name, $user->role);

        return ['token' => $token, 'user' => $this->toPublicUser($user)];
    }

    /** @return array<string, mixed> */
    public function getCurrentUser(string $userId): array
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            throw ApiException::notFound('Usuário não encontrado.');
        }

        return $this->toPublicUser($user);
    }

    public function changePassword(string $userId, string $newPassword, ?string $currentPassword): void
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            throw ApiException::notFound('Usuário não encontrado.');
        }

        if ($currentPassword !== null && $currentPassword !== '') {
            if (! password_verify($currentPassword, $user->passwordHash)) {
                throw ApiException::unauthorized('Senha atual incorreta.');
            }
        }

        $user->passwordHash = $this->hash($newPassword);
        $user->save();
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function listUsersByRole(?string $role, int $skip, int $take): array
    {
        $query = User::query();
        if ($role !== null) {
            $query->where('role', $role);
        }
        $total = (clone $query)->count();
        $items = $query->orderBy('createdAt')->skip($skip)->take($take)->get()
            ->map(fn (User $u) => $this->toPublicUser($u))->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Escopo por perfil: instrutor só vê alunos matriculados/admitidos nos próprios
     * cursos. Cursos por FK (instructorId) com fallback por nome (legado); alunos por
     * FK (userId) com fallback por nome. Espelha listStudentsForInstructor do Node.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function listStudentsForInstructor(string $instructorSub, string $instructorName, int $skip, int $take): array
    {
        $courseIds = DB::table('Course')
            ->where('instructorId', $instructorSub)
            ->orWhere(function ($q) use ($instructorName): void {
                $q->whereNull('instructorId')->where('instructorName', $instructorName);
            })
            ->pluck('id')
            ->all();

        if (count($courseIds) === 0) {
            return ['items' => [], 'total' => 0];
        }

        $admissions = DB::table('AdmissionRequest')
            ->whereIn('courseId', $courseIds)
            ->where('status', 'approved')
            ->get(['studentName', 'userId']);
        $enrollments = DB::table('StudentEnrollment')
            ->whereIn('enrolledCourseId', $courseIds)
            ->get(['studentName', 'userId']);

        $linked = $admissions->concat($enrollments);
        $studentIds = $linked->pluck('userId')->filter()->unique()->values()->all();
        $legacyNames = $linked->filter(fn ($r) => empty($r->userId))
            ->pluck('studentName')->unique()->values()->all();

        if (count($studentIds) === 0 && count($legacyNames) === 0) {
            return ['items' => [], 'total' => 0];
        }

        $query = User::query()->where('role', 'student')->where(function ($q) use ($studentIds, $legacyNames): void {
            if (count($studentIds) > 0) {
                $q->orWhereIn('id', $studentIds);
            }
            if (count($legacyNames) > 0) {
                $q->orWhereIn('name', $legacyNames);
            }
        });

        $total = (clone $query)->count();
        $items = $query->orderBy('createdAt')->skip($skip)->take($take)->get()
            ->map(fn (User $u) => $this->toPublicUser($u))->all();

        return ['items' => $items, 'total' => $total];
    }

    /** @return array<string, mixed> */
    public function updateAccountStatus(string $userId, string $status): array
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            throw ApiException::notFound('Usuário não encontrado.');
        }
        $user->status = $status;
        $user->save();

        return $this->toPublicUser($user);
    }

    public function deleteUser(string $userId): void
    {
        User::query()->where('id', $userId)->delete();
    }

    /** @return array<string, mixed> */
    public function toPublicUser(User $user): array
    {
        $public = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
        ];
        // Campos opcionais só aparecem quando presentes (mesma forma do toPublicUser do Node).
        foreach (['municipio', 'uf', 'areaInteresse', 'dataCadastro'] as $field) {
            if ($user->{$field} !== null) {
                $public[$field] = $user->{$field};
            }
        }

        return $public;
    }

    private function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
    }

    private function generateId(): string
    {
        // Id opaco e único para novas contas criadas pelo Laravel (o Prisma usa cuid();
        // o formato não importa para integridade — só precisa ser único e estável).
        return 'usr_'.bin2hex(random_bytes(12));
    }
}
