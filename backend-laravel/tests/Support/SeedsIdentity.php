<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Jwt;
use Illuminate\Support\Facades\DB;

/**
 * Fixtures de identidade (ADR 10): cria usuários reais via registro e devolve
 * id+token, para que as satélites de teste nasçam sempre com FK preenchida.
 */
trait SeedsIdentity
{
    /** @return array{id: string, name: string, token: string} */
    protected function makeStudent(string $prefix = 'Aluno Teste', ?string $exactName = null): array
    {
        $adminToken = $this->staffToken('admin');
        $name = $exactName ?? $prefix.' '.uniqid();
        $res = $this->withHeader('Authorization', "Bearer {$adminToken}")->postJson('/api/auth/register', [
            'name' => $name,
            'email' => 'id-'.uniqid().'@example.com',
            'password' => 'senha123456',
            'role' => 'student',
        ]);
        $res->assertStatus(201);
        $id = $res->json('user.id');
        $this->flushHeaders();

        return ['id' => $id, 'name' => $name, 'token' => Jwt::issue($id, $name, 'student')];
    }

    protected function staffToken(string $role): string
    {
        $u = DB::table('User')->where('role', $role)->where('status', 'active')->first(['id', 'name', 'role']);
        $this->assertNotNull($u, "Seed sem usuário ativo com role '{$role}'.");

        return Jwt::issue($u->id, $u->name, $u->role);
    }

    protected function anySeededCourseId(): string
    {
        $id = DB::table('Course')->value('id');
        $this->assertNotNull($id, 'Seed sem cursos — popular o MySQL de dev.');

        return $id;
    }
}
