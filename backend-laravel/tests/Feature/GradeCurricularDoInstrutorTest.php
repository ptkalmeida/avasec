<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Curso;
use App\Models\Aula;
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
 * `/inst/curso/{ref}/grade-curricular` — a lista de aulas em Blade.
 *
 * Duas escritas moram aqui: mudar a ordem e retirar uma aula. O que os testes
 * prendem: que só a ORDEM muda ao subir ou descer (o React reenviava o curso
 * inteiro e dois instrutores se sobrescreviam), que retirar NÃO apaga (ADR 12),
 * e que dá para retirar a ÚLTIMA aula — o `syncLessons` não deixava.
 */
final class GradeCurricularDoInstrutorTest extends TestCase
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

    /** @return array{0:Curso,1:list<Aula>} */
    private function cursoComAulas(string $instrutor_id, int $quantas = 3, string $titulo = 'Curso Da Grade Teste'): array
    {
        $curso = Curso::query()->create([
            'id' => 'course-grade-'.uniqid(),
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

        $aulas = [];
        for ($i = 1; $i <= $quantas; $i++) {
            $aulas[] = Aula::query()->create([
                'id' => 'lesson-grade-'.$curso->id.'-'.$i,
                'curso_id' => $curso->id,
                'titulo' => "Aula numero {$i}",
                'duracao' => '30 min',
                'conteudo' => "Conteudo {$i}",
                'ordem' => $i,
            ]);
        }

        return [$curso->refresh(), $aulas];
    }

    /** @return list<string> */
    private function ordemAtual(Curso $curso): array
    {
        /** @var list<string> $ids */
        $ids = Aula::query()->where('curso_id', $curso->id)->orderBy('ordem')->orderBy('id')->pluck('id')->all();

        return $ids;
    }

    public function test_visitante_sem_sessao_vai_para_o_login(): void
    {
        $this->get('/inst/curso/qualquer/grade-curricular')->assertRedirect('/entrar');
    }

    public function test_lista_as_aulas_em_ordem_com_o_tempo_total(): void
    {
        $eu = $this->instrutor('Gestor Grade');
        [$curso] = $this->cursoComAulas($eu['id']);

        $resposta = $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/grade-curricular');

        $resposta->assertOk();
        $resposta->assertDontSee('<div id="root">', false);
        $resposta->assertSeeInOrder(['Aula numero 1', 'Aula numero 2', 'Aula numero 3'], false);
        // 3 × 30 min = 90 min = 1,5 h.
        $resposta->assertSee('tempo total 1,5 h', escape: false);
        // A página da aula é do servidor, com endereço próprio.
        $resposta->assertSee('href="/inst/curso/'.$curso->slug.'/aulas/nova"', escape: false);
        $resposta->assertSee('/aulas/lesson-grade-', escape: false);
        $resposta->assertDontSee('/aula/lesson-grade-', escape: false);
    }

    public function test_curso_de_outro_instrutor_responde_404_na_leitura_e_nas_escritas(): void
    {
        $eu = $this->instrutor('Gestor Um Grade');
        $outro = $this->instrutor('Gestor Dois Grade');
        [$alheio, $aulas] = $this->cursoComAulas($outro['id'], 2, 'Curso Alheio Da Grade');

        $this->como($eu['token'])->get('/inst/curso/'.$alheio->slug.'/grade-curricular')->assertNotFound();
        $this->como($eu['token'])
            ->post('/inst/curso/'.$alheio->slug.'/aulas/'.$aulas[0]->id.'/mover', ['direcao' => 'down'])
            ->assertNotFound();
        $this->como($eu['token'])
            ->post('/inst/curso/'.$alheio->slug.'/aulas/'.$aulas[0]->id.'/remover')
            ->assertNotFound();

        $this->assertSame(2, Aula::query()->where('curso_id', $alheio->id)->count());
    }

    public function test_descer_troca_a_aula_com_a_vizinha_e_renumera(): void
    {
        $eu = $this->instrutor('Gestor Desce');
        [$curso, $aulas] = $this->cursoComAulas($eu['id']);

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/aulas/'.$aulas[0]->id.'/mover', ['direcao' => 'down'])
            ->assertRedirect();

        $this->assertSame([$aulas[1]->id, $aulas[0]->id, $aulas[2]->id], $this->ordemAtual($curso));
        $this->assertSame([1, 2, 3], Aula::query()->where('curso_id', $curso->id)->orderBy('ordem')->pluck('ordem')->map(fn ($n) => (int) $n)->all());
    }

    public function test_subir_a_ultima_troca_com_a_penultima(): void
    {
        $eu = $this->instrutor('Gestor Sobe');
        [$curso, $aulas] = $this->cursoComAulas($eu['id']);

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/aulas/'.$aulas[2]->id.'/mover', ['direcao' => 'up']);

        $this->assertSame([$aulas[0]->id, $aulas[2]->id, $aulas[1]->id], $this->ordemAtual($curso));
    }

    /** Primeira não sobe e última não desce: nada a fazer, e não é erro. */
    public function test_subir_a_primeira_nao_muda_nada(): void
    {
        $eu = $this->instrutor('Gestor Sobe Primeira');
        [$curso, $aulas] = $this->cursoComAulas($eu['id']);
        $antes = $this->ordemAtual($curso);

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/aulas/'.$aulas[0]->id.'/mover', ['direcao' => 'up'])
            ->assertRedirect();

        $this->assertSame($antes, $this->ordemAtual($curso));
    }

    /** Mover só mexe na ORDEM: o conteúdo da aula não pode ser tocado. */
    public function test_mover_nao_altera_o_conteudo_das_aulas(): void
    {
        $eu = $this->instrutor('Gestor Conteudo Intacto');
        [$curso, $aulas] = $this->cursoComAulas($eu['id']);

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/aulas/'.$aulas[0]->id.'/mover', ['direcao' => 'down']);

        $this->assertSame('Conteudo 1', Aula::query()->whereKey($aulas[0]->id)->value('conteudo'));
        $this->assertSame('Aula numero 1', Aula::query()->whereKey($aulas[0]->id)->value('titulo'));
    }

    /** O banco pode ter posições repetidas; a ordem que a tela mostra é a que se move. */
    public function test_com_posicoes_repetidas_a_ordem_vira_de_1_a_n(): void
    {
        $eu = $this->instrutor('Gestor Posicoes Repetidas');
        [$curso, $aulas] = $this->cursoComAulas($eu['id']);
        Aula::query()->where('curso_id', $curso->id)->update(['ordem' => 5]);

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/aulas/'.$aulas[0]->id.'/mover', ['direcao' => 'up']);

        $this->assertSame([1, 2, 3], Aula::query()->where('curso_id', $curso->id)->orderBy('ordem')->pluck('ordem')->map(fn ($n) => (int) $n)->all());
    }

    public function test_direcao_invalida_e_recusada(): void
    {
        $eu = $this->instrutor('Gestor Direcao');
        [$curso, $aulas] = $this->cursoComAulas($eu['id']);

        $this->como($eu['token'])
            ->from('/inst/curso/'.$curso->slug.'/grade-curricular')
            ->post('/inst/curso/'.$curso->slug.'/aulas/'.$aulas[0]->id.'/mover', ['direcao' => 'lateral'])
            ->assertSessionHasErrors('direcao');
    }

    public function test_aula_de_outro_curso_nao_e_movida_por_este_endereco(): void
    {
        $eu = $this->instrutor('Gestor Aula Alheia');
        [$curso] = $this->cursoComAulas($eu['id'], 2, 'Curso Meu Da Grade');
        [, $deOutro] = $this->cursoComAulas($eu['id'], 2, 'Curso Irmao Da Grade');

        $this->como($eu['token'])
            ->from('/inst/curso/'.$curso->slug.'/grade-curricular')
            ->post('/inst/curso/'.$curso->slug.'/aulas/'.$deOutro[0]->id.'/mover', ['direcao' => 'down'])
            ->assertSessionHasErrors('grade');
    }

    /** ADR 12: retirar NÃO apaga, e diz quem e por quê. */
    public function test_retirar_inativa_a_aula_e_preserva_o_registro(): void
    {
        $eu = $this->instrutor('Gestor Retira');
        [$curso, $aulas] = $this->cursoComAulas($eu['id']);

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/aulas/'.$aulas[1]->id.'/remover')
            ->assertRedirect('/inst/curso/'.$curso->slug.'/grade-curricular');

        // Sai da lista...
        $this->assertSame([$aulas[0]->id, $aulas[2]->id], $this->ordemAtual($curso));

        // ...mas a linha continua no banco, com quem tirou.
        $linha = DB::table('aula')->where('id', $aulas[1]->id)->first();
        $this->assertNotNull($linha, 'A aula foi APAGADA do banco.');
        $this->assertNotNull($linha->inativado_em);
        $this->assertSame($eu['id'], $linha->inativado_por);
        $this->assertSame('Conteudo 2', $linha->conteudo);
    }

    /**
     * O defeito do `syncLessons`: ele só inativa as aulas que "saíram" quando a
     * lista que sobrou não está vazia, então a ÚLTIMA aula de um curso nunca
     * podia ser removida pela API de atualização do curso.
     */
    public function test_a_ultima_aula_pode_ser_retirada(): void
    {
        $eu = $this->instrutor('Gestor Ultima');
        [$curso, $aulas] = $this->cursoComAulas($eu['id'], 1);

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/aulas/'.$aulas[0]->id.'/remover');

        $this->assertSame([], $this->ordemAtual($curso));
        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/grade-curricular')
            ->assertSee('A grade deste curso está vazia.', escape: false);
    }

    /** A aula retirada some da conta de progresso: id órfão contava como concluída. */
    public function test_retirar_limpa_o_progresso_que_apontava_para_a_aula(): void
    {
        $eu = $this->instrutor('Gestor Progresso');
        [$curso, $aulas] = $this->cursoComAulas($eu['id']);
        $aluno = $this->makeStudent('Aluno Progresso Grade');
        ProgressoAluno::query()->create([
            'id' => 'prog-'.uniqid(),
            'usuario_id' => $aluno['id'],
            'nome_aluno' => $aluno['nome'],
            'curso_id' => $curso->id,
            'aulas_concluidas' => [$aulas[0]->id, $aulas[1]->id],
            'sessoes_ao_vivo_assistidas' => [],
        ]);

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/aulas/'.$aulas[1]->id.'/remover');

        $restante = ProgressoAluno::query()->where('usuario_id', $aluno['id'])->where('curso_id', $curso->id)->first();
        $this->assertNotNull($restante);
        $this->assertSame([$aulas[0]->id], array_values($restante->aulas_concluidas));
    }

    public function test_as_duas_escritas_ficam_na_auditoria(): void
    {
        $eu = $this->instrutor('Gestor Auditoria Grade');
        [$curso, $aulas] = $this->cursoComAulas($eu['id']);
        $antes = (int) DB::table('log_seguranca')->count();

        $this->como($eu['token'])->post('/inst/curso/'.$curso->slug.'/aulas/'.$aulas[0]->id.'/mover', ['direcao' => 'down']);
        $this->como($eu['token'])->post('/inst/curso/'.$curso->slug.'/aulas/'.$aulas[2]->id.'/remover');

        $this->assertGreaterThanOrEqual($antes + 2, (int) DB::table('log_seguranca')->count());
    }

    /** Sem aula nova nem selo de "Obrigatória": a coluna `isOptional` não existe. */
    public function test_nao_mostra_o_selo_de_obrigatoria_que_o_banco_nao_sustenta(): void
    {
        $eu = $this->instrutor('Gestor Sem Selo');
        [$curso] = $this->cursoComAulas($eu['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/grade-curricular')
            ->assertDontSee('Obrigatória', escape: false)
            ->assertDontSee('Opcional', escape: false)
            ->assertDontSee('Arraste', escape: false);
    }

    public function test_pelo_id_do_curso_vai_para_o_endereco_de_hoje(): void
    {
        $eu = $this->instrutor('Gestor Slug Grade');
        [$curso] = $this->cursoComAulas($eu['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->id.'/grade-curricular')
            ->assertRedirect('/inst/curso/'.$curso->slug.'/grade-curricular');
    }

    public function test_com_modulos_desligado_a_grade_responde_404(): void
    {
        config(['features.modulosAulas' => false]);
        $eu = $this->instrutor('Gestor Sem Modulos');
        [$curso] = $this->cursoComAulas($eu['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/grade-curricular')->assertNotFound();
    }

    /** O admin socorre qualquer curso. */
    public function test_admin_ve_e_move_aulas_de_qualquer_curso(): void
    {
        $outro = $this->instrutor('Gestor Do Admin Grade');
        [$curso, $aulas] = $this->cursoComAulas($outro['id']);
        $admin = Usuario::query()->where('papel', 'admin')->first();
        $this->assertNotNull($admin);
        $token = Jwt::issue((string) $admin->id, (string) $admin->nome, 'admin');

        $this->como($token)->get('/inst/curso/'.$curso->slug.'/grade-curricular')->assertOk();
        $this->como($token)->post('/inst/curso/'.$curso->slug.'/aulas/'.$aulas[0]->id.'/mover', ['direcao' => 'down']);

        $this->assertSame($aulas[1]->id, $this->ordemAtual($curso)[0]);
    }
}
