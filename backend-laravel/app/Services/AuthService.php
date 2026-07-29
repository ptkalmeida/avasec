<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Course;
use App\Models\User;
use App\Support\Identity;
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
 *
 * @phpstan-type PublicUser array{id: string, name: string, email: string, role: string, status: string, municipio?: string, uf?: string, areaInteresse?: string, dataCadastro?: string}
 */
final class AuthService
{
    private const MAX_FAILED_ATTEMPTS = 5;

    private const LOCKOUT_MINUTES = 15;

    private const BCRYPT_COST = 10;

    /**
     * @param  array{name:string,email:string,password:string,role?:string|null,cpf?:string|null,municipio?:string|null,uf?:string|null,areaInteresse?:string|null,dataCadastro?:string|null}  $input
     * @param  array{sub:string,name:string,role:string}|null  $requester
     * @return array{token: string|null, user: PublicUser}
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

        $role = 'student';
        if ($isAdminProvisioning && in_array($requestedRole, ['student', 'instructor', 'admin'], true)) {
            $role = $requestedRole;
        }
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
     * @param  array{name?:string|null,email?:string|null,password:string}  $input
     * @return array{token: string, user: PublicUser}
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

    /** @return PublicUser */
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
        $requester = ['sub' => $instructorSub, 'name' => $instructorName, 'role' => 'instructor'];
        $courseIds = Identity::applyOwnRows(Course::query(), $requester, 'instructorId', 'instructorName')
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

    /** @return PublicUser */
    public function updateAccountStatus(string $userId, string $status): array
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            throw ApiException::notFound('Usuário não encontrado.');
        }
        if (! in_array($status, ['active', 'blocked', 'pending_confirmation'], true)) {
            throw ApiException::validation('Status de conta inválido.');
        }
        $user->status = $status;
        $user->save();

        return $this->toPublicUser($user);
    }

    public function deleteUser(string $userId): void
    {
        User::query()->where('id', $userId)->delete();
    }

    /**
     * Rename seguro (ADR 10): identidade é o id, então mudar o nome é atualizar
     * o User e os SNAPSHOTS de exibição das satélites na mesma transação.
     * EXCEÇÃO: Certificate.studentName fica intocado — é o nome impresso no PDF
     * emitido e a chave humana da verificação pública (documento histórico).
     *
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return PublicUser
     */
    public function renameUser(string $userId, string $newName, array $requester): array
    {
        if ($requester['role'] !== 'admin' && $requester['sub'] !== $userId) {
            throw ApiException::forbidden('Você só pode alterar o próprio nome.');
        }
        $user = User::query()->find($userId);
        if ($user === null) {
            throw ApiException::notFound('Usuário não encontrado.');
        }

        DB::transaction(function () use ($user, $newName): void {
            $user->name = $newName;
            $user->save();

            foreach (['StudentEnrollment', 'StudentProgress', 'AdmissionRequest', 'AcademicRequest', 'QuizSubmission', 'ExerciseSubmission'] as $table) {
                DB::table($table)->where('userId', $user->id)->update(['studentName' => $newName]);
            }
            DB::table('DirectMessage')->where('studentUserId', $user->id)->update(['studentName' => $newName]);
            foreach (['DirectMessage', 'ChatMessage', 'ForumMessage'] as $table) {
                DB::table($table)->where('senderUserId', $user->id)->update(['senderName' => $newName]);
            }
            DB::table('Course')->where('instructorId', $user->id)->update(['instructorName' => $newName]);
        });

        return $this->toPublicUser($user);
    }

    /** @return PublicUser */
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
        if ($user->municipio !== null) {
            $public['municipio'] = $user->municipio;
        }
        if ($user->uf !== null) {
            $public['uf'] = $user->uf;
        }
        if ($user->areaInteresse !== null) {
            $public['areaInteresse'] = $user->areaInteresse;
        }
        if ($user->dataCadastro !== null) {
            $public['dataCadastro'] = $user->dataCadastro;
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
