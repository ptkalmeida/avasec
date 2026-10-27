<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Curso;
use App\Models\Avaliacao;
use App\Models\QuestaoAvaliacao;
use App\Models\TentativaAvaliacao;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Avaliações do aluno em Blade — a lista e a prova.
 *
 * Decisão de 20/10: a prova é corrigida no FIM, e o gabarito não chega ao
 * navegador. O React mostrava a alternativa certa a cada questão e deixava
 * refazer a questão até acertar — com a resposta final valendo nota. Os
 * testes que mais importam aqui são os que provam que a página não carrega o
 * gabarito e que a nota é a das respostas enviadas.
 */
final class AvaliacoesDoAlunoTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private string $slug;

    private Curso $curso;

    private Avaliacao $quiz;

    /** @var array{id:string,name:string,token:string} */
    private array $aluno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->slug = 'curso-avaliacao-'.Str::lower(Str::random(8));
        $this->curso = Curso::query()->create([
            'id' => 'course-avaliacao-'.uniqid(),
            'titulo' => 'Curso de Teste das Avaliações',
            'descricao' => 'd',
            'categoria' => 'Teste',
            'miniatura' => 'sem-imagem.png',
            'nome_instrutor' => 'Instrutor',
        ]);
        Curso::query()->whereKey($this->curso->id)->update(['slug' => $this->slug, 'status' => Visibilidade::PUBLICADO]);

        $this->quiz = Avaliacao::query()->create(['id' => 'quiz-teste-'.uniqid(), 'curso_id' => $this->curso->id, 'titulo' => 'Prova de Teste']);
        // A certa da 1ª é a C (índice 2); a da 2ª é a A (índice 0).
        $this->questao('q1', 'Primeira pergunta de teste?', 2, 'Explicação para quem acertou a primeira.', 'Dica para quem errou a primeira.', 'Módulo Um de Teste');
        $this->questao('q2', 'Segunda pergunta de teste?', 0, 'Explicação para quem acertou a segunda.', 'Dica para quem errou a segunda.', null);

        $this->aluno = $this->makeStudent('Aluno Avaliacoes '.uniqid());
        DB::table('matricula')->insert([
            'id' => (string) Str::uuid(),
            'usuario_id' => $this->aluno['id'],
            'nome_aluno' => $this->aluno['nome'],
            'curso_matriculado_id' => $this->curso->id,
            'cursos_concluidos_ids' => json_encode([]),
            'pode_multimatricula' => false,
            'cursos_extras_ids' => json_encode([]),
        ]);
    }

    private function questao(string $sufixo, string $texto, int $certa, string $explicacao, string $dica, ?string $modulo): void
    {
        QuestaoAvaliacao::query()->create([
            'id' => $this->quiz->id.'-'.$sufixo,
            'avaliacao_id' => $this->quiz->id,
            'texto_questao' => $texto,
            'opcoes' => ['Alternativa um', 'Alternativa dois', 'Alternativa três', 'Alternativa quatro'],
            'indice_opcao_correta' => $certa,
            'explicacao' => $explicacao,
            'mensagem_revisao' => $dica,
            'modulo_recomendado' => $modulo,
        ]);
    }

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    private function urlDaProva(): string
    {
        return '/aluno/curso/'.$this->slug.'/avaliacoes/'.$this->quiz->id;
    }

    /** @param array<string, string> $respostas */
    private function enviar(array $respostas): TestResponse
    {
        $corpo = [];
        foreach ($respostas as $sufixo => $indice) {
            $corpo[$this->quiz->id.'-'.$sufixo] = $indice;
        }

        return $this->como($this->aluno['token'])->from($this->urlDaProva())->post($this->urlDaProva(), ['respostas' => $corpo]);
    }

    public function test_visitante_sem_sessao_vai_para_o_login(): void
    {
        $this->get('/aluno/curso/'.$this->slug.'/avaliacoes')->assertRedirect('/entrar');
    }

    public function test_a_lista_e_servida_pelo_servidor_e_diz_a_regra_do_certificado(): void
    {
        $this->como($this->aluno['token'])->get('/aluno/curso/'.$this->slug.'/avaliacoes')
            ->assertOk()
            ->assertDontSee('<div id="root">', false)
            ->assertSee('Prova de Teste', escape: false)
            ->assertSee('Não feita', escape: false)
            ->assertSee('Começar', escape: false)
            ->assertSee('0 de 1 avaliação aprovada', escape: false);
    }

    /** O coração da decisão: nada do gabarito vai na página da prova. */
    public function test_a_prova_nao_carrega_o_gabarito(): void
    {
        $html = (string) $this->como($this->aluno['token'])->get($this->urlDaProva())->assertOk()->getContent();

        $this->assertStringContainsString('Primeira pergunta de teste?', $html);
        $this->assertStringContainsString('Alternativa três', $html);
        $this->assertStringNotContainsString('indice_opcao_correta', $html);
        $this->assertStringNotContainsString('Explicação para quem acertou', $html);
        $this->assertStringNotContainsString('Dica para quem errou', $html);
        $this->assertStringNotContainsString('Módulo Um de Teste', $html);
    }

    public function test_curso_alheio_responde_404(): void
    {
        $outro = $this->makeStudent('Aluno Sem Matricula '.uniqid());

        $this->como($outro['token'])->get('/aluno/curso/'.$this->slug.'/avaliacoes')->assertNotFound();
        $this->como($outro['token'])->get($this->urlDaProva())->assertNotFound();
        $this->como($outro['token'])->post($this->urlDaProva(), ['respostas' => []])->assertNotFound();
    }

    public function test_avaliacao_de_outro_curso_pelo_endereco_deste_responde_404(): void
    {
        $alheia = Avaliacao::query()->where('curso_id', '!=', $this->curso->id)->value('id');
        if (! is_string($alheia)) {
            $this->markTestSkipped('Não há avaliação de outro curso no banco.');
        }

        $this->como($this->aluno['token'])->get('/aluno/curso/'.$this->slug.'/avaliacoes/'.$alheia)->assertNotFound();
    }

    public function test_avaliacao_em_rascunho_nao_aparece_nem_abre(): void
    {
        Avaliacao::query()->whereKey($this->quiz->id)->update(['status' => Visibilidade::RASCUNHO]);

        $this->como($this->aluno['token'])->get('/aluno/curso/'.$this->slug.'/avaliacoes')
            ->assertDontSee('Prova de Teste', escape: false);
        $this->como($this->aluno['token'])->get($this->urlDaProva())->assertNotFound();
    }

    public function test_a_nota_e_a_das_respostas_e_fica_registrada(): void
    {
        $this->enviar(['q1' => '2', 'q2' => '3'])->assertRedirect($this->urlDaProva());

        $tentativa = TentativaAvaliacao::query()->where('usuario_id', $this->aluno['id'])->where('avaliacao_id', $this->quiz->id)->first();
        $this->assertNotNull($tentativa, 'A tentativa não foi gravada.');
        $this->assertSame(50, (int) $tentativa->percentual_nota);
        $this->assertFalse((bool) $tentativa->aprovado);
        $this->assertSame($this->curso->id, $tentativa->curso_id);
    }

    /**
     * Depois de enviar: o resultado diz o que errou e dá a dica, mas não diz
     * qual era a certa — quem refaz a prova não a refaz com a cola na mão.
     */
    public function test_o_resultado_diz_o_que_errou_sem_entregar_a_certa(): void
    {
        $html = (string) $this->como($this->aluno['token'])
            ->followingRedirects()
            ->from($this->urlDaProva())
            ->post($this->urlDaProva(), ['respostas' => [$this->quiz->id.'-q1' => '2', $this->quiz->id.'-q2' => '3']])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Ainda não foi desta vez', $html);
        $this->assertStringContainsString('1 de 2', $html);
        $this->assertStringContainsString('50%', $html);
        $this->assertStringContainsString('role="status"', $html);
        // Acertou a 1ª: vê a explicação. Errou a 2ª: vê a dica, não a explicação.
        $this->assertStringContainsString('Explicação para quem acertou a primeira.', $html);
        $this->assertStringContainsString('Dica para quem errou a segunda.', $html);
        $this->assertStringNotContainsString('Explicação para quem acertou a segunda.', $html);
        $this->assertStringNotContainsString('alternativa correta', $html);
        $this->assertStringNotContainsString('Alternativa um', $html);
    }

    public function test_aprovado_com_todas_certas_e_a_lista_mostra_aprovada(): void
    {
        $this->enviar(['q1' => '2', 'q2' => '0']);

        $this->como($this->aluno['token'])->get('/aluno/curso/'.$this->slug.'/avaliacoes')
            ->assertSee('Aprovada', escape: false)
            ->assertSee('100%', escape: false)
            ->assertSee('1 de 1 avaliação aprovada', escape: false);
    }

    /** Como o certificado: uma tentativa aprovada basta, e refazer pior não desfaz. */
    public function test_refazer_pior_depois_de_aprovado_nao_desfaz_a_aprovacao(): void
    {
        $this->enviar(['q1' => '2', 'q2' => '0']);
        $this->enviar(['q1' => '0', 'q2' => '1']);

        $this->assertSame(2, TentativaAvaliacao::query()->where('usuario_id', $this->aluno['id'])->where('avaliacao_id', $this->quiz->id)->count());

        $this->como($this->aluno['token'])->get('/aluno/curso/'.$this->slug.'/avaliacoes')
            ->assertSee('Aprovada', escape: false)
            ->assertSee('0%', escape: false)
            ->assertSee('2 tentativas', escape: false);
    }

    public function test_faltando_questao_nada_e_gravado_e_o_marcado_volta(): void
    {
        $this->enviar(['q1' => '2'])
            ->assertRedirect($this->urlDaProva())
            ->assertSessionHasErrors('respostas');

        $this->assertSame(0, TentativaAvaliacao::query()->where('usuario_id', $this->aluno['id'])->count());

        $html = (string) $this->como($this->aluno['token'])
            ->withSession(['_old_input' => ['respostas' => [$this->quiz->id.'-q1' => '2']]])
            ->get($this->urlDaProva())->getContent();
        $this->assertMatchesRegularExpression('/value="2"\s+checked/', $html);
    }

    public function test_alternativa_que_nao_existe_conta_como_nao_respondida(): void
    {
        $this->enviar(['q1' => '9', 'q2' => '0'])->assertSessionHasErrors('respostas');
        $this->enviar(['q1' => '-1', 'q2' => '0'])->assertSessionHasErrors('respostas');

        $this->assertSame(0, TentativaAvaliacao::query()->where('usuario_id', $this->aluno['id'])->count());
    }

    /** Nota do formulário é ignorada: quem corrige é o servidor. */
    public function test_nota_enviada_pelo_formulario_e_ignorada(): void
    {
        $this->como($this->aluno['token'])->from($this->urlDaProva())->post($this->urlDaProva(), [
            'respostas' => [$this->quiz->id.'-q1' => '0', $this->quiz->id.'-q2' => '1'],
            'percentual_nota' => 100,
            'aprovado' => true,
        ]);

        $tentativa = TentativaAvaliacao::query()->where('usuario_id', $this->aluno['id'])->first();
        $this->assertNotNull($tentativa);
        $this->assertSame(0, (int) $tentativa->percentual_nota);
        $this->assertFalse((bool) $tentativa->aprovado);
    }

    public function test_pelo_id_do_curso_vai_para_o_endereco_de_hoje(): void
    {
        $this->como($this->aluno['token'])->get('/aluno/curso/'.$this->curso->id.'/avaliacoes')
            ->assertRedirect('/aluno/curso/'.$this->slug.'/avaliacoes');
    }

    public function test_com_quiz_simples_desligado_responde_404(): void
    {
        config(['features.quizSimples' => false]);

        $this->como($this->aluno['token'])->get('/aluno/curso/'.$this->slug.'/avaliacoes')->assertNotFound();
        $this->como($this->aluno['token'])->get($this->urlDaProva())->assertNotFound();
    }

    public function test_a_tela_do_curso_leva_as_avaliacoes(): void
    {
        $this->como($this->aluno['token'])->get('/aluno/curso/'.$this->slug)
            ->assertSee('href="/aluno/curso/'.$this->slug.'/avaliacoes"', escape: false);
    }
}
