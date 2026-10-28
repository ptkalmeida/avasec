<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Matricula;
use App\Support\Fuso;
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
        $res = $this->withHeader('Authorization', "Bearer {$adminToken}")->postJson('/api/autenticacao/cadastro', [
            'nome' => $name,
            'email' => 'id-'.uniqid().'@example.com',
            'password' => 'senha123456',
            'papel' => 'student',
            // CPF é obrigatório para conta de aluno (ADR 11) — é o login dela.
            'cpf' => $cpf,
        ]);
        $res->assertStatus(201);
        $id = $res->json('usuario.id');
        $this->flushHeaders();

        return ['id' => $id, 'nome' => $name, 'cpf' => $cpf, 'token' => Jwt::issue($id, $name, 'student')];
    }

    /**
     * Matricula o aluno no curso. O progresso e o certificado só existem para quem PERTENCE ao
     * curso (varredura de segurança de 23/10/2026): um teste que grava progresso sem matricular
     * está modelando justamente o aluno que não cursa.
     *
     * @param  array{id: string, name: string}  $aluno
     */
    protected function matricular(array $aluno, string $curso_id): void
    {
        Matricula::query()->updateOrCreate(
            ['usuario_id' => $aluno['id']],
            [
                'id' => $aluno['id'], 'nome_aluno' => $aluno['nome'], 'curso_matriculado_id' => $curso_id,
                'cursos_concluidos_ids' => [], 'cursos_extras_ids' => [], 'pode_multimatricula' => false,
            ],
        );
    }

    protected function staffToken(string $role): string
    {
        $u = DB::table('usuario')->where('papel', $role)->where('status', 'active')->first(['id', 'nome', 'papel']);
        $this->assertNotNull($u, "Seed sem usuário ativo com role '{$role}'.");

        return Jwt::issue($u->id, $u->nome, $u->papel);
    }

    protected function anySeededCourseId(): string
    {
        // Um curso em que dá para matricular: publicado, não inativado e no prazo. Sem isto
        // o sorteio caía num curso INATIVADO (`DB::table` ignora o SoftDeletes) ou vencido.
        $hoje = Fuso::agora()->format('Y-m-d');
        $id = DB::table('curso')
            ->whereNull('inativado_em')
            ->where('status', 1)
            ->where(static fn ($q) => $q->whereNull('vigencia_ate')->orWhere('vigencia_ate', '>=', $hoje))
            ->orderBy('id')
            ->value('id');
        $this->assertNotNull($id, 'Seed sem cursos ativos no prazo — popular o MySQL de dev.');

        return $id;
    }

    protected function anotherSeededCourseId(string $excludeId): string
    {
        /*
         * No prazo, de propósito. Desde 21/10 curso com a vigência vencida não
         * aceita matrícula nova, e o banco de desenvolvimento tem um vencido —
         * três testes de visibilidade de material passaram a falhar com 409 ao
         * sortearem justamente ele. Eles medem outra coisa, e continuam medindo.
         */
        $hoje = Fuso::agora()->format('Y-m-d');
        $id = DB::table('curso')->where('id', '!=', $excludeId)
            // `DB::table` não aplica o SoftDeletes do model: sem esta linha o
            // sorteio cai num curso INATIVADO e a API responde 404.
            ->whereNull('inativado_em')
            ->where(static fn ($q) => $q->whereNull('vigencia_ate')->orWhere('vigencia_ate', '>=', $hoje))
            ->value('id');
        $this->assertNotNull($id, 'Seed sem um segundo curso distinto e no prazo — popular o MySQL de dev.');

        return $id;
    }

    /**
     * Os testes foram escritos sobre os cursos de seed (`course-1`, `course-2`, `course-3`),
     * mas o MySQL de dev é mantido à mão: em 27/10/2026 os três foram inativados pela tela do
     * admin (e continuam assim, de propósito — a vitrine mostra só o que é real). Em vez de
     * exigir que o dev volte ao estado de seed, o teste DECLARA o pré-requisito: reativa os
     * três, mas só dentro da transação do próprio teste, que o DatabaseTransactions reverte.
     * O dado real não muda. Recusa rodar sem transação aberta, para nunca gravar no banco de
     * verdade se alguém esquecer o trait.
     */
    protected function ativarCursosDeSeed(): void
    {
        $this->assertGreaterThan(0, DB::transactionLevel(), 'ativarCursosDeSeed() exige DatabaseTransactions: sem transação ele gravaria no banco de dev.');

        DB::table('curso')->whereIn('id', ['course-1', 'course-2', 'course-3'])->update([
            'inativado_em' => null,
            'inativado_por' => null,
            'motivo_inativacao' => null,
        ]);
    }
}
