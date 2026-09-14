<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdmissionRequest;
use App\Models\StudentEnrollment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Aprovação automática de matrícula (coordenação, 14/09/2026).
 *
 * Por que este arquivo existe: havia DOIS caminhos para matricular e só um
 * tinha regra. `selfEnroll` validava cinco condições; `updateAdmissionStatus`
 * — o botão "Aprovar Acesso" — não validava nenhuma, e sobrescrevia
 * `enrolledCourseId` direto.
 *
 * Enquanto uma pessoa lia cada pedido, a assimetria passava. Automatizar a
 * aprovação sem unificar as travas transformaria isso em dano silencioso: o
 * aluno pede um curso novo e o sistema o tira, sem aviso e sem ninguém
 * olhando, do curso em que ele já tem progresso e frequência.
 *
 * Os testes ligam a flag explicitamente em vez de herdar o valor do config:
 * um teste que passa só porque a flag está ligada hoje não diz nada sobre o
 * dia em que alguém a desligar.
 */
final class AprovacaoAutomaticaMatriculaTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    public function test_com_a_flag_ligada_a_solicitacao_ja_nasce_aprovada_e_matricula(): void
    {
        config(['features.aprovacaoAutomaticaMatricula' => true]);

        $aluno = $this->makeStudent('Aluno Aprovacao Automatica');
        $courseId = $this->anySeededCourseId();

        $this->withHeader('Authorization', "Bearer {$aluno['token']}")
            ->postJson('/api/admissions', ['courseId' => $courseId])
            ->assertStatus(201)
            ->assertJsonPath('status', 'approved');

        // "Aprovada" sem matricular é o estado que não pode existir: se a
        // solicitação diz aprovada e a matrícula não saiu, o aluno vê acesso
        // liberado na tela e não tem registro nenhum por trás.
        $matricula = StudentEnrollment::query()->whereKey($aluno['id'])->first();
        $this->assertNotNull($matricula, 'Aprovou e não matriculou.');
        $this->assertSame($courseId, $matricula->enrolledCourseId);
    }

    public function test_com_a_flag_desligada_a_solicitacao_continua_pendente_e_nao_matricula(): void
    {
        config(['features.aprovacaoAutomaticaMatricula' => false]);

        $aluno = $this->makeStudent('Aluno Aprovacao Manual');
        $courseId = $this->anySeededCourseId();

        $this->withHeader('Authorization', "Bearer {$aluno['token']}")
            ->postJson('/api/admissions', ['courseId' => $courseId])
            ->assertStatus(201)
            ->assertJsonPath('status', 'pending');

        $this->assertNull(
            StudentEnrollment::query()->whereKey($aluno['id'])->value('enrolledCourseId'),
            'Pedido pendente não pode matricular — a fila do professor existe justamente para decidir isso.'
        );
    }

    public function test_automatica_nao_tira_o_aluno_do_curso_em_que_ele_ja_esta(): void
    {
        /*
         * O caso concreto que motivou a trava: um aluno matriculado em
         * Full-Stack pediu Metodologias Ágeis. Sem esta regra, a aprovação
         * automática trocaria o curso ativo dele em silêncio, deixando para
         * trás o progresso e a frequência do primeiro.
         */
        config([
            'features.aprovacaoAutomaticaMatricula' => true,
            'features.matriculasMultiplas' => false,
        ]);

        $aluno = $this->makeStudent('Aluno Com Curso Ativo');
        $primeiro = $this->anySeededCourseId();
        $segundo = $this->anotherSeededCourseId($primeiro);

        $this->withHeader('Authorization', "Bearer {$aluno['token']}")
            ->postJson('/api/enrollments/self/enroll', ['courseId' => $primeiro])
            ->assertOk();
        $this->flushHeaders();

        $this->withHeader('Authorization', "Bearer {$aluno['token']}")
            ->postJson('/api/admissions', ['courseId' => $segundo])
            ->assertStatus(409);
        $this->flushHeaders();

        $this->assertSame(
            $primeiro,
            StudentEnrollment::query()->whereKey($aluno['id'])->value('enrolledCourseId'),
            'A recusa precisa deixar a matrícula anterior INTACTA, não apenas devolver erro.'
        );

        // E a recusa não pode deixar pedido pendente para trás: a fila de
        // aprovação deixou de ser exibida, então quem entrasse nela ficaria
        // preso para sempre.
        $this->assertSame(
            0,
            AdmissionRequest::query()->where('userId', $aluno['id'])->count(),
            'Recusa não pode criar solicitação — ela ficaria pendente sem ninguém para resolver.'
        );
    }

    public function test_automatica_respeita_a_regra_de_curso_ja_concluido(): void
    {
        /*
         * Curso concluído não aceita nova matrícula — o acesso continua pelo
         * modo revisão, que não depende de matrícula ativa. A automação não
         * pode ser a porta que reabre a matrícula que a regra fechou.
         */
        config(['features.aprovacaoAutomaticaMatricula' => true]);

        $aluno = $this->makeStudent('Aluno Curso Concluido');
        $courseId = $this->anySeededCourseId();

        StudentEnrollment::query()->create([
            'id' => (string) Str::uuid(),
            'userId' => $aluno['id'],
            'studentName' => $aluno['name'],
            'enrolledCourseId' => null,
            'enrolledAt' => null,
            'completedCourseIds' => [$courseId],
            'dropOutPenaltyUntil' => null,
            'canMultiEnroll' => false,
            'extraCourseIds' => [],
        ]);

        $this->withHeader('Authorization', "Bearer {$aluno['token']}")
            ->postJson('/api/admissions', ['courseId' => $courseId])
            ->assertStatus(409);
    }
}
