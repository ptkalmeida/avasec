<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Aula;
use App\Models\Certificado;
use App\Models\Curso;
use App\Models\ExercicioPratico;
use App\Models\Matricula;
use App\Models\MensagemForum;
use App\Models\SessaoAoVivo;
use App\Models\Usuario;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\ComVerificacaoPublicaLigada;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Achados da varredura de segurança de 23/10/2026 que não cabem num arquivo de
 * domínio só. Cada teste descreve o ataque que deixou de funcionar.
 */
final class VarreduraDeSegurancaTest extends TestCase
{
    use ComVerificacaoPublicaLigada;
    use DatabaseTransactions;
    use SeedsIdentity;

    /** @return array{id:string,token:string,curso:Curso} */
    private function instrutorComCurso(string $nome): array
    {
        $id = (string) Str::uuid();
        Usuario::query()->create([
            'id' => $id, 'nome' => $nome, 'email' => Str::lower(Str::random(10)).'@example.com',
            'senha_hash' => password_hash('senha123456', PASSWORD_BCRYPT), 'papel' => 'instructor',
        ]);
        $curso = Curso::query()->create([
            'id' => 'course-seg-'.uniqid(), 'titulo' => 'Curso '.$nome,
            'descricao' => 'Descrição longa o bastante para o validador aceitar.', 'categoria' => 'Teste',
            'miniatura' => 'sem-imagem.png', 'instrutor_id' => $id, 'nome_instrutor' => $nome,
        ]);

        return ['id' => $id, 'token' => Jwt::issue($id, $nome, 'instructor'), 'curso' => $curso];
    }

    public function test_instrutor_nao_marca_curso_como_concluido_nem_mexe_na_penalidade(): void
    {
        $eu = $this->instrutorComCurso('Instrutor Seguranca Um');
        $aluno = $this->makeStudent('Aluno Seguranca Concluido');

        $this->withHeader('Authorization', 'Bearer '.$eu['token'])
            ->putJson('/api/matriculas/'.$aluno['id'], [
                'curso_matriculado_id' => $eu['curso']->id,
                'cursos_concluidos_ids' => [$this->anySeededCourseId()],
            ])->assertForbidden();

        $this->withHeader('Authorization', 'Bearer '.$eu['token'])
            ->putJson('/api/matriculas/'.$aluno['id'], [
                'curso_matriculado_id' => $eu['curso']->id,
                'penalidade_desistencia_ate' => '2099-01-01',
            ])->assertForbidden();

        $this->assertNull(Matricula::query()->whereKey($aluno['id'])->value('curso_matriculado_id'));
    }

    public function test_instrutor_nao_toma_o_aluno_do_curso_de_outro_instrutor(): void
    {
        $dono = $this->instrutorComCurso('Instrutor Seguranca Dono');
        $intruso = $this->instrutorComCurso('Instrutor Seguranca Intruso');
        $aluno = $this->makeStudent('Aluno Seguranca Alheio');

        // O dono matricula o aluno no curso dele.
        $this->withHeader('Authorization', 'Bearer '.$dono['token'])
            ->putJson('/api/matriculas/'.$aluno['id'], ['curso_matriculado_id' => $dono['curso']->id])
            ->assertOk();

        // O intruso tenta passá-lo para o curso dele.
        $this->withHeader('Authorization', 'Bearer '.$intruso['token'])
            ->putJson('/api/matriculas/'.$aluno['id'], ['curso_matriculado_id' => $intruso['curso']->id])
            ->assertForbidden();

        $this->assertSame(
            $dono['curso']->id,
            Matricula::query()->whereKey($aluno['id'])->value('curso_matriculado_id'),
        );
    }

    public function test_instrutor_ainda_matricula_aluno_sem_matricula_no_proprio_curso(): void
    {
        $eu = $this->instrutorComCurso('Instrutor Seguranca Normal');
        $aluno = $this->makeStudent('Aluno Seguranca Normal');

        $this->withHeader('Authorization', 'Bearer '.$eu['token'])
            ->putJson('/api/matriculas/'.$aluno['id'], ['curso_matriculado_id' => $eu['curso']->id])
            ->assertOk()
            ->assertJsonPath('curso_matriculado_id', $eu['curso']->id);
    }

