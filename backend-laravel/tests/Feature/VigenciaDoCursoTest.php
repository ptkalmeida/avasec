<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\SolicitacaoIngresso;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\Usuario;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Curso com a vigência de exibição vencida não é oferecido a aluno.
 *
 * A regra estava escrita no painel do instrutor — "bloqueado no catálogo de
 * alunos para proteção jurídica contra distribuição ilegal de direitos
 * autorais expirados" — e só existia no NAVEGADOR: o React filtrava a lista, e
 * nem o `listCoursesFor` nem o `selfEnroll` olhavam a data. Quando o catálogo
 * passou para Blade (M3), o filtro saiu junto com a tela antiga.
 *
 * Quem já está matriculado NÃO é afetado: tirar alguém do meio de um curso tem
 * consequência acadêmica, e é decisão do responsável (plano 18 §24).
 */
final class VigenciaDoCursoTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    private function curso(string $titulo, ?string $vigencia_ate): Curso
    {
        $curso = Curso::query()->create([
            'id' => 'course-vig-'.uniqid(),
            'titulo' => $titulo,
            'descricao' => 'Descrição do curso de teste de vigência.',
            'categoria' => 'Teste',
            'miniatura' => 'sem-imagem.png',
            'nome_instrutor' => 'Instrutor',
        ]);
        Curso::query()->whereKey($curso->id)->update([
            'slug' => Str::slug($titulo).'-'.Str::lower(Str::random(5)),
            'status' => Visibilidade::PUBLICADO,
            'vigencia_ate' => $vigencia_ate,
            'vencimento_contrato_texto' => $vigencia_ate,
        ]);

        return $curso->refresh();
    }

    public function test_curso_vencido_nao_aparece_no_catalogo_do_aluno(): void
    {
        $aluno = $this->makeStudent('Aluno Catalogo Vigencia');
        $this->curso('Curso Vencido De Teste', now()->subDay()->format('Y-m-d'));
        $this->curso('Curso No Prazo De Teste', now()->addYear()->format('Y-m-d'));

        $this->como($aluno['token'])->get('/aluno/catalogo')
            ->assertSee('Curso No Prazo De Teste', escape: false)
            ->assertDontSee('Curso Vencido De Teste', escape: false);
    }

    /** O dia do vencimento vale inteiro. */
    public function test_curso_que_vence_hoje_ainda_e_oferecido(): void
    {
        $aluno = $this->makeStudent('Aluno Vence Hoje');
        $this->curso('Curso Que Vence Hoje Teste', now()->format('Y-m-d'));

        $this->como($aluno['token'])->get('/aluno/catalogo')
            ->assertSee('Curso Que Vence Hoje Teste', escape: false);
    }

    public function test_curso_sem_prazo_e_oferecido(): void
    {
        $aluno = $this->makeStudent('Aluno Sem Prazo');
        $this->curso('Curso Permanente De Teste', null);

        $this->como($aluno['token'])->get('/aluno/catalogo')
            ->assertSee('Curso Permanente De Teste', escape: false);
    }

    public function test_a_ficha_do_curso_vencido_responde_404(): void
    {
        $aluno = $this->makeStudent('Aluno Ficha Vencida');
        $curso = $this->curso('Curso Vencido Ficha', now()->subMonth()->format('Y-m-d'));

        $this->como($aluno['token'])->get('/aluno/catalogo/'.$curso->slug)->assertNotFound();
    }

    /** A porta da tela. */
    public function test_matricula_pela_tela_em_curso_vencido_e_recusada(): void
    {
        $aluno = $this->makeStudent('Aluno Matricula Vencida');
        $curso = $this->curso('Curso Vencido Matricula', now()->subDay()->format('Y-m-d'));

        $this->como($aluno['token'])
            ->from('/aluno/catalogo')
            ->post('/aluno/catalogo/'.$curso->slug.'/matricula', ['ciente' => '1']);

        $this->assertFalse(
            Matricula::query()->whereKey($aluno['id'])->where('curso_matriculado_id', $curso->id)->exists(),
            'O aluno foi matriculado num curso com a vigência vencida.',
        );
    }

    /** E a porta da API, que é por onde o bloqueio faltava de verdade. */
    public function test_matricula_pela_api_em_curso_vencido_e_recusada(): void
    {
        $aluno = $this->makeStudent('Aluno API Vencida');
        $curso = $this->curso('Curso Vencido API', now()->subDay()->format('Y-m-d'));

        $this->withHeader('Authorization', 'Bearer '.$aluno['token'])
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $curso->id])
            ->assertStatus(409);

        $this->assertFalse(
            Matricula::query()->whereKey($aluno['id'])->where('curso_matriculado_id', $curso->id)->exists(),
        );
    }

    /**
     * A terceira porta: `POST /api/ingressos`. Com a aprovação automática
     * ligada (que é a configuração de hoje) este pedido MATRICULA na hora, e o
     * bloqueio que primeiro cobriu só o `selfEnroll` não o alcançava.
     */
    public function test_pedido_de_matricula_pela_api_em_curso_vencido_e_recusado(): void
    {
        config(['features.aprovacaoAutomaticaMatricula' => true]);
        $aluno = $this->makeStudent('Aluno Pedido Vencido');
        $curso = $this->curso('Curso Vencido Pedido', now()->subDay()->format('Y-m-d'));

        $this->withHeader('Authorization', 'Bearer '.$aluno['token'])
            ->postJson('/api/ingressos', ['curso_id' => $curso->id])
            ->assertStatus(409);

        $this->assertFalse(
            Matricula::query()->whereKey($aluno['id'])->where('curso_matriculado_id', $curso->id)->exists(),
            'O pedido matriculou o aluno num curso com a vigência vencida.',
        );
        $this->assertFalse(SolicitacaoIngresso::query()->where('usuario_id', $aluno['id'])->where('curso_id', $curso->id)->exists());
    }

    /** A quarta: o gestor aprovando um pedido pendente de um curso que venceu depois. */
    public function test_aprovar_pedido_pendente_de_curso_que_venceu_e_recusado(): void
    {
        config(['features.aprovacaoAutomaticaMatricula' => false]);
        $aluno = $this->makeStudent('Aluno Pendente Venceu');
        $curso = $this->curso('Curso Que Venceu Depois', now()->addDay()->format('Y-m-d'));

        $pedido = $this->withHeader('Authorization', 'Bearer '.$aluno['token'])
            ->postJson('/api/ingressos', ['curso_id' => $curso->id])
            ->assertSuccessful()->json();
        $this->assertSame('pending', $pedido['status']);

        // O curso vence ENQUANTO o pedido espera.
        Curso::query()->whereKey($curso->id)->update([
            'vigencia_ate' => now()->subDay()->format('Y-m-d'),
            'vencimento_contrato_texto' => now()->subDay()->format('Y-m-d'),
        ]);

        $admin = Usuario::query()->where('papel', 'admin')->first();
        $this->assertNotNull($admin);
        $tokenAdmin = Jwt::issue((string) $admin->id, (string) $admin->nome, 'admin');

        $this->withHeader('Authorization', 'Bearer '.$tokenAdmin)
            ->putJson('/api/ingressos/'.$pedido['id'], ['status' => 'approved'])
            ->assertStatus(409);

        $this->assertFalse(
            Matricula::query()->whereKey($aluno['id'])->where('curso_matriculado_id', $curso->id)->exists(),
        );

        // Reprovar continua possível: o gestor pode encerrar o pedido.
        $this->withHeader('Authorization', 'Bearer '.$tokenAdmin)
            ->putJson('/api/ingressos/'.$pedido['id'], ['status' => 'rejected'])
            ->assertSuccessful();
    }

    /**
     * Quem já está matriculado continua com o curso. Tirar alguém do meio do
     * curso é decisão do responsável, não efeito colateral deste bloqueio.
     */
    public function test_quem_ja_esta_matriculado_nao_perde_o_curso(): void
    {
        $aluno = $this->makeStudent('Aluno Ja Matriculado');
        $curso = $this->curso('Curso Vencido Com Aluno', now()->subDay()->format('Y-m-d'));

        Matricula::query()->create([
            'id' => (string) Str::uuid(),
            'usuario_id' => $aluno['id'],
            'nome_aluno' => $aluno['nome'],
            'curso_matriculado_id' => $curso->id,
            'matriculado_em_texto' => now(),
            'cursos_concluidos_ids' => [],
            'penalidade_desistencia_ate' => null,
            'pode_multimatricula' => false,
            'cursos_extras_ids' => [],
        ]);

        $this->como($aluno['token'])->get('/aluno/curso/'.$curso->slug)
            ->assertOk()
            ->assertSee('Curso Vencido Com Aluno', escape: false);
    }
}
