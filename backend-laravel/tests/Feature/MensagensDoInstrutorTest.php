<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\MensagemDireta;
use App\Models\Usuario;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * `/inst/mensagens` — o gestor responde as dúvidas dos alunos.
 *
 * Decisão do responsável em 21/10: o gestor precisa responder quem o procurou,
 * numa página só. Dois testes carregam o peso aqui — o que prova que o gestor
 * não alcança aluno de outro curso, e o que prova que ele não pode ESCREVER
 * para esse aluno. A segunda metade não existia: a leitura era escopada e a
 * escrita não.
 */
final class MensagensDoInstrutorTest extends TestCase
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

    private function cursoDe(string $instrutor_id): Curso
    {
        $curso = Curso::query()->create([
            'id' => 'course-msg-'.uniqid(),
            'titulo' => 'Curso de Mensagens '.Str::random(5),
            'descricao' => 'd',
            'categoria' => 'Teste',
            'miniatura' => 'sem-imagem.png',
            'instrutor_id' => $instrutor_id,
            'nome_instrutor' => 'Instrutor',
        ]);
        Curso::query()->whereKey($curso->id)->update([
            'slug' => 'curso-msg-'.Str::lower(Str::random(8)),
            'status' => Visibilidade::PUBLICADO,
        ]);

        return $curso->refresh();
    }

    /** @return array{id:string,name:string,token:string} */
    private function alunoDoCurso(string $nome, Curso $curso): array
    {
        $aluno = $this->makeStudent($nome);
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

        return $aluno;
    }

    public function test_visitante_sem_sessao_vai_para_o_login(): void
    {
        $this->get('/inst/mensagens')->assertRedirect('/entrar');
    }

    public function test_aluno_nao_entra_na_caixa_do_gestor(): void
    {
        $aluno = $this->makeStudent('Aluno Na Caixa');

        $this->como($aluno['token'])->get('/inst/mensagens')->assertRedirect('/aluno');
    }

    public function test_a_duvida_do_aluno_chega_a_caixa_com_a_aula_como_etiqueta(): void
    {
        $eu = $this->instrutor('Gestor Caixa');
        $curso = $this->cursoDe($eu['id']);
        $aluno = $this->alunoDoCurso('Aluno Que Escreve', $curso);

        MensagemDireta::query()->create([
            'id' => 'dm-caixa-'.uniqid(),
            'nome_aluno' => $aluno['nome'],
            'aluno_usuario_id' => $aluno['id'],
            'nome_remetente' => $aluno['nome'],
            'remetente_usuario_id' => $aluno['id'],
            'papel_remetente' => 'student',
            'texto' => '[Aula: Aula de Teste da Caixa] Nao entendi o exercicio.',
            'registrado_em_texto' => now()->toIso8601String(),
            'enviada_em' => now(),
        ]);

        $resposta = $this->como($eu['token'])->get('/inst/mensagens?aluno='.$aluno['id']);

        $resposta->assertOk();
        $resposta->assertDontSee('<div id="root">', false);
        $resposta->assertSee('Aluno Que Escreve', escape: false);
        $resposta->assertSee('Nao entendi o exercicio.', escape: false);
        $resposta->assertSee('Aula: Aula de Teste da Caixa', escape: false);
        // A marca crua não vaza para a tela.
        $resposta->assertDontSee('[Aula:', escape: false);
        // Quem espera resposta é anunciado.
        $resposta->assertSee('Aguarda', escape: false);
    }

    public function test_responder_grava_a_mensagem_e_a_conversa_deixa_de_esperar(): void
    {
        $eu = $this->instrutor('Gestor Responde');
        $curso = $this->cursoDe($eu['id']);
        $aluno = $this->alunoDoCurso('Aluno Respondido', $curso);

        $this->como($aluno['token'])->post('/aluno/suporte', ['texto' => 'Duvida para responder.']);

        $this->como($eu['token'])
            ->post('/inst/mensagens', ['aluno' => $aluno['id'], 'texto' => 'Resposta do gestor.'])
            ->assertRedirect('/inst/mensagens?aluno='.urlencode($aluno['id']));

        $gravada = MensagemDireta::query()
            ->where('aluno_usuario_id', $aluno['id'])
            ->where('papel_remetente', 'instructor')
            ->first();

        $this->assertNotNull($gravada, 'A resposta não chegou ao banco.');
        $this->assertSame('Resposta do gestor.', $gravada->texto);
        $this->assertSame($eu['id'], $gravada->remetente_usuario_id);

        // E o aluno lê a resposta na área dele.
        $this->como($aluno['token'])->get('/aluno/suporte')->assertSee('Resposta do gestor.', escape: false);

        // A conversa sai da fila de espera.
        $this->como($eu['token'])->get('/inst/mensagens')->assertDontSee('Aguarda', escape: false);
    }

    /** Leitura escopada: a conversa de aluno de outro instrutor não aparece. */
    public function test_nao_le_conversa_de_aluno_de_outro_instrutor(): void
    {
        $eu = $this->instrutor('Gestor Um');
        $outro = $this->instrutor('Gestor Dois');
        $cursoAlheio = $this->cursoDe($outro['id']);
        $alheio = $this->alunoDoCurso('Aluno Do Outro Gestor', $cursoAlheio);

        $this->como($alheio['token'])->post('/aluno/suporte', ['texto' => 'Duvida que nao e sua.']);

        $this->como($eu['token'])->get('/inst/mensagens')
            ->assertDontSee('Aluno Do Outro Gestor', escape: false)
            ->assertDontSee('Duvida que nao e sua.', escape: false);

        // Nem pedindo o aluno pelo endereço.
        $this->como($eu['token'])->get('/inst/mensagens?aluno='.$alheio['id'])
            ->assertDontSee('Duvida que nao e sua.', escape: false);
    }

    /**
     * A metade que faltava: ESCREVER para aluno de outro curso. Antes de
     * 21/10, qualquer instrutor autenticado podia mandar mensagem a qualquer
     * aluno da escola, e ela aparecia na caixa daquele aluno com o nome dele.
     */
    public function test_nao_escreve_para_aluno_de_outro_instrutor(): void
    {
        $eu = $this->instrutor('Gestor Escrita Um');
        $outro = $this->instrutor('Gestor Escrita Dois');
        $alheio = $this->alunoDoCurso('Aluno Alheio Escrita', $this->cursoDe($outro['id']));

        $this->como($eu['token'])
            ->from('/inst/mensagens')
            ->post('/inst/mensagens', ['aluno' => $alheio['id'], 'texto' => 'Nao deveria chegar.'])
            ->assertSessionHasErrors('texto');

        $this->assertFalse(
            MensagemDireta::query()->where('aluno_usuario_id', $alheio['id'])->where('remetente_usuario_id', $eu['id'])->exists(),
            'O instrutor escreveu para um aluno que não é dos cursos dele.',
        );
    }

    /** A mesma regra vale na API, que é a porta de onde o defeito vinha. */
    public function test_a_api_tambem_recusa_a_escrita_fora_do_escopo(): void
    {
        $eu = $this->instrutor('Gestor API');
        $outro = $this->instrutor('Gestor API Dois');
        $alheio = $this->alunoDoCurso('Aluno Alheio API', $this->cursoDe($outro['id']));

        $this->withHeader('Authorization', 'Bearer '.$eu['token'])
            ->postJson('/api/mensagens-diretas', ['aluno_usuario_id' => $alheio['id'], 'texto' => 'Nao deveria chegar.'])
            ->assertForbidden();
    }

    /**
     * O defeito que deixou a caixa vazia com dúvida dentro dela.
     *
     * `InstructorScope::studentIds` só olhava a matrícula PRINCIPAL
     * (`curso_matriculado_id`). O aluno de demonstração está no curso por matrícula
     * CONCORRENTE (`cursos_extras_ids`), e por isso nenhum gestor alcançava a
     * mensagem dele — nem a resposta que já existia no banco. O escopo passou
     * a contar as duas formas.
     */
    public function test_aluno_por_matricula_concorrente_e_aluno_do_instrutor(): void
    {
        $eu = $this->instrutor('Gestor Concorrente');
        $curso = $this->cursoDe($eu['id']);

        $aluno = $this->makeStudent('Aluno Matricula Concorrente');
        Matricula::query()->create([
            'id' => (string) Str::uuid(),
            'usuario_id' => $aluno['id'],
            'nome_aluno' => $aluno['nome'],
            // Sem curso principal: ele está no curso pela via concorrente.
            'curso_matriculado_id' => null,
            'matriculado_em_texto' => now(),
            'cursos_concluidos_ids' => [],
            'penalidade_desistencia_ate' => null,
            'pode_multimatricula' => true,
            'cursos_extras_ids' => [$curso->id],
        ]);

        $this->como($aluno['token'])->post('/aluno/suporte', ['texto' => 'Duvida do concorrente.']);

        $this->como($eu['token'])->get('/inst/mensagens')
            ->assertSee('Aluno Matricula Concorrente', escape: false)
            ->assertSee('Duvida do concorrente.', escape: false);

        // E o gestor pode responder quem alcança.
        $this->como($eu['token'])
            ->post('/inst/mensagens', ['aluno' => $aluno['id'], 'texto' => 'Resposta ao concorrente.'])
            ->assertRedirect('/inst/mensagens?aluno='.urlencode($aluno['id']));
    }

    /**
     * Um id de curso que é PREFIXO de outro não pode casar por engano: a busca
     * em `cursos_extras_ids` é por texto na coluna JSON.
     */
    public function test_curso_cujo_id_e_prefixo_de_outro_nao_entra_no_escopo(): void
    {
        $eu = $this->instrutor('Gestor Prefixo');
        $meu = $this->cursoDe($eu['id']);

        $outro = $this->instrutor('Gestor Prefixo Dois');
        $cursoAlheio = $this->cursoDe($outro['id']);
        // O id do curso alheio passa a começar com o id do meu.
        $idDerivado = $meu->id.'-extensao';
        Curso::query()->whereKey($cursoAlheio->id)->update(['id' => $idDerivado]);

        $aluno = $this->makeStudent('Aluno Do Curso Derivado');
        Matricula::query()->create([
            'id' => (string) Str::uuid(),
            'usuario_id' => $aluno['id'],
            'nome_aluno' => $aluno['nome'],
            'curso_matriculado_id' => null,
            'matriculado_em_texto' => now(),
            'cursos_concluidos_ids' => [],
            'penalidade_desistencia_ate' => null,
            'pode_multimatricula' => true,
            'cursos_extras_ids' => [$idDerivado],
        ]);

        $this->como($aluno['token'])->post('/aluno/suporte', ['texto' => 'Duvida do derivado.']);

        $this->como($eu['token'])->get('/inst/mensagens')
            ->assertDontSee('Aluno Do Curso Derivado', escape: false);
    }

    /**
     * O aviso de quem espera aparece no menu de QUALQUER tela da área — o menu
     * calcula sozinho quando a página não passa o número. É a armadilha que o
     * menu do aluno já pagou com "Cursos concluídos".
     */
    public function test_o_menu_avisa_quem_espera_mesmo_em_outra_tela(): void
    {
        $eu = $this->instrutor('Gestor Aviso No Menu');
        $curso = $this->cursoDe($eu['id']);
        $aluno = $this->alunoDoCurso('Aluno Que Aguarda', $curso);
        $this->como($aluno['token'])->post('/aluno/suporte', ['texto' => 'Duvida pendente.']);

        $html = (string) $this->como($eu['token'])->get('/inst')->getContent();

        $this->assertMatchesRegularExpression('/pendente<\/span>/u', $html);
        $this->assertStringContainsString('href="/inst/mensagens"', $html);
    }

    public function test_sem_o_canal_aberto_a_caixa_responde_404(): void
    {
        config(['features.mensagensDiretas' => false]);
        $eu = $this->instrutor('Gestor Sem Canal');

        $this->como($eu['token'])->get('/inst/mensagens')->assertNotFound();
        $this->como($eu['token'])->post('/inst/mensagens', ['aluno' => 'x', 'texto' => 'y'])->assertNotFound();
    }

    public function test_caixa_vazia_explica_em_vez_de_mostrar_nada(): void
    {
        $eu = $this->instrutor('Gestor Caixa Vazia');
        $this->cursoDe($eu['id']);

        $this->como($eu['token'])->get('/inst/mensagens')
            ->assertOk()
            ->assertSee('Nenhum aluno escreveu ainda.', escape: false);
    }

    /** O que o painel React mostrava e não vem: a presença inventada. */
    public function test_nao_mostra_presenca_nem_selo_de_qualificado(): void
    {
        $eu = $this->instrutor('Gestor Sem Presenca');
        $curso = $this->cursoDe($eu['id']);
        $aluno = $this->alunoDoCurso('Aluno Sem Presenca', $curso);
        $this->como($aluno['token'])->post('/aluno/suporte', ['texto' => 'Duvida qualquer.']);

        $this->como($eu['token'])->get('/inst/mensagens?aluno='.$aluno['id'])
            ->assertDontSee('Presença', escape: false)
            ->assertDontSee('Qualificado', escape: false);
    }
}