    public function test_as_telas_saem_com_nosniff_e_sem_poder_ser_embutidas_por_outro_site(): void
    {
        foreach (['/', '/o-ava', '/entrar', '/uma-tela-que-nunca-existiu'] as $caminho) {
            $resposta = $this->get($caminho);
            $resposta->assertHeader('X-Content-Type-Options', 'nosniff');
            $resposta->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        }
    }

    public function test_instrutor_nao_sequestra_aula_nem_encontro_de_outro_curso_pelo_put_do_curso(): void
    {
        $dono = $this->instrutorComCurso('Instrutor Seguranca Aulas Dono');
        $intruso = $this->instrutorComCurso('Instrutor Seguranca Aulas Intruso');
        $aula = Aula::query()->create([
            'id' => 'lesson-seg-'.uniqid(), 'curso_id' => $dono['curso']->id, 'titulo' => 'Aula Do Dono',
            'duracao' => '10 min', 'conteudo' => 'Texto do dono.', 'ordem' => 1,
        ]);
        $encontro = SessaoAoVivo::query()->create([
            'id' => 'live-seg-'.uniqid(), 'curso_id' => $dono['curso']->id, 'titulo' => 'Encontro Do Dono',
            'agendado_para_texto' => '2030-01-01T10:00', 'duracao_minutos' => 60, 'link_reuniao' => 'https://example.com/sala-do-dono',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$intruso['token'])
            ->putJson('/api/cursos/'.$intruso['curso']->id, [
                'aulas' => [['id' => $aula->id, 'titulo' => 'Aula Sequestrada', 'duracao' => '1 min', 'conteudo' => 'hack', 'order' => 0]],
                'sessoes_ao_vivo' => [['id' => $encontro->id, 'titulo' => 'Encontro Sequestrado', 'agendado_para_texto' => '2030-02-02T10:00', 'duracao_minutos' => 60, 'link_reuniao' => 'https://example.com/sala-do-intruso']],
            ])->assertOk();

        $aulaDepois = Aula::query()->findOrFail($aula->id);
        $this->assertSame($dono['curso']->id, $aulaDepois->curso_id);
        $this->assertSame('Aula Do Dono', $aulaDepois->titulo);
        $this->assertSame('Texto do dono.', $aulaDepois->conteudo);

        $encontroDepois = SessaoAoVivo::query()->findOrFail($encontro->id);
        $this->assertSame($dono['curso']->id, $encontroDepois->curso_id);
        $this->assertSame('Encontro Do Dono', $encontroDepois->titulo);

        // O conteúdo do intruso entrou como aula NOVA, no curso dele.
        $this->assertSame(1, Aula::query()->where('curso_id', $intruso['curso']->id)->where('titulo', 'Aula Sequestrada')->count());
    }

    public function test_aluno_nao_registra_progresso_nem_pede_certificado_de_curso_em_que_nao_esta(): void
    {
        $dono = $this->instrutorComCurso('Instrutor Seguranca Progresso');
        $aula = Aula::query()->create([
            'id' => 'lesson-prog-'.uniqid(), 'curso_id' => $dono['curso']->id, 'titulo' => 'Unica Aula',
            'duracao' => '10 min', 'conteudo' => 'x', 'ordem' => 1,
        ]);
        $aluno = $this->makeStudent('Aluno Seguranca Forja');
        $h = ['Authorization' => 'Bearer '.$aluno['token']];

        // Os ids de aula saem no catálogo público: bastava mandá-los para "cursar".
        $this->postJson('/api/progresso', ['curso_id' => $dono['curso']->id, 'aulas_concluidas' => [$aula->id]], $h)->assertForbidden();
        $this->postJson('/api/certificados', ['curso_id' => $dono['curso']->id], $h)->assertForbidden();
    }

    public function test_aluno_matriculado_segue_registrando_progresso(): void
    {
        $dono = $this->instrutorComCurso('Instrutor Seguranca Progresso Ok');
        $aula = Aula::query()->create([
            'id' => 'lesson-prog-ok-'.uniqid(), 'curso_id' => $dono['curso']->id, 'titulo' => 'Unica Aula',
            'duracao' => '10 min', 'conteudo' => 'x', 'ordem' => 1,
        ]);
        $aluno = $this->makeStudent('Aluno Seguranca Progresso Ok');
        $this->matricular($aluno, $dono['curso']->id);

        $this->postJson('/api/progresso', ['curso_id' => $dono['curso']->id, 'aulas_concluidas' => [$aula->id]], ['Authorization' => 'Bearer '.$aluno['token']])
            ->assertOk();
    }

    public function test_consulta_publica_de_certificado_nao_enumera_alunos_por_pedaco_de_nome(): void
    {
        $dono = $this->instrutorComCurso('Instrutor Seguranca Cert');
        $aluno = $this->makeStudent('Zenaide Quaresma Seguranca');
        $cert = Certificado::query()->create([
            'id' => 'cert-seg-'.uniqid(), 'nome_aluno' => $aluno['nome'], 'usuario_id' => $aluno['id'],
            'curso_id' => $dono['curso']->id, 'titulo_curso' => $dono['curso']->titulo, 'data_emissao' => now(),
            'percentual_frequencia' => 100, 'hash_verificacao' => 'AVA-SEG-'.strtoupper(uniqid()),
        ]);

        foreach (['Zen', '%', '_', 'Quaresma', 'a', ' '] as $pedaco) {
            $resposta = $this->getJson('/api/certificados/verificar?q='.rawurlencode($pedaco));
            $this->assertNotSame($cert->hash_verificacao, $resposta->json('hash_verificacao'), "«{$pedaco}» achou o certificado");
        }

        // O código e o nome completo continuam achando.
        $this->getJson('/api/certificados/verificar?q='.$cert->hash_verificacao)->assertOk()->assertJsonPath('hash_verificacao', $cert->hash_verificacao);
        $this->getJson('/api/certificados/verificar?q='.rawurlencode($aluno['nome']))->assertOk()->assertJsonPath('hash_verificacao', $cert->hash_verificacao);
    }

    public function test_admin_bloqueado_ou_rebaixado_nao_provisiona_administrador_com_o_token_antigo(): void
    {
        $id = (string) Str::uuid();
        Usuario::query()->create([
            'id' => $id, 'nome' => 'Admin Que Caiu', 'email' => Str::lower(Str::random(10)).'@example.com',
            'senha_hash' => password_hash('senha123456', PASSWORD_BCRYPT), 'papel' => 'admin',
        ]);
        $token = Jwt::issue($id, 'Admin Que Caiu', 'admin');
        $corpo = fn (): array => [
            'nome' => 'Admin Clandestino', 'email' => 'id-'.uniqid().'@example.com', 'password' => 'senha123456', 'papel' => 'admin',
        ];

        // Enquanto é admin de verdade, a chamada vale (prova de que o teste não passa por outro motivo).
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/autenticacao/cadastro', $corpo())->assertCreated();

        // Bloqueado: o token de 12 h continua assinado, mas não vale mais. A conta vale como visitante,
        // e visitante não cria administrador (a recusa pode vir como 400 ou 403, conforme a regra que pegar primeiro).
        Usuario::query()->whereKey($id)->update(['status' => 'blocked']);
        $bloqueado = $corpo();
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/autenticacao/cadastro', $bloqueado);
        $this->assertFalse(Usuario::query()->where('email', $bloqueado['email'])->exists());

        // Rebaixado a aluno e reativado: o papel vem do banco, não do token.
        Usuario::query()->whereKey($id)->update(['status' => 'active', 'papel' => 'student']);
        $rebaixado = $corpo();
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/autenticacao/cadastro', $rebaixado);
        $this->assertFalse(Usuario::query()->where('email', $rebaixado['email'])->exists());
    }

    public function test_aluno_nao_se_matricula_em_curso_em_rascunho_restrito_ou_inexistente_pela_api(): void
    {
        $dono = $this->instrutorComCurso('Instrutor Seguranca Matricula');
        $aluno = $this->makeStudent('Aluno Seguranca Matricula');
        $h = ['Authorization' => 'Bearer '.$aluno['token']];

        foreach ([Visibilidade::RASCUNHO, Visibilidade::RESTRITO] as $nivel) {
            Curso::query()->whereKey($dono['curso']->id)->update(['status' => $nivel]);

            $this->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $dono['curso']->id], $h)->assertNotFound();
            $this->postJson('/api/ingressos', ['curso_id' => $dono['curso']->id], $h)->assertNotFound();
        }
        $this->postJson('/api/matriculas/proprio/matricular', ['curso_id' => 'curso-que-nunca-existiu'], $h)->assertNotFound();
        $this->postJson('/api/ingressos', ['curso_id' => 'curso-que-nunca-existiu'], $h)->assertNotFound();

