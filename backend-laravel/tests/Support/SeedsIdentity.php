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
    use GeneratesCpf;

    /** @return array{id: string, name: string, cpf: string, token: string} */
    protected function makeStudent(string $prefix = 'Aluno Teste', ?string $exactName = null): array
    {
        $adminToken = $this->staffToken('admin');
        $name = $exactName ?? $prefix.' '.uniqid();
        $cpf = $this->makeCpf();
        $res = $this->withHeader('Authorization', "Bearer {$adminToken}")->postJson('/api/auth/register', [
            'name' => $name,
            'email' => 'id-'.uniqid().'@example.com',
            'password' => 'senha123456',
            'role' => 'student',
            // CPF é obrigatório para conta de aluno (ADR 11) — é o login dela.
            'cpf' => $cpf,
        ]);
        $res->assertStatus(201);
        $id = $res->json('user.id');
        $this->flushHeaders();

        return ['id' => $id, 'name' => $name, 'cpf' => $cpf, 'token' => Jwt::issue($id, $name, 'student')];
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

    protected function anotherSeededCourseId(string $excludeId): string
    {
        $id = DB::table('Course')->where('id', '!=', $excludeId)->value('id');
        $this->assertNotNull($id, 'Seed sem um segundo curso distinto — popular o MySQL de dev.');

        return $id;
    }
}
