<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\StudentProgress;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Revogação de certificado (ADR 12).
 *
 * Certificado é documento: foi emitido, tem hash de verificação pública e pode
 * estar impresso ou anexado a um processo. Revogar não pode significar
 * desaparecer — quem confere o papel precisa da resposta "foi revogado", que é
 * diferente de "nunca existiu".
 */
final class CertificadoRevogadoTest extends TestCase
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

    /** @return array{cert: Certificate, aluno: array{id: string, name: string, cpf: string, token: string}, curso: Course} */
    private function certificadoEmitido(): array
    {
        $instructorId = DB::table('User')->where('role', 'instructor')->where('status', 'active')->value('id');
        $curso = Course::query()->where('instructorId', $instructorId)->first();
        $this->assertNotNull($curso, 'Seed sem curso do instrutor.');

        $aluno = $this->makeStudent('Aluno Revogacao');
        $cert = Certificate::query()->create([
            'id' => 'cert-revog-'.uniqid(),
            'studentName' => $aluno['name'],
            'userId' => $aluno['id'],
            'courseId' => $curso->id,
            'courseTitle' => $curso->title,
            'issueDate' => now(),
            'attendancePercent' => 100,
            'verificationHash' => 'AVA-REVOG-'.strtoupper(uniqid()),
        ]);

        return ['cert' => $cert, 'aluno' => $aluno, 'curso' => $curso];
    }

    public function test_verificacao_publica_de_certificado_valido_diz_que_nao_esta_revogado(): void
    {
        $c = $this->certificadoEmitido();

        $this->getJson('/api/certificates/verify?q='.$c['cert']->verificationHash)
            ->assertOk()
            ->assertJsonPath('revogado', false)
            ->assertJsonPath('revogadoEm', null)
            ->assertJsonPath('motivoRevogacao', null);
    }

    public function test_verificacao_publica_encontra_certificado_revogado_e_o_declara_revogado(): void
    {
        // O ponto da revogação. Antes, `Certificate::query()` omitia o inativado
        // e a rota respondia "não encontrado" sobre um papel que existe.
        $c = $this->certificadoEmitido();
        $hash = $c['cert']->verificationHash;

        $this->deleteJson(
            "/api/certificates/{$c['cert']->id}",
            ['motivo' => 'Frequência apurada abaixo do mínimo.'],
            $this->auth($this->staffToken('admin'))
        )->assertOk();

        $this->getJson('/api/certificates/verify?q='.$hash)
            ->assertOk()
            ->assertJsonPath('revogado', true)
            ->assertJsonPath('motivoRevogacao', 'Frequência apurada abaixo do mínimo.')
            ->assertJsonPath('studentName', $c['aluno']['name']);
    }

    public function test_a_verificacao_publica_nunca_expoe_quem_revogou(): void
    {
        // Rota sem autenticação: identificador interno de servidor não sai daqui.
        $c = $this->certificadoEmitido();
        $hash = $c['cert']->verificationHash;
        $this->deleteJson(
            "/api/certificates/{$c['cert']->id}",
            ['motivo' => 'Erro de lançamento.'],
            $this->auth($this->staffToken('admin'))
        )->assertOk();

        $resposta = $this->getJson('/api/certificates/verify?q='.$hash)->assertOk();
        $resposta->assertJsonMissingPath('revogadoPor');
        $resposta->assertJsonMissingPath('inativadoPor');
        $resposta->assertJsonMissingPath('userId');
        $resposta->assertJsonMissingPath('enrollmentId');
    }

    public function test_revogar_registra_quem_e_por_que_e_nao_apaga_a_linha(): void
    {
        $c = $this->certificadoEmitido();
        $adminId = DB::table('User')->where('role', 'admin')->value('id');

        $this->deleteJson(
            "/api/certificates/{$c['cert']->id}",
            ['motivo' => 'Progresso corrompido por exclusão em cascata.'],
            $this->auth($this->staffToken('admin'))
        )->assertOk();

        // A linha permanece, íntegra, com autoria — o que a auditoria pergunta.
        $linha = DB::table('Certificate')->where('id', $c['cert']->id)->first();
        $this->assertNotNull($linha, 'O certificado foi APAGADO — a ADR 12 proíbe.');
        $this->assertNotNull($linha->inativadoEm);
        $this->assertSame($adminId, $linha->inativadoPor);
        $this->assertSame('Progresso corrompido por exclusão em cascata.', $linha->motivoInativacao);
    }

    public function test_certificado_revogado_sai_da_listagem_e_o_pdf_nao_baixa_mais(): void
    {
        $c = $this->certificadoEmitido();
        $admin = $this->auth($this->staffToken('admin'));

        $this->get("/api/certificates/{$c['cert']->id}/pdf", $admin)->assertOk();

        $this->deleteJson("/api/certificates/{$c['cert']->id}", ['motivo' => 'Emitido por engano.'], $admin)->assertOk();

        // Documento revogado não é documento entregável.
        $this->get("/api/certificates/{$c['cert']->id}/pdf", $admin)->assertNotFound();

        $ids = collect($this->getJson('/api/certificates?pageSize=200', $admin)->assertOk()->json('items'))
            ->pluck('id')->all();
        $this->assertNotContains($c['cert']->id, $ids);
    }

    public function test_reemitir_certificado_revogado_explica_em_vez_de_estourar_a_chave_unica(): void
    {
        /*
         * Armadilha 1 da ADR 12. A idempotência da emissão não via o revogado,
         * seguia para o `create()` e batia na UNIQUE (studentName, courseId) —
         * erro de servidor. E, pior, `reativar()` limpa a autoria: reemitir sem
         * mais nada apagaria o registro de que houve revogação.
         */
        $c = $this->certificadoEmitido();
        $admin = $this->auth($this->staffToken('admin'));

        // Frequência real de 100%, para que a recusa venha da revogação e não do critério.
        StudentProgress::query()->create([
            'id' => 'prog-revog-'.uniqid(),
            'userId' => $c['aluno']['id'],
            'studentName' => $c['aluno']['name'],
            'courseId' => $c['curso']->id,
            'completedLessons' => $c['curso']->lessons()->pluck('id')->all(),
            'attendedLiveSessions' => $c['curso']->liveSessions()->pluck('id')->all(),
        ]);

        $this->deleteJson("/api/certificates/{$c['cert']->id}", ['motivo' => 'Sob apuração.'], $admin)->assertOk();

        $resposta = $this->postJson(
            '/api/certificates',
            ['userId' => $c['aluno']['id'], 'courseId' => $c['curso']->id],
            $admin
        )->assertForbidden();
        $this->assertStringContainsString('revogado', (string) json_encode($resposta->json()));

        // E a revogação continua registrada, intacta.
        $linha = DB::table('Certificate')->where('id', $c['cert']->id)->first();
        $this->assertNotNull($linha);
        $this->assertSame('Sob apuração.', $linha->motivoInativacao);
    }

    public function test_a_busca_da_verificacao_e_um_grupo_e_nao_um_or_solto(): void
    {
        /*
         * Regressão do OR sem parênteses: as três condições de busca ficavam
         * soltas e o filtro do SoftDeletes, acrescentado ao fim, ligava-se por
         * AND apenas à última — então id e hash escapavam do escopo. Agora a
         * busca é um grupo, e o estado do documento vem no campo `revogado`.
         */
        $c = $this->certificadoEmitido();

        $this->getJson('/api/certificates/verify?q='.$c['cert']->id)
            ->assertOk()
            ->assertJsonPath('id', $c['cert']->id);

        $this->getJson('/api/certificates/verify?q=Nome Que Nao Existe No Banco')
            ->assertOk()
            ->assertJsonMissingPath('id');
    }
}
