<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\SolicitacaoIngresso;
use App\Models\Certificado;
use App\Models\Curso;
use App\Models\Aula;
use App\Models\Avaliacao;
use App\Models\TentativaAvaliacao;
use App\Models\Matricula;
use App\Models\ProgressoAluno;
use App\Models\Usuario;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * `/inst/curso/{ref}/alunos` — a turma e a fila de matrículas.
 *
 * A aba do painel React era vazia com a aprovação automática ligada. O que
 * carrega o peso aqui: que a turma mostre SÓ o que o banco sabe (sem a presença
 * e o e-mail inventados do painel antigo), que a fila exista só quando a regra a
 * pede, e que um pedido de outro curso não se decida pelo endereço deste.
 */
final class AlunosDoInstrutorTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    /** @return array{id:string,name:string,token:string} */
    private function instrutor(string $nome): array
    {
        $id = (string) Str::uuid();
        Usuario::query()->create([
            'id' => $id,
            'nome' => $nome,
            'email' => Str::lower(Str::random(10)).'@example.com',
            'senha_hash' => password_hash('senha123456', PASSWORD_BCRYPT),
            'papel' => 'instructor',
        ]);

        return ['id' => $id, 'nome' => $nome, 'token' => Jwt::issue($id, $nome, 'instructor')];
    }

    private function curso(string $instrutor_id, string $titulo = 'Curso Da Turma Teste'): Curso
    {
        $curso = Curso::query()->create([
            'id' => 'course-turma-'.uniqid(),
            'titulo' => $titulo,
            'descricao' => 'Descrição longa o bastante para o validador aceitar.',
            'categoria' => 'Teste',
            'miniatura' => 'sem-imagem.png',
            'instrutor_id' => $instrutor_id,
            'nome_instrutor' => 'Instrutor',
        ]);
        Curso::query()->whereKey($curso->id)->update([
            'slug' => Str::slug($titulo).'-'.Str::lower(Str::random(5)),
            'status' => Visibilidade::PUBLICADO,
        ]);

        return $curso->refresh();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{id:string,name:string,token:string}
     */
    private function aluno(string $nome, Curso $curso, array $extra = []): array
    {
        $aluno = $this->makeStudent($nome);
        Matricula::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'usuario_id' => $aluno['id'],
            'nome_aluno' => $aluno['nome'],
            'curso_matriculado_id' => $curso->id,
            'matriculado_em_texto' => now(),
            'cursos_concluidos_ids' => [],
            'penalidade_desistencia_ate' => null,
            'pode_multimatricula' => false,
            'cursos_extras_ids' => [],
        ], $extra));

        return $aluno;
    }

    public function test_visitante_sem_sessao_vai_para_o_login(): void
    {
        $this->get('/inst/curso/qualquer/alunos')->assertRedirect('/entrar');
    }

    public function test_mostra_a_turma_com_o_progresso_real(): void
    {
        $eu = $this->instrutor('Gestor Turma');
        $curso = $this->curso($eu['id']);
        $aulas = [];
        foreach ([1, 2, 3, 4] as $n) {
            $aulas[] = Aula::query()->create([
                'id' => "lesson-turma-{$curso->id}-{$n}", 'curso_id' => $curso->id,
                'titulo' => "Aula {$n}", 'duracao' => '10 min', 'conteudo' => 'c', 'ordem' => $n,
            ]);
        }
        $quiz = Avaliacao::query()->create(['id' => 'quiz-turma-'.uniqid(), 'curso_id' => $curso->id, 'titulo' => 'Prova da turma']);
        $aluno = $this->aluno('Ana Da Turma', $curso);

        ProgressoAluno::query()->create([
            'id' => 'prog-'.uniqid(), 'usuario_id' => $aluno['id'], 'nome_aluno' => $aluno['nome'], 'curso_id' => $curso->id,
            // 2 aulas reais e UM id órfão: o resíduo não é progresso.
            'aulas_concluidas' => [$aulas[0]->id, $aulas[1]->id, 'lesson-que-nao-existe-mais'],
            'sessoes_ao_vivo_assistidas' => [],
        ]);
        TentativaAvaliacao::query()->create([
            'id' => 'sub-turma-'.uniqid(), 'nome_aluno' => $aluno['nome'], 'usuario_id' => $aluno['id'],
            'curso_id' => $curso->id, 'avaliacao_id' => $quiz->id, 'percentual_nota' => 90, 'aprovado' => true,
            'enviado_em_texto' => '21/10/2026 às 10:00', 'enviado_em' => now(),
        ]);

        $resposta = $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/alunos');

        $resposta->assertOk();
        $resposta->assertDontSee('<div id="root">', false);
        $resposta->assertSee('Ana Da Turma', escape: false);
        $resposta->assertSee('2 de 4', escape: false);   // aulas — o órfão não conta
        $resposta->assertSee('1 de 1', escape: false);   // avaliações aprovadas
        $resposta->assertSee('Ainda não', escape: false); // certificado
        $resposta->assertSee('1 aluno cursando', escape: false);
    }

    /** Uma tentativa aprovada basta, como o certificado diz. */
    public function test_aprovado_uma_vez_continua_aprovado_mesmo_se_a_ultima_foi_pior(): void
    {
        $eu = $this->instrutor('Gestor Aprovado Uma Vez');
        $curso = $this->curso($eu['id']);
        $quiz = Avaliacao::query()->create(['id' => 'quiz-uma-vez-'.uniqid(), 'curso_id' => $curso->id, 'titulo' => 'Prova']);
        $aluno = $this->aluno('Aluno Aprovou E Piorou', $curso);
        foreach ([[100, true, 0], [0, false, 5]] as $n => [$nota, $passou, $seg]) {
            TentativaAvaliacao::query()->create([
                'id' => 'sub-uv-'.$n.'-'.uniqid(), 'nome_aluno' => $aluno['nome'], 'usuario_id' => $aluno['id'],
                'curso_id' => $curso->id, 'avaliacao_id' => $quiz->id, 'percentual_nota' => $nota, 'aprovado' => $passou,
                'enviado_em_texto' => '21/10/2026 às 10:0'.$n, 'enviado_em' => now()->addSeconds($seg),
            ]);
        }

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/alunos')
            ->assertSee('1 de 1', escape: false);
    }

    public function test_certificado_emitido_aparece(): void
    {
        $eu = $this->instrutor('Gestor Certificado');
        $curso = $this->curso($eu['id']);
        $aluno = $this->aluno('Aluno Certificado', $curso);
        Certificado::query()->create([
            'id' => 'cert-turma-'.uniqid(), 'nome_aluno' => $aluno['nome'], 'usuario_id' => $aluno['id'],
            'curso_id' => $curso->id, 'titulo_curso' => $curso->titulo, 'data_emissao' => now(),
            'percentual_frequencia' => 90, 'hash_verificacao' => 'AVA-TURMA-'.strtoupper(uniqid()),
        ]);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/alunos')
            ->assertSee('Emitido', escape: false);
    }

    /** A matrícula concorrente conta: foi o defeito que deixou a caixa de mensagens vazia. */
    public function test_aluno_por_matricula_concorrente_aparece(): void
    {
        $eu = $this->instrutor('Gestor Concorrente Turma');
        $curso = $this->curso($eu['id']);
        $this->aluno('Aluno Concorrente Da Turma', $curso, ['curso_matriculado_id' => null, 'cursos_extras_ids' => [$curso->id]]);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/alunos')
            ->assertSee('Aluno Concorrente Da Turma', escape: false);
    }

    /** Quem concluiu entra só na CONTAGEM: ex-aluno não fica no alcance para sempre. */
    public function test_quem_concluiu_entra_so_na_contagem_e_sem_nome(): void
    {
        $eu = $this->instrutor('Gestor Concluidos');
        $curso = $this->curso($eu['id']);
        $this->aluno('Aluno Que Ja Concluiu', $curso, ['curso_matriculado_id' => null, 'cursos_concluidos_ids' => [$curso->id]]);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/alunos')
            ->assertSee('1 já concluiu', escape: false)
            ->assertDontSee('Aluno Que Ja Concluiu', escape: false);
    }

    public function test_aluno_de_outro_curso_nao_aparece(): void
    {
        $eu = $this->instrutor('Gestor Turma Um');
        $meu = $this->curso($eu['id'], 'Curso Meu Da Turma');
        $outro = $this->instrutor('Gestor Turma Dois');
        $alheio = $this->curso($outro['id'], 'Curso Alheio Da Turma');
        $this->aluno('Aluno Do Curso Alheio', $alheio);

        $this->como($eu['token'])->get('/inst/curso/'.$meu->slug.'/alunos')
            ->assertDontSee('Aluno Do Curso Alheio', escape: false)
            ->assertSee('Ninguém está cursando este curso agora.', escape: false);
    }

    public function test_curso_de_outro_instrutor_responde_404(): void
    {
        $eu = $this->instrutor('Gestor Nega Um');
        $outro = $this->instrutor('Gestor Nega Dois');
        $alheio = $this->curso($outro['id'], 'Curso Negado Da Turma');

        $this->como($eu['token'])->get('/inst/curso/'.$alheio->slug.'/alunos')->assertNotFound();
        $this->como($eu['token'])->post('/inst/curso/'.$alheio->slug.'/pedidos/qualquer', ['decisao' => 'approved'])->assertNotFound();
    }

    /** O que o painel antigo inventava não volta. */
    public function test_nao_mostra_presenca_nem_selo_de_qualificado(): void
    {
        $eu = $this->instrutor('Gestor Sem Presenca Turma');
        $curso = $this->curso($eu['id']);
        $this->aluno('Aluno Sem Presenca Inventada', $curso);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/alunos')
            ->assertDontSee('Presença', escape: false)
            ->assertDontSee('Qualificado', escape: false)
            ->assertDontSee('@escola.dev.br', escape: false);
    }

    public function test_com_aprovacao_automatica_nao_ha_fila_e_a_tela_diz_por_que(): void
    {
        config(['features.aprovacaoAutomaticaMatricula' => true]);
        $eu = $this->instrutor('Gestor Automatica');
        $curso = $this->curso($eu['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/alunos')
            ->assertSee('aprovadas automaticamente', escape: false)
            ->assertDontSee('Pedidos de matrícula', escape: false);
    }

    public function test_sem_aprovacao_automatica_a_fila_lista_so_os_pedidos_deste_curso(): void
    {
        config(['features.aprovacaoAutomaticaMatricula' => false]);
        $eu = $this->instrutor('Gestor Fila');
        $curso = $this->curso($eu['id']);
        $outro = $this->curso($eu['id'], 'Curso Irmao Da Fila');
        $aluno = $this->makeStudent('Aluno Que Pediu');
        $outroAluno = $this->makeStudent('Aluno Que Pediu Outro Curso');
        foreach ([[$aluno, $curso], [$outroAluno, $outro]] as $i => [$a, $c]) {
            SolicitacaoIngresso::query()->create([
                'id' => 'adm-fila-'.$i.'-'.uniqid(), 'nome_aluno' => $a['nome'], 'usuario_id' => $a['id'],
                'curso_id' => $c->id, 'status' => 'pending', 'enviado_em_texto' => '21/10/2026',
            ]);
        }

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/alunos')
            ->assertSee('Pedidos de matrícula', escape: false)
            ->assertSee('Aluno Que Pediu', escape: false)
            ->assertDontSee('Aluno Que Pediu Outro Curso', escape: false);
    }

    public function test_aprovar_um_pedido_matricula_o_aluno_e_audita(): void
    {
        config(['features.aprovacaoAutomaticaMatricula' => false]);
        $eu = $this->instrutor('Gestor Aprova');
        $curso = $this->curso($eu['id']);
        $aluno = $this->makeStudent('Aluno A Aprovar');
        $pedido = SolicitacaoIngresso::query()->create([
            'id' => 'adm-aprova-'.uniqid(), 'nome_aluno' => $aluno['nome'], 'usuario_id' => $aluno['id'],
            'curso_id' => $curso->id, 'status' => 'pending', 'enviado_em_texto' => '21/10/2026',
        ]);
        $antes = (int) DB::table('log_seguranca')->count();

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/pedidos/'.$pedido->id, ['decisao' => 'approved'])
            ->assertRedirect('/inst/curso/'.$curso->slug.'/alunos');

        $this->assertSame('approved', SolicitacaoIngresso::query()->whereKey($pedido->id)->value('status'));
        $this->assertSame($curso->id, Matricula::query()->whereKey($aluno['id'])->value('curso_matriculado_id'));
        $this->assertGreaterThan($antes, (int) DB::table('log_seguranca')->count());
    }

    public function test_reprovar_nao_matricula(): void
    {
        config(['features.aprovacaoAutomaticaMatricula' => false]);
        $eu = $this->instrutor('Gestor Reprova');
        $curso = $this->curso($eu['id']);
        $aluno = $this->makeStudent('Aluno A Reprovar');
        $pedido = SolicitacaoIngresso::query()->create([
            'id' => 'adm-reprova-'.uniqid(), 'nome_aluno' => $aluno['nome'], 'usuario_id' => $aluno['id'],
            'curso_id' => $curso->id, 'status' => 'pending', 'enviado_em_texto' => '21/10/2026',
        ]);

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/pedidos/'.$pedido->id, ['decisao' => 'rejected']);

        $this->assertSame('rejected', SolicitacaoIngresso::query()->whereKey($pedido->id)->value('status'));
        $this->assertNull(Matricula::query()->whereKey($aluno['id'])->value('curso_matriculado_id'));
    }

    /** O pedido de OUTRO curso não se decide pelo endereço deste. */
    public function test_pedido_de_outro_curso_nao_se_decide_por_este_endereco(): void
    {
        config(['features.aprovacaoAutomaticaMatricula' => false]);
        $eu = $this->instrutor('Gestor Pedido Alheio');
        $meu = $this->curso($eu['id'], 'Curso Meu Pedido');
        $outro = $this->curso($eu['id'], 'Curso Irmao Pedido');
        $aluno = $this->makeStudent('Aluno Do Pedido Irmao');
        $pedido = SolicitacaoIngresso::query()->create([
            'id' => 'adm-irmao-'.uniqid(), 'nome_aluno' => $aluno['nome'], 'usuario_id' => $aluno['id'],
            'curso_id' => $outro->id, 'status' => 'pending', 'enviado_em_texto' => '21/10/2026',
        ]);

        $this->como($eu['token'])
            ->post('/inst/curso/'.$meu->slug.'/pedidos/'.$pedido->id, ['decisao' => 'approved'])
            ->assertNotFound();

        $this->assertSame('pending', SolicitacaoIngresso::query()->whereKey($pedido->id)->value('status'));
    }

    /** Decidir um pedido já decidido não é possível: ele sai da fila. */
    public function test_pedido_ja_decidido_nao_se_decide_de_novo(): void
    {
        config(['features.aprovacaoAutomaticaMatricula' => false]);
        $eu = $this->instrutor('Gestor Ja Decidido');
        $curso = $this->curso($eu['id']);
        $aluno = $this->makeStudent('Aluno Ja Decidido');
        $pedido = SolicitacaoIngresso::query()->create([
            'id' => 'adm-decidido-'.uniqid(), 'nome_aluno' => $aluno['nome'], 'usuario_id' => $aluno['id'],
            'curso_id' => $curso->id, 'status' => 'rejected', 'enviado_em_texto' => '21/10/2026',
        ]);

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/pedidos/'.$pedido->id, ['decisao' => 'approved'])
            ->assertNotFound();

        $this->assertSame('rejected', SolicitacaoIngresso::query()->whereKey($pedido->id)->value('status'));
    }

    public function test_decisao_invalida_e_recusada(): void
    {
        config(['features.aprovacaoAutomaticaMatricula' => false]);
        $eu = $this->instrutor('Gestor Decisao Invalida');
        $curso = $this->curso($eu['id']);

        $this->como($eu['token'])
            ->from('/inst/curso/'.$curso->slug.'/alunos')
            ->post('/inst/curso/'.$curso->slug.'/pedidos/x', ['decisao' => 'talvez'])
            ->assertSessionHasErrors('decisao');
    }

    public function test_sem_dados_gerenciais_a_tela_responde_404(): void
    {
        config(['features.dadosGerenciais' => false]);
        $eu = $this->instrutor('Gestor Sem Dados');
        $curso = $this->curso($eu['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/alunos')->assertNotFound();
    }
}