        $this->assertNull(Matricula::query()->whereKey($aluno['id'])->value('curso_matriculado_id'));

        // Publicado, a matrícula segue funcionando.
        Curso::query()->whereKey($dono['curso']->id)->update(['status' => Visibilidade::PUBLICADO]);
        $this->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $dono['curso']->id], $h)->assertOk();
    }

    public function test_curtir_mensagem_de_forum_e_entregar_exercicio_exigem_pertencer_ao_curso(): void
    {
        config(['features.forum' => true, 'features.atividadesPraticasAvancadas' => true]);
        $dono = $this->instrutorComCurso('Instrutor Seguranca Forum');
        $mensagem = MensagemForum::query()->create([
            'id' => 'forum-seg-'.uniqid(), 'curso_id' => $dono['curso']->id, 'nome_remetente' => 'Quem Escreveu',
            'remetente_usuario_id' => $dono['id'], 'papel_remetente' => 'instructor', 'texto' => 'Mensagem privada do curso.',
            'registrado_em_texto' => '23/10/2026 10:00', 'curtidas' => 0, 'curtido_por' => [],
        ]);
        $exercicio = ExercicioPratico::query()->create([
            'id' => 'ex-seg-'.uniqid(), 'curso_id' => $dono['curso']->id, 'titulo' => 'Exercicio Do Curso',
            'descricao' => 'x', 'instrucoes' => 'x', 'pontuacao_maxima' => 10, 'prazo_texto' => '31/12/2030',
        ]);
        $estranho = $this->makeStudent('Aluno Seguranca Forum Estranho');
        $deDentro = $this->makeStudent('Aluno Seguranca Forum Dentro');
        $this->matricular($deDentro, $dono['curso']->id);

        $fora = ['Authorization' => 'Bearer '.$estranho['token']];
        $dentro = ['Authorization' => 'Bearer '.$deDentro['token']];

        $this->putJson('/api/forum/'.$mensagem->id.'/curtir', [], $fora)->assertForbidden();
        $this->postJson('/api/entregas-exercicio', ['exercicio_id' => $exercicio->id, 'texto_entrega' => 'minha entrega'], $fora)->assertForbidden();
        $this->postJson('/api/entregas-exercicio', ['exercicio_id' => 'nao-existe', 'texto_entrega' => 'x'], $dentro)->assertNotFound();
        $this->postJson('/api/entregas-exercicio', ['exercicio_id' => $exercicio->id, 'texto_entrega' => 'x', 'url_arquivo' => 'javascript:alert(1)'], $dentro)->assertStatus(400);

        $this->putJson('/api/forum/'.$mensagem->id.'/curtir', [], $dentro)->assertOk();
        $this->postJson('/api/entregas-exercicio', ['exercicio_id' => $exercicio->id, 'texto_entrega' => 'minha entrega'], $dentro)->assertCreated();
    }

    public function test_o_papel_da_tela_de_entrar_nao_vira_javascript(): void
    {
        $ataque = "');alert(document.cookie)//";

        $resposta = $this->get('/entrar?papel='.rawurlencode($ataque));

        $resposta->assertOk();
        $resposta->assertDontSee('alert(document.cookie)', false);
        // Um valor desconhecido cai no padrão (aluno); os conhecidos continuam valendo.
        $this->get('/entrar?papel=admin')->assertOk();
        $this->get('/entrar?papel[]=x')->assertOk();
    }

    public function test_id_de_curso_com_curinga_de_like_e_recusado(): void
    {
        $eu = $this->instrutorComCurso('Instrutor Seguranca Id');

        foreach (['%', 'a_b', 'a%b', 'com espaco', "x'y"] as $id) {
            $this->withHeader('Authorization', 'Bearer '.$eu['token'])->postJson('/api/cursos', [
                'id' => $id, 'titulo' => 'Curso Com Id Estranho', 'descricao' => 'Descrição longa o bastante para o validador aceitar.',
                'categoria' => 'Teste', 'miniatura' => 'sem-imagem.png', 'nome_instrutor' => 'x',
            ])->assertStatus(400);
        }
    }

    public function test_falha_de_login_nao_grava_o_cpf_inteiro_nem_texto_fora_do_formato(): void
    {
        $ultimo = static fn (): string => (string) DB::table('log_seguranca')->where('acao', 'Tentativa Fracassada')
            ->orderByDesc('ocorrido_em')->orderByDesc('id')->value('detalhes');

        $this->postJson('/api/autenticacao/entrar', ['cpf' => '529.982.247-25', 'password' => 'errada', 'papel' => 'student']);
        $this->assertStringContainsString('***.***.***-25', $ultimo());
        $this->assertStringNotContainsString('529.982.247', $ultimo());

        // Pela tela de entrar o aluno também digita CPF; um texto qualquer (uma senha no campo errado) não é gravado.
        $this->post('/entrar', ['papel' => 'student', 'identificador' => 'MinhaSenhaSecreta#2026', 'senha' => 'x']);
        $this->assertStringNotContainsString('MinhaSenhaSecreta', $ultimo());
    }

    public function test_aluno_nao_responde_prova_em_rascunho_ou_restrita(): void
    {
        config(['features.quizSimples' => true]);
        $dono = $this->instrutorComCurso('Instrutor Seguranca Prova');
        $aluno = $this->makeStudent('Aluno Seguranca Prova');
        $this->matricular($aluno, $dono['curso']->id);

        $avaliacao_id = 'quiz-seg-'.uniqid();
        DB::table('avaliacao')->insert(['id' => $avaliacao_id, 'curso_id' => $dono['curso']->id, 'titulo' => 'Prova Em Rascunho', 'status' => Visibilidade::RASCUNHO]);
        DB::table('questao_avaliacao')->insert([
            'id' => $avaliacao_id.'-q1', 'avaliacao_id' => $avaliacao_id, 'texto_questao' => 'Pergunta?', 'opcoes' => json_encode(['a', 'b']),
            'indice_opcao_correta' => 0,
        ]);

        $this->postJson('/api/tentativas-avaliacao', ['avaliacao_id' => $avaliacao_id, 'answers' => [$avaliacao_id.'-q1' => 0]], ['Authorization' => 'Bearer '.$aluno['token']])
            ->assertNotFound();

        DB::table('avaliacao')->where('id', $avaliacao_id)->update(['status' => Visibilidade::PUBLICADO]);
        $this->postJson('/api/tentativas-avaliacao', ['avaliacao_id' => $avaliacao_id, 'answers' => [$avaliacao_id.'-q1' => 0]], ['Authorization' => 'Bearer '.$aluno['token']])
            ->assertSuccessful();
    }

    public function test_admin_continua_podendo_registrar_conclusao(): void
    {
        $aluno = $this->makeStudent('Aluno Seguranca Admin');
        $curso = $this->anySeededCourseId();

        $this->withHeader('Authorization', 'Bearer '.$this->staffToken('admin'))
            ->putJson('/api/matriculas/'.$aluno['id'], ['cursos_concluidos_ids' => [$curso]])
            ->assertOk();
    }
}
