<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Curso;
use App\Models\Avaliacao;
use App\Models\QuestaoAvaliacao;
use App\Models\TentativaAvaliacao;
use App\Models\Matricula;
use App\Models\Usuario;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * `/inst/curso/{ref}/avaliacoes` — as provas do curso, em Blade.
 *
 * O que carrega o peso: que o gabarito impossível não seja gravado, que o curso
 * venha do endereço e nunca do formulário, que retirar não apague as respostas
 * dos alunos (ADR 12) e que um instrutor não alcance prova de outro.
 */
final class AvaliacoesDoInstrutorTest extends TestCase
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

    private function curso(string $instrutor_id, string $titulo = 'Curso Das Provas Teste'): Curso
    {
        $curso = Curso::query()->create([
            'id' => 'course-prova-'.uniqid(),
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

    private function prova(Curso $curso, string $titulo = 'Prova De Teste', int $questoes = 2): Avaliacao
    {
        $quiz = Avaliacao::query()->create(['id' => 'quiz-adm-'.uniqid(), 'curso_id' => $curso->id, 'titulo' => $titulo]);
        for ($i = 1; $i <= $questoes; $i++) {
            QuestaoAvaliacao::query()->create([
                'id' => $quiz->id.'-q'.$i,
                'avaliacao_id' => $quiz->id,
                'texto_questao' => "Pergunta numero {$i}?",
                'opcoes' => ['Alfa', 'Beta', 'Gama'],
                'indice_opcao_correta' => 1,
                'explicacao' => "Explicacao {$i}",
                'mensagem_revisao' => "Dica {$i}",
                'modulo_recomendado' => "Modulo {$i}",
                'permite_nova_tentativa' => false,
            ]);
        }

        return $quiz;
    }

    /** @return array<string, mixed> */
    private function corpoValido(string $titulo = 'Avaliacao Nova', array $sobrescreve = []): array
    {
        return array_merge([
            'titulo' => $titulo,
            'questoes' => [[
                'id' => '',
                'enunciado' => 'Qual e a capital do Brasil?',
                'alternativas' => ['Rio', 'Brasilia', 'Sao Paulo', ''],
                'correta' => 1,
                'explicacao' => 'Brasilia e a capital desde 1960.',
                'revisao' => 'Releia a aula sobre geografia.',
                'modulo' => 'Modulo 1',
            ]],
        ], $sobrescreve);
    }

    public function test_visitante_sem_sessao_vai_para_o_login(): void
    {
        $this->get('/inst/curso/qualquer/avaliacoes')->assertRedirect('/entrar');
    }

    public function test_a_lista_conta_alunos_e_nao_tentativas(): void
    {
        $eu = $this->instrutor('Gestor Conta Alunos');
        $curso = $this->curso($eu['id']);
        $quiz = $this->prova($curso);

        $a = $this->makeStudent('Aluno Que Tentou Tres Vezes');
        $b = $this->makeStudent('Aluno Que Reprovou');
        foreach ([[$a, true], [$a, true], [$a, false], [$b, false]] as $n => [$aluno, $passou]) {
            TentativaAvaliacao::query()->create([
                'id' => 'sub-adm-'.$n.'-'.uniqid(),
                'nome_aluno' => $aluno['nome'],
                'usuario_id' => $aluno['id'],
                'curso_id' => $curso->id,
                'avaliacao_id' => $quiz->id,
                'percentual_nota' => $passou ? 100 : 0,
                'aprovado' => $passou,
                'enviado_em_texto' => '21/10/2026 às 10:0'.$n,
                'enviado_em' => now()->addSeconds($n),
            ]);
        }

        $html = (string) $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/avaliacoes')->assertOk()->getContent();

        $this->assertStringContainsString('2 alunos responderam', $html);
        // `a` passou duas vezes e falhou uma: UM aprovado, e não dois.
        $this->assertStringContainsString('1 aprovado', $html);
        $this->assertStringNotContainsString('2 aprovados', $html);
        $this->assertStringNotContainsString('<div id="root">', $html);
    }

    public function test_curso_de_outro_instrutor_responde_404_em_tudo(): void
    {
        $eu = $this->instrutor('Gestor Um Provas');
        $outro = $this->instrutor('Gestor Dois Provas');
        $alheio = $this->curso($outro['id'], 'Curso Alheio Das Provas');
        $quiz = $this->prova($alheio);
        $base = '/inst/curso/'.$alheio->slug.'/avaliacoes';

        $this->como($eu['token'])->get($base)->assertNotFound();
        $this->como($eu['token'])->get($base.'/nova')->assertNotFound();
        $this->como($eu['token'])->get($base.'/'.$quiz->id.'/editar')->assertNotFound();
        $this->como($eu['token'])->post($base, $this->corpoValido())->assertNotFound();
        $this->como($eu['token'])->post($base.'/'.$quiz->id, $this->corpoValido())->assertNotFound();
        $this->como($eu['token'])->post($base.'/'.$quiz->id.'/remover')->assertNotFound();

        $this->assertSame('Prova De Teste', Avaliacao::query()->whereKey($quiz->id)->value('titulo'));
    }

    public function test_criar_grava_a_prova_no_curso_do_endereco_e_audita(): void
    {
        $eu = $this->instrutor('Gestor Cria Prova');
        $curso = $this->curso($eu['id']);
        $antes = (int) DB::table('log_seguranca')->count();

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/avaliacoes', $this->corpoValido('Minha Prova Nova'))
            ->assertRedirect('/inst/curso/'.$curso->slug.'/avaliacoes');

        $quiz = Avaliacao::query()->where('curso_id', $curso->id)->where('titulo', 'Minha Prova Nova')->first();
        $this->assertNotNull($quiz, 'A prova não foi gravada.');

        $questao = QuestaoAvaliacao::query()->where('avaliacao_id', $quiz->id)->first();
        $this->assertNotNull($questao);
        // A alternativa vazia do FIM foi descartada; o gabarito segue na 2ª.
        $this->assertSame(['Rio', 'Brasilia', 'Sao Paulo'], $questao->opcoes);
        $this->assertSame(1, $questao->indice_opcao_correta);
        $this->assertSame('Brasilia e a capital desde 1960.', $questao->explicacao);

        $this->assertGreaterThan($antes, (int) DB::table('log_seguranca')->count());
        $this->assertTrue(DB::table('log_seguranca')->where('acao', 'Criação de Avaliação')->exists());
    }

    /** O formulário não tem campo de curso; e se alguém mandar um, ele é ignorado. */
    public function test_o_curso_vem_do_endereco_e_nunca_do_formulario(): void
    {
        $eu = $this->instrutor('Gestor Curso Do Endereco');
        $meu = $this->curso($eu['id'], 'Curso Meu Das Provas');
        $outro = $this->instrutor('Gestor Outro Do Endereco');
        $alheio = $this->curso($outro['id'], 'Curso Alheio Do Endereco');

        $this->como($eu['token'])
            ->post('/inst/curso/'.$meu->slug.'/avaliacoes', $this->corpoValido('Tentativa De Plantar') + ['curso_id' => $alheio->id]);

        $this->assertFalse(Avaliacao::query()->where('curso_id', $alheio->id)->exists(), 'A prova foi plantada no curso alheio.');
        $this->assertTrue(Avaliacao::query()->where('curso_id', $meu->id)->where('titulo', 'Tentativa De Plantar')->exists());
    }

    /** O defeito que a API tinha: gabarito apontando para alternativa que não existe. */
    public function test_gabarito_que_nao_existe_e_recusado_e_nada_e_gravado(): void
    {
        $eu = $this->instrutor('Gestor Gabarito Falso');
        $curso = $this->curso($eu['id']);
        $corpo = $this->corpoValido('Prova Impossivel');
        $corpo['questoes'][0]['correta'] = 7;

        $this->como($eu['token'])
            ->from('/inst/curso/'.$curso->slug.'/avaliacoes/nova')
            ->post('/inst/curso/'.$curso->slug.'/avaliacoes', $corpo)
            ->assertSessionHasErrors('questoes.0');

        $this->assertFalse(Avaliacao::query()->where('curso_id', $curso->id)->exists());
    }

    public function test_alternativa_em_branco_no_meio_e_recusada(): void
    {
        $eu = $this->instrutor('Gestor Branco No Meio');
        $curso = $this->curso($eu['id']);
        $corpo = $this->corpoValido();
        $corpo['questoes'][0]['alternativas'] = ['Rio', '', 'Brasilia'];
        $corpo['questoes'][0]['correta'] = 2;

        $this->como($eu['token'])
            ->from('/inst/curso/'.$curso->slug.'/avaliacoes/nova')
            ->post('/inst/curso/'.$curso->slug.'/avaliacoes', $corpo)
            ->assertSessionHasErrors('questoes.0');
    }

    public function test_alternativas_repetidas_sao_recusadas(): void
    {
        $eu = $this->instrutor('Gestor Repetidas Form');
        $curso = $this->curso($eu['id']);
        $corpo = $this->corpoValido();
        $corpo['questoes'][0]['alternativas'] = ['Igual', 'igual', 'Outra'];

        $this->como($eu['token'])
            ->from('/inst/curso/'.$curso->slug.'/avaliacoes/nova')
            ->post('/inst/curso/'.$curso->slug.'/avaliacoes', $corpo)
            ->assertSessionHasErrors('questoes.0');
    }

    public function test_titulo_vazio_e_sem_questao_sao_recusados(): void
    {
        $eu = $this->instrutor('Gestor Sem Titulo');
        $curso = $this->curso($eu['id']);

        $this->como($eu['token'])
            ->from('/inst/curso/'.$curso->slug.'/avaliacoes/nova')
            ->post('/inst/curso/'.$curso->slug.'/avaliacoes', ['titulo' => '', 'questoes' => []])
            ->assertSessionHasErrors(['titulo', 'questoes']);
    }

    /** A mensagem diz QUAL questão tem o problema. */
    public function test_o_erro_diz_qual_questao_tem_o_problema(): void
    {
        $eu = $this->instrutor('Gestor Qual Questao');
        $curso = $this->curso($eu['id']);
        $corpo = $this->corpoValido();
        $corpo['questoes'][] = ['id' => '', 'enunciado' => '', 'alternativas' => ['a', 'b'], 'correta' => 0];

        $this->followingRedirects()
            ->como($eu['token'])
            ->from('/inst/curso/'.$curso->slug.'/avaliacoes/nova')
            ->post('/inst/curso/'.$curso->slug.'/avaliacoes', $corpo)
            ->assertSee('Questão 2: Escreva o enunciado da questão.', escape: false);
    }

    public function test_editar_mantem_o_id_das_questoes_e_preserva_a_regra_de_novas_tentativas(): void
    {
        $eu = $this->instrutor('Gestor Edita Prova');
        $curso = $this->curso($eu['id']);
        $quiz = $this->prova($curso, 'Prova A Editar', 2);
        $idQ1 = $quiz->id.'-q1';

        $this->como($eu['token'])->post('/inst/curso/'.$curso->slug.'/avaliacoes/'.$quiz->id, [
            'titulo' => 'Prova Editada',
            'questoes' => [[
                'id' => $idQ1,
                'enunciado' => 'Enunciado reescrito?',
                'alternativas' => ['Alfa', 'Beta', 'Gama'],
                'correta' => 2,
            ]],
        ])->assertRedirect('/inst/curso/'.$curso->slug.'/avaliacoes');

        $this->assertSame('Prova Editada', Avaliacao::query()->whereKey($quiz->id)->value('titulo'));

        $q1 = QuestaoAvaliacao::query()->find($idQ1);
        $this->assertNotNull($q1, 'A questão perdeu o id.');
        $this->assertSame('Enunciado reescrito?', $q1->texto_questao);
        $this->assertSame(2, $q1->indice_opcao_correta);
        // Não foi digitado de novo, e o que já estava gravado não é jogado fora.
        $this->assertFalse($q1->permite_nova_tentativa);

        // A que saiu foi INATIVADA, e não apagada (ADR 12).
        $linha = DB::table('questao_avaliacao')->where('id', $quiz->id.'-q2')->first();
        $this->assertNotNull($linha, 'A questão retirada foi APAGADA.');
        $this->assertNotNull($linha->inativado_em);
    }

    public function test_o_formulario_de_edicao_traz_o_gabarito_e_avisa_de_quem_ja_respondeu(): void
    {
        $eu = $this->instrutor('Gestor Aviso Edicao');
        $curso = $this->curso($eu['id']);
        $quiz = $this->prova($curso, 'Prova Com Respostas');
        $aluno = $this->makeStudent('Aluno Que Respondeu');
        TentativaAvaliacao::query()->create([
            'id' => 'sub-aviso-'.uniqid(),
            'nome_aluno' => $aluno['nome'],
            'usuario_id' => $aluno['id'],
            'curso_id' => $curso->id,
            'avaliacao_id' => $quiz->id,
            'percentual_nota' => 100,
            'aprovado' => true,
            'enviado_em_texto' => '21/10/2026 às 10:00',
            'enviado_em' => now(),
        ]);

        $html = (string) $this->como($eu['token'])
            ->get('/inst/curso/'.$curso->slug.'/avaliacoes/'.$quiz->id.'/editar')->assertOk()->getContent();

        $this->assertStringContainsString('1 aluno já respondeu', $html);
        $this->assertStringContainsString('não recalcula', $html);
        // O gabarito vai para quem o escreve.
        $this->assertStringContainsString('Pergunta numero 1?', $html);
        // E os rótulos dizem PARA QUEM cada texto aparece.
        $this->assertStringContainsString('aparece para quem ACERTOU', $html);
        $this->assertStringContainsString('aparece para quem ERROU', $html);
    }

    public function test_prova_nova_avisa_que_passa_a_ser_exigida_para_o_certificado(): void
    {
        $eu = $this->instrutor('Gestor Aviso Nova');
        $curso = $this->curso($eu['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/avaliacoes/nova')
            ->assertOk()
            ->assertSee('exigida para o certificado', escape: false);
    }

    public function test_prova_de_outro_curso_nao_abre_pelo_endereco_deste(): void
    {
        $eu = $this->instrutor('Gestor Prova Irma');
        $um = $this->curso($eu['id'], 'Curso Um Das Provas');
        $dois = $this->curso($eu['id'], 'Curso Dois Das Provas');
        $deDois = $this->prova($dois, 'Prova Do Curso Dois');

        $this->como($eu['token'])->get('/inst/curso/'.$um->slug.'/avaliacoes/'.$deDois->id.'/editar')->assertNotFound();
        $this->como($eu['token'])->post('/inst/curso/'.$um->slug.'/avaliacoes/'.$deDois->id.'/remover')->assertNotFound();
    }

    /** ADR 12: retirar a prova NÃO apaga as respostas dos alunos. */
    public function test_retirar_inativa_a_prova_e_preserva_as_respostas(): void
    {
        $eu = $this->instrutor('Gestor Retira Prova');
        $curso = $this->curso($eu['id']);
        $quiz = $this->prova($curso);
        $aluno = $this->makeStudent('Aluno Resposta Preservada');
        TentativaAvaliacao::query()->create([
            'id' => 'sub-pres-'.uniqid(),
            'nome_aluno' => $aluno['nome'],
            'usuario_id' => $aluno['id'],
            'curso_id' => $curso->id,
            'avaliacao_id' => $quiz->id,
            'percentual_nota' => 80,
            'aprovado' => true,
            'enviado_em_texto' => '21/10/2026 às 10:00',
            'enviado_em' => now(),
        ]);

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/avaliacoes/'.$quiz->id.'/remover')
            ->assertRedirect('/inst/curso/'.$curso->slug.'/avaliacoes');

        $linha = DB::table('avaliacao')->where('id', $quiz->id)->first();
        $this->assertNotNull($linha, 'A prova foi APAGADA.');
        $this->assertNotNull($linha->inativado_em);
        $this->assertSame(1, TentativaAvaliacao::query()->where('avaliacao_id', $quiz->id)->count(), 'A resposta do aluno sumiu.');
        $this->assertTrue(DB::table('log_seguranca')->where('acao', 'Inativação de Avaliação')->exists());

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/avaliacoes')
            ->assertDontSee('Prova De Teste', escape: false);
    }

    /** O ciclo inteiro: o gestor cria, o aluno responde e a prova que ele vê não traz o gabarito. */
    public function test_a_prova_criada_pelo_gestor_chega_ao_aluno_sem_gabarito(): void
    {
        $eu = $this->instrutor('Gestor Ciclo Completo');
        $curso = $this->curso($eu['id']);
        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/avaliacoes', $this->corpoValido('Prova Do Ciclo'));

        $aluno = $this->makeStudent('Aluno Ciclo Completo');
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
        $avaliacao_id = (string) Avaliacao::query()->where('curso_id', $curso->id)->value('id');

        $html = (string) $this->como($aluno['token'])
            ->get('/aluno/curso/'.$curso->slug.'/avaliacoes/'.$avaliacao_id)->assertOk()->getContent();

        $this->assertStringContainsString('Qual e a capital do Brasil?', $html);
        $this->assertStringNotContainsString('indice_opcao_correta', $html);
        $this->assertStringNotContainsString('Brasilia e a capital desde 1960.', $html);
    }

    public function test_exercicios_desligado_o_servidor_explica(): void
    {
        $eu = $this->instrutor('Gestor Exercicios');
        $curso = $this->curso($eu['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/avaliacoes/exercicios')
            ->assertOk()
            ->assertDontSee('<div id="root">', false)
            ->assertSee('Esta seção não está disponível.', escape: false);
    }

    public function test_com_exercicios_ligado_a_tela_responde_501_ate_ser_construida(): void
    {
        config(['features.atividadesPraticasAvancadas' => true]);
        $eu = $this->instrutor('Gestor Exercicios Ligado');
        $curso = $this->curso($eu['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/avaliacoes/exercicios')->assertStatus(501);
    }

    public function test_sem_quiz_simples_tudo_responde_404(): void
    {
        config(['features.quizSimples' => false]);
        $eu = $this->instrutor('Gestor Sem Avaliacao');
        $curso = $this->curso($eu['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/avaliacoes')->assertNotFound();
        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/avaliacoes/nova')->assertNotFound();
    }
}
