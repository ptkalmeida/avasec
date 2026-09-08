<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Escopo das mensagens diretas, agora que `mensagensDiretas` está LIGADA.
 *
 * Por que este arquivo nasceu junto com a flag: o relatório de auditoria afirma
 * que "os testes de escopo ligam as flags explicitamente e cobram a regra com
 * elas ativas, para que ligar a funcionalidade não reabra o vazamento". Para
 * esta flag isso **não era verdade** — nenhum teste do backend a ligava, então
 * `/api/dms` ia entrar no ar com o escopo implementado e não verificado.
 *
 * O que se protege aqui é o que já custou correção neste projeto: antes,
 * qualquer instrutor fazia dump da caixa de mensagens diretas da escola inteira.
 * Mensagem de suporte carrega dúvida de aluno e o que ele quiser escrever.
 */
final class MensagensDiretasEscopoTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    /** @return array<string, string> */
    private function auth(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"];
    }

    /** Matricula o aluno num curso pela rota real, para entrar no escopo do instrutor. */
    private function matricular(string $token, string $courseId): void
    {
        $this->withHeaders($this->auth($token))
            ->postJson('/api/enrollments/self/enroll', ['courseId' => $courseId])
            ->assertOk();
        $this->flushHeaders();
    }

    /** Instrutor que NÃO é responsável por curso nenhum. */
    private function instrutorSemCurso(): ?string
    {
        $comCurso = DB::table('Course')->whereNotNull('instructorId')->pluck('instructorId')->all();
        $u = DB::table('User')->where('role', 'instructor')->where('status', 'active')
            ->whereNotIn('id', $comCurso === [] ? [''] : $comCurso)
            ->first(['id', 'name', 'role']);

        return $u === null ? null : Jwt::issue($u->id, $u->name, $u->role);
    }

    private function enviar(string $token, string $texto): void
    {
        $this->withHeaders($this->auth($token))
            ->postJson('/api/dms', ['text' => $texto])
            ->assertStatus(201);
        $this->flushHeaders();
    }

    public function test_aluno_le_so_o_proprio_canal(): void
    {
        $a = $this->makeStudent('DM Aluno A');
        $b = $this->makeStudent('DM Aluno B');

        $this->enviar($a['token'], 'duvida do aluno A');
        $this->enviar($b['token'], 'duvida do aluno B');

        $itens = $this->getJson('/api/dms', $this->auth($a['token']))->assertOk()->json();
        $this->assertIsArray($itens);
        $this->assertNotSame([], $itens, 'O aluno A não viu a própria mensagem.');
        foreach ($itens as $dm) {
            $this->assertSame($a['id'], $dm['studentUserId'] ?? null, 'Aluno recebeu canal de outro.');
        }
    }

    public function test_aluno_nao_consegue_escrever_no_canal_de_outro(): void
    {
        /*
         * O corpo aceita `studentUserId` porque a equipe endereça o aluno por id
         * (ADR 10). Vindo de um aluno, o campo tem de ser recusado.
         *
         * E a recusa é 403 EXPLÍCITO, não um redirecionamento silencioso para o
         * canal do próprio autor. A diferença importa: aceitar calado com o dono
         * trocado gravaria uma mensagem que a pessoa não sabe onde foi parar, e
         * esconderia a tentativa de quem audita.
         */
        $a = $this->makeStudent('DM Autor');
        $vitima = $this->makeStudent('DM Vitima');

        $this->withHeaders($this->auth($a['token']))
            ->postJson('/api/dms', ['studentUserId' => $vitima['id'], 'text' => 'tentativa de invasao'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Você só pode agir em seu próprio nome.');
        $this->flushHeaders();

        // Nada foi gravado em canal nenhum.
        $this->assertSame([], $this->getJson('/api/dms', $this->auth($vitima['token']))->assertOk()->json());
        $this->assertSame([], $this->getJson('/api/dms', $this->auth($a['token']))->assertOk()->json());
    }

    public function test_instrutor_le_dms_dos_proprios_alunos(): void
    {
        $curso = $this->anySeededCourseId();
        $instrutorDoCurso = DB::table('Course')->where('id', $curso)->value('instructorId');
        if (! is_string($instrutorDoCurso) || $instrutorDoCurso === '') {
            $this->markTestSkipped('Curso semeado sem instructorId.');
        }

        $aluno = $this->makeStudent('DM Meu Aluno');
        $this->matricular($aluno['token'], $curso);
        $this->enviar($aluno['token'], 'duvida sobre a aula 1');

        $dono = DB::table('User')->where('id', $instrutorDoCurso)->first(['id', 'name', 'role']);
        $token = Jwt::issue($dono->id, $dono->name, 'instructor');

        $itens = $this->getJson('/api/dms', $this->auth($token))->assertOk()->json();
        $ids = array_map(static fn ($d) => $d['studentUserId'] ?? null, $itens);
        $this->assertContains($aluno['id'], $ids, 'Instrutor não viu a DM do próprio aluno.');
    }

    public function test_instrutor_sem_curso_nao_le_dm_de_ninguem(): void
    {
        // A guarda que importa: escopo vazio devolve nada, e NÃO "tudo".
        // `whereIn` com lista vazia é o erro clássico que inverte esta regra.
        $token = $this->instrutorSemCurso();
        if ($token === null) {
            $this->markTestSkipped('Sem instrutor sem curso no banco de desenvolvimento.');
        }

        $aluno = $this->makeStudent('DM Alheio');
        $this->matricular($aluno['token'], $this->anySeededCourseId());
        $this->enviar($aluno['token'], 'duvida que nao e dele');

        $itens = $this->getJson('/api/dms', $this->auth($token))->assertOk()->json();
        $this->assertSame([], $itens, 'Instrutor sem curso leu a caixa de mensagens da escola.');
    }

    public function test_instrutor_nao_alcanca_canal_alheio_nem_pedindo_pelo_id(): void
    {
        $token = $this->instrutorSemCurso();
        if ($token === null) {
            $this->markTestSkipped('Sem instrutor sem curso no banco de desenvolvimento.');
        }

        $aluno = $this->makeStudent('DM Pedido Direto');
        $this->matricular($aluno['token'], $this->anySeededCourseId());
        $this->enviar($aluno['token'], 'conteudo sensivel');

        // IDOR: pedir o canal por id não pode furar o escopo por curso.
        $itens = $this->getJson('/api/dms?studentUserId='.$aluno['id'], $this->auth($token))
            ->assertOk()->json();
        $this->assertSame([], $itens, 'Escopo furado ao pedir o canal por studentUserId.');
    }

    public function test_admin_le_todos_os_canais(): void
    {
        $aluno = $this->makeStudent('DM Para Admin');
        $this->enviar($aluno['token'], 'duvida vista pelo admin');

        $itens = $this->getJson('/api/dms', $this->auth($this->staffToken('admin')))->assertOk()->json();
        $ids = array_map(static fn ($d) => $d['studentUserId'] ?? null, $itens);
        $this->assertContains($aluno['id'], $ids, 'Admin não é irrestrito, como a decisão exige.');
    }

    public function test_com_a_flag_desligada_a_rota_volta_a_404(): void
    {
        /*
         * A flag foi LIGADA em 08/09/2026 porque o suporte pedagógico depende
         * dela. Este teste prova que o portão continua existindo — se um dia ela
         * for desligada de novo, a rota fecha em vez de ficar meio aberta.
         */
        config(['features.mensagensDiretas' => false]);
        $aluno = $this->makeStudent('DM Flag Off');

        $this->getJson('/api/dms', $this->auth($aluno['token']))
            ->assertStatus(404)->assertJsonPath('code', 'FEATURE_DISABLED');
        $this->postJson('/api/dms', ['text' => 'nao deve entrar'], $this->auth($aluno['token']))
            ->assertStatus(404)->assertJsonPath('code', 'FEATURE_DISABLED');
    }
}
