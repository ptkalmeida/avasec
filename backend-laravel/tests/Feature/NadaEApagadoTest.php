<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Certificado;
use App\Models\Curso;
use App\Models\ProgressoAluno;
use App\Models\TentativaAvaliacao;
use App\Models\Usuario;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * ADR 12 — nada é apagado.
 *
 * Registro de pessoa, disciplina, conteúdo ou avaliação não sai do banco: sai do
 * ar. Estes testes existem porque a versão anterior apagava fisicamente em 14
 * pontos, com as chaves estrangeiras em ON DELETE CASCADE — apagar UM usuário
 * destruía notas, entregas corrigidas, progresso, matrícula e requerimentos, e
 * deixava o certificado apontando para ninguém.
 */
final class NadaEApagadoTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /** @return array<string, string> */
    private function auth(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"];
    }

    private function cursoDoInstrutor(): Curso
    {
        $instrutor_id = DB::table('usuario')->where('papel', 'instructor')->where('status', 'active')->value('id');
        $curso = Curso::query()->where('instrutor_id', $instrutor_id)->first();
        $this->assertNotNull($curso, 'Seed sem curso do instrutor.');

        return $curso;
    }

    public function test_excluir_aluno_preserva_o_historico_academico_inteiro(): void
    {
        $curso = $this->cursoDoInstrutor();
        $aluno = $this->makeStudent('Aluno Historico');

        // Histórico: uma nota, um progresso e um certificado.
        TentativaAvaliacao::query()->create([
            'id' => 'sub-hist-'.uniqid(),
            'usuario_id' => $aluno['id'],
            'nome_aluno' => $aluno['nome'],
            'curso_id' => $curso->id,
            'avaliacao_id' => 'quiz-1',
            'percentual_nota' => 90,
            'aprovado' => true,
            'enviado_em_texto' => '03/09/2026 às 10:00',
        ]);
        ProgressoAluno::query()->create([
            'id' => 'prog-hist-'.uniqid(),
            'usuario_id' => $aluno['id'],
            'nome_aluno' => $aluno['nome'],
            'curso_id' => $curso->id,
            'aulas_concluidas' => [],
            'sessoes_ao_vivo_assistidas' => [],
        ]);
        Certificado::query()->create([
            'id' => 'cert-hist-'.uniqid(),
            'nome_aluno' => $aluno['nome'],
            'usuario_id' => $aluno['id'],
            'curso_id' => $curso->id,
            'titulo_curso' => $curso->titulo,
            'data_emissao' => now(),
            'percentual_frequencia' => 100,
            'hash_verificacao' => 'AVA-TESTE-'.strtoupper(uniqid()),
        ]);

        $this->deleteJson("/api/autenticacao/usuarios/{$aluno['id']}", ['motivo' => 'Pedido da secretaria'], $this->auth($this->staffToken('admin')))
            ->assertOk();

        // A pessoa saiu do ar...
        $this->assertNotNull(DB::table('usuario')->where('id', $aluno['id'])->value('inativado_em'));
        $this->assertNull(Usuario::query()->find($aluno['id']), 'Usuário inativado não deve aparecer nas consultas padrão.');
        // ...mas continua no banco, com quem inativou e por quê.
        $registro = DB::table('usuario')->where('id', $aluno['id'])->first();
        $this->assertNotNull($registro, 'O usuário NÃO pode ser apagado do banco.');
        $this->assertSame('Pedido da secretaria', $registro->motivo_inativacao);
        $this->assertNotNull($registro->inativado_por, 'Falta o registro de quem inativou.');

        // E o histórico acadêmico — que o CASCADE destruía — está intacto.
        $this->assertSame(1, DB::table('tentativa_avaliacao')->where('usuario_id', $aluno['id'])->count(), 'Nota de avaliação foi perdida.');
        $this->assertSame(1, DB::table('progresso_aluno')->where('usuario_id', $aluno['id'])->count(), 'Progresso foi perdido.');
        $this->assertSame(1, DB::table('certificado')->where('usuario_id', $aluno['id'])->count(), 'Certificado foi perdido.');
        // O certificado continua ligado à pessoa (era SET NULL: ficava órfão).
        $this->assertNotNull(DB::table('certificado')->where('usuario_id', $aluno['id'])->value('usuario_id'));
    }

    public function test_conta_inativada_nao_entra_mais(): void
    {
        // Preservar o registro não pode virar porta aberta: a conta tem de
        // deixar de autenticar.
        $aluno = $this->makeStudent('Aluno Bloqueado');
        $this->deleteJson("/api/autenticacao/usuarios/{$aluno['id']}", [], $this->auth($this->staffToken('admin')))->assertOk();

        $this->assertSame('blocked', DB::table('usuario')->where('id', $aluno['id'])->value('status'));

        // /api/cursos é o catálogo PÚBLICO (jwt.optional): não serve de prova.
        // A rota tem de exigir conta ativa para o teste medir algo.
        $this->getJson('/api/avaliacoes', $this->auth($aluno['token']))->assertStatus(403);

        // E o login também é recusado. 401 e não 403 de propósito: a busca por
        // CPF não encontra a conta inativada, e responder "credenciais
        // inválidas" é o certo — dizer "esta conta foi inativada" revelaria a
        // quem tentou que aquele CPF existe na base.
        $this->postJson('/api/autenticacao/entrar', [
            'cpf' => $aluno['cpf'],
            'password' => 'senha123456',
        ])->assertStatus(401);
    }

    public function test_curso_inativado_sai_do_catalogo_mas_fica_no_banco(): void
    {
        $curso = $this->cursoDoInstrutor();

        $this->deleteJson("/api/cursos/{$curso->id}", ['motivo' => 'Turma encerrada'], $this->auth($this->staffToken('admin')))
            ->assertOk();

        $this->assertDatabaseHas('curso', ['id' => $curso->id]);
        $this->assertSame('Turma encerrada', DB::table('curso')->where('id', $curso->id)->value('motivo_inativacao'));

        $catalogo = $this->getJson('/api/cursos')->assertOk()->json();
        $ids = array_column(is_array($catalogo['items'] ?? null) ? $catalogo['items'] : $catalogo, 'id');
        $this->assertNotContains($curso->id, $ids, 'Curso inativado não pode aparecer no catálogo.');
    }

    public function test_a_trilha_de_auditoria_nao_pode_ser_apagada(): void
    {
        // Isto apagava LogSeguranca inteiro — inclusive o registro de quem apagou.
        $antes = DB::table('log_seguranca')->count();
        $this->assertGreaterThan(0, $antes, 'Seed sem log de segurança para o teste valer.');

        $this->deleteJson('/api/logs-seguranca', [], $this->auth($this->staffToken('admin')))->assertStatus(403);

        $this->assertSame($antes, DB::table('log_seguranca')->count());
    }

    public function test_avaliacao_inativada_preserva_as_notas_dos_alunos(): void
    {
        $curso = $this->cursoDoInstrutor();
        $aluno = $this->makeStudent('Aluno Nota');
        $token = $this->staffToken('instructor');

        $quiz = $this->postJson('/api/avaliacoes', [
            'curso_id' => $curso->id,
            'titulo' => 'Avaliação a ser retirada',
            'questoes' => [['texto_questao' => 'P?', 'opcoes' => ['A', 'B'], 'indice_opcao_correta' => 0]],
        ], $this->auth($token))->assertStatus(201)->json();

        TentativaAvaliacao::query()->create([
            'id' => 'sub-nota-'.uniqid(),
            'usuario_id' => $aluno['id'],
            'nome_aluno' => $aluno['nome'],
            'curso_id' => $curso->id,
            'avaliacao_id' => $quiz['id'],
            'percentual_nota' => 80,
            'aprovado' => true,
            'enviado_em_texto' => '03/09/2026 às 11:00',
        ]);

        $this->deleteJson("/api/avaliacoes/{$quiz['id']}", [], $this->auth($token))->assertOk();

        // A avaliação saiu do ar; a nota que o aluno tirou continua no banco.
        $this->assertNotNull(DB::table('avaliacao')->where('id', $quiz['id'])->value('inativado_em'));
        $this->assertSame(
            1,
            DB::table('tentativa_avaliacao')->where('avaliacao_id', $quiz['id'])->whereNull('inativado_em')->count(),
            'A nota do aluno foi apagada junto com a avaliação.'
        );
    }

    public function test_certificado_revogado_nao_e_apagado(): void
    {
        $curso = $this->cursoDoInstrutor();
        $aluno = $this->makeStudent('Aluno Cert');
        $id = 'cert-rev-'.uniqid();

        Certificado::query()->create([
            'id' => $id,
            'nome_aluno' => $aluno['nome'],
            'usuario_id' => $aluno['id'],
            'curso_id' => $curso->id,
            'titulo_curso' => $curso->titulo,
            'data_emissao' => now(),
            'percentual_frequencia' => 100,
            'hash_verificacao' => 'AVA-REV-'.strtoupper(uniqid()),
        ]);

        $this->deleteJson("/api/certificados/{$id}", [], $this->auth($this->staffToken('admin')))->assertOk();

        // Certificado é documento: pode estar impresso ou anexado a um processo.
        $this->assertDatabaseHas('certificado', ['id' => $id]);
        $this->assertNotNull(DB::table('certificado')->where('id', $id)->value('inativado_em'));
    }

    public function test_aula_retirada_e_readicionada_volta_sem_estourar_a_chave(): void
    {
        /*
         * Regressão específica da inativação: com o SoftDeletes, `find()` não
         * encontra a aula inativa. Sem `withTrashed()` no upsert, readicionar a
         * mesma aula tentaria INSERT e estouraria a chave primária.
         */
        $curso = Curso::query()->with('aulas')->find($this->cursoDoInstrutor()->id);
        $this->assertNotNull($curso);
        $aula = $curso->aulas->first();
        $this->assertNotNull($aula, 'Curso do seed sem aula.');

        $paraPayload = fn ($lessons) => $lessons->map(fn ($l) => [
            'id' => $l->id, 'titulo' => $l->titulo, 'conteudo' => $l->conteudo ?? 'x',
            'duracao' => $l->duracao ?? '10 min', 'order' => $l->ordem ?? 1,
        ])->values()->all();

        $token = $this->auth($this->staffToken('instructor'));
        $base = ['titulo' => $curso->titulo, 'descricao' => $curso->descricao, 'categoria' => $curso->categoria];

        // Retira a primeira aula...
        $this->putJson("/api/cursos/{$curso->id}",
            $base + ['aulas' => $paraPayload($curso->aulas->slice(1))], $token)->assertOk();
        $this->assertNotNull(DB::table('aula')->where('id', $aula->id)->value('inativado_em'));

        // ...e devolve com o MESMO id.
        $this->putJson("/api/cursos/{$curso->id}",
            $base + ['aulas' => $paraPayload($curso->aulas)], $token)->assertOk();

        $this->assertNull(
            DB::table('aula')->where('id', $aula->id)->value('inativado_em'),
            'Aula readicionada tem de voltar ao ar, não duplicar.'
        );
        $this->assertSame(1, DB::table('aula')->where('id', $aula->id)->count());
    }

    public function test_escada_de_audiencia_do_status(): void
    {
        $this->assertSame(Visibilidade::PUBLICADO, Visibilidade::nivelMaximoDoPapel('student'));
        $this->assertSame(Visibilidade::PUBLICADO, Visibilidade::nivelMaximoDoPapel(null));
        $this->assertSame(Visibilidade::RESTRITO, Visibilidade::nivelMaximoDoPapel('instructor'));
        $this->assertSame(Visibilidade::RASCUNHO, Visibilidade::nivelMaximoDoPapel('admin'));

        // Quem vê o nível 1 vê tudo acima; o aluno não alcança restrito.
        $this->assertTrue(Visibilidade::papelAlcanca('student', Visibilidade::PUBLICADO));
        $this->assertFalse(Visibilidade::papelAlcanca('student', Visibilidade::RESTRITO));
        $this->assertTrue(Visibilidade::papelAlcanca('admin', Visibilidade::RASCUNHO));

        // Nível inventado pelo cliente cai em publicado, não vira coluna inválida.
        $this->assertSame(Visibilidade::PUBLICADO, Visibilidade::normalizar(99));
        $this->assertSame(Visibilidade::PUBLICADO, Visibilidade::normalizar('abc'));
        $this->assertSame(Visibilidade::RASCUNHO, Visibilidade::normalizar('3'));
    }

    public function test_curso_restrito_nao_aparece_para_o_aluno(): void
    {
        $curso = $this->cursoDoInstrutor();
        DB::table('curso')->where('id', $curso->id)->update(['status' => Visibilidade::RESTRITO]);
        $aluno = $this->makeStudent('Aluno Restrito');

        $doAluno = $this->getJson('/api/cursos', $this->auth($aluno['token']))->assertOk()->json();
        $ids = array_column(is_array($doAluno['items'] ?? null) ? $doAluno['items'] : $doAluno, 'id');
        $this->assertNotContains($curso->id, $ids);

        // Para o admin, continua visível.
        $doAdmin = $this->getJson('/api/cursos', $this->auth($this->staffToken('admin')))->assertOk()->json();
        $idsAdmin = array_column(is_array($doAdmin['items'] ?? null) ? $doAdmin['items'] : $doAdmin, 'id');
        $this->assertContains($curso->id, $idsAdmin);
    }
}
