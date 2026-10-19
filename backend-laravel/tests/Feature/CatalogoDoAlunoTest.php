<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Course;
use App\Models\StudentEnrollment;
use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Catálogo do aluno e matrícula, em Blade (plano 18, fase M3).
 *
 * Esta tela existe porque a skill removeu "Disciplinas acadêmicas disponíveis"
 * do painel, e a matrícula morava lá. A decisão de 19/10 foi tirar do lugar sem
 * tirar a função — então o que este arquivo protege, antes de qualquer coisa, é
 * que **a função continua existindo e continua obedecendo às mesmas regras**.
 *
 * `DatabaseTransactions`: as matrículas feitas aqui são desfeitas ao fim de
 * cada teste. Nada é apagado (ADR 12) — a transação é revertida.
 */
final class CatalogoDoAlunoTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    /** @return array{id:string,name:string,cpf:string,token:string} */
    private function alunoNovo(): array
    {
        return $this->makeStudent('Aluno Catalogo');
    }

    private function como(string $id, string $nome): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, Jwt::issue($id, $nome, 'student'));
    }

    public function test_o_catalogo_abre_e_lista_cursos_para_matricula(): void
    {
        $aluno = $this->alunoNovo();

        $resposta = $this->como($aluno['id'], $aluno['name'])->get('/aluno/catalogo');

        $resposta->assertOk();
        $resposta->assertSee('Catálogo', escape: false);
        $resposta->assertSee('Ver e escolher', escape: false);
    }

    public function test_matricula_efetiva_e_leva_ao_curso(): void
    {
        $aluno = $this->alunoNovo();
        $curso = Course::query()->where('status', 1)->firstOrFail();

        $resposta = $this->como($aluno['id'], $aluno['name'])
            ->post('/aluno/catalogo/'.$curso->id.'/matricula', ['ciente' => '1']);

        $resposta->assertRedirect('/aluno/curso/'.($curso->slug !== null && $curso->slug !== '' ? $curso->slug : $curso->id));

        $matricula = StudentEnrollment::query()->where('userId', $aluno['id'])->first();
        $this->assertNotNull($matricula, 'A matrícula não foi gravada.');
        $this->assertSame($curso->id, $matricula->enrolledCourseId);
    }

    /**
     * O Termo de Ciência é validado NO SERVIDOR. No React ele só desabilitava o
     * botão — e botão desabilitado não é regra, é sugestão: basta enviar o
     * formulário por fora.
     */
    public function test_sem_o_termo_de_ciencia_nao_matricula(): void
    {
        $aluno = $this->alunoNovo();
        $curso = Course::query()->where('status', 1)->firstOrFail();

        $resposta = $this->como($aluno['id'], $aluno['name'])
            ->from('/aluno/catalogo/'.$curso->id)
            ->post('/aluno/catalogo/'.$curso->id.'/matricula', []);

        $resposta->assertSessionHasErrors('ciente');
        $this->assertNull(
            StudentEnrollment::query()->where('userId', $aluno['id'])->value('enrolledCourseId'),
            'Matriculou sem o termo aceito.',
        );
    }

    public function test_visitante_sem_sessao_nao_matricula_ninguem(): void
    {
        $curso = Course::query()->where('status', 1)->firstOrFail();

        $this->post('/aluno/catalogo/'.$curso->id.'/matricula', ['ciente' => '1'])
            ->assertRedirect('/entrar');
    }

    /**
     * Curso que o aluno já tem não é oferta: a ficha dele no catálogo manda
     * para a tela do curso, que é o que a pessoa queria ao clicar.
     */
    public function test_curso_que_o_aluno_ja_tem_nao_aparece_como_oferta(): void
    {
        $aluno = $this->alunoNovo();
        $curso = Course::query()->where('status', 1)->firstOrFail();

        $this->como($aluno['id'], $aluno['name'])
            ->post('/aluno/catalogo/'.$curso->id.'/matricula', ['ciente' => '1']);

        // Some da lista...
        $lista = $this->como($aluno['id'], $aluno['name'])->get('/aluno/catalogo');
        $lista->assertDontSee('/aluno/catalogo/'.$curso->id.'"', escape: false);

        // ...e a ficha dele leva ao curso.
        $destino = $curso->slug !== null && $curso->slug !== '' ? $curso->slug : $curso->id;
        $this->como($aluno['id'], $aluno['name'])
            ->get('/aluno/catalogo/'.$curso->id)
            ->assertRedirect('/aluno/curso/'.$destino);
    }

    /**
     * Quem já tem curso em andamento é avisado ANTES de escolher, e não depois
     * de tentar — a recusa do servidor chegaria com a expectativa já criada.
     */
    public function test_quem_ja_tem_curso_ativo_e_avisado_antes_de_escolher(): void
    {
        $aluno = $this->alunoNovo();
        $curso = Course::query()->where('status', 1)->firstOrFail();

        $this->como($aluno['id'], $aluno['name'])
            ->post('/aluno/catalogo/'.$curso->id.'/matricula', ['ciente' => '1']);

        $this->como($aluno['id'], $aluno['name'])
            ->get('/aluno/catalogo')
            ->assertSee('Você já tem um curso em andamento', escape: false);
    }

    public function test_curso_inexistente_no_catalogo_responde_404(): void
    {
        $aluno = $this->alunoNovo();

        $this->como($aluno['id'], $aluno['name'])->get('/aluno/catalogo/nao-existe')->assertNotFound();
    }
}
